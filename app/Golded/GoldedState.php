<?php

declare(strict_types=1);

namespace App\Golded;

use App\Domain\LineClassifier;
use App\Domain\LineType;
use App\Domain\ThreadTree;
use App\Models\Area;
use App\Models\Message;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as SupportCollection;

/**
 * @phpstan-type Segment array{0: string, 1: string}
 * @phpstan-type AreaDisplayItem array{type: 'area', index: int, area: Area}
 * @phpstan-type SeparatorDisplayItem array{type: 'sep', label: string}
 */
class GoldedState
{
    /** @var 'areas'|'messages'|'reader'|'editor' */
    public string $screen = 'areas';

    public ?int $areaId = null;

    public ?int $messageId = null;

    public int $selectionIndex = 0;

    public int $scrollOffset = 0;

    public int $topOffset = 0;

    public bool $showKludges = false;

    /** @var Collection<int, Area>|null */
    private ?Collection $_areas = null;

    /** @var SupportCollection<int, Message>|null */
    private ?SupportCollection $_messages = null;

    public function __construct(public int $cols = 80, public int $rows = 25) {}

    public function resize(int $cols, int $rows): void
    {
        $this->cols = $cols;
        $this->rows = $rows;
    }

    /** @return Collection<int, Area> */
    public function areas(): Collection
    {
        if (! $this->_areas instanceof Collection) {
            $sort = strtoupper((string) config('golded.arealistsort', 'YUE'));
            $query = Area::query();

            $typeOrder = collect(config('golded.areasep', []))->pluck('area_type')->values();

            foreach (str_split($sort) as $token) {
                match ($token) {
                    'T' => $typeOrder->isNotEmpty()
                        ? $query->orderByRaw(
                            'CASE area_type '
                            .$typeOrder->map(fn ($t, $i): string => "WHEN '{$t}' THEN {$i}")->implode(' ')
                            .' ELSE '.$typeOrder->count().' END'
                        )
                        : null,
                    'G' => $query->orderBy('group_id'),
                    'Y' => $query->orderByRaw('CASE WHEN unread_count > 0 THEN 0 ELSE 1 END'),
                    'U' => $query->orderByDesc('unread_count'),
                    'E' => $query->orderBy('echoid'),
                    'O' => $query->orderBy('sort_order'),
                    'N' => $query->orderBy('name'),
                    default => null,
                };
            }

            $this->_areas = $query->get();
        }

        return $this->_areas;
    }

    /** @return SupportCollection<int, Message> */
    public function messages(): SupportCollection
    {
        if (! $this->_messages instanceof SupportCollection) {
            $msgs = Message::where('area_id', $this->areaId ?? 0)->orderBy('msgno')->get();
            $this->_messages = (new ThreadTree)->order($msgs);
        }

        return $this->_messages;
    }

    public function currentMessage(): ?Message
    {
        return $this->messageId ? Message::find($this->messageId) : null;
    }

    public function handleKey(string $key): void
    {
        match ($this->screen) {
            'areas' => $this->handleAreasKey($key),
            'messages' => $this->handleMessagesKey($key),
            'reader' => $this->handleReaderKey($key),
            'editor' => null,
        };
    }

    private function handleAreasKey(string $key): void
    {
        $count = $this->areas()->count();
        match ($key) {
            'ArrowDown' => $this->selectionIndex = min($this->selectionIndex + 1, max(0, $count - 1)),
            'ArrowUp' => $this->selectionIndex = max(0, $this->selectionIndex - 1),
            'PageDown' => $this->selectionIndex = min($this->selectionIndex + 20, max(0, $count - 1)),
            'PageUp' => $this->selectionIndex = max(0, $this->selectionIndex - 20),
            'Home' => $this->selectionIndex = 0,
            'End' => $this->selectionIndex = max(0, $count - 1),
            'ArrowRight', 'Enter' => $this->openArea(),
            default => null,
        };
        $this->clampTopOffsetAreas($count);
    }

    private function handleMessagesKey(string $key): void
    {
        $count = $this->messages()->count();
        match ($key) {
            'ArrowDown' => $this->selectionIndex = min($this->selectionIndex + 1, max(0, $count - 1)),
            'ArrowUp' => $this->selectionIndex = max(0, $this->selectionIndex - 1),
            'PageDown' => $this->selectionIndex = min($this->selectionIndex + 20, max(0, $count - 1)),
            'PageUp' => $this->selectionIndex = max(0, $this->selectionIndex - 20),
            'Home' => $this->selectionIndex = 0,
            'End' => $this->selectionIndex = max(0, $count - 1),
            'Enter' => $this->openMessage(),
            'ArrowLeft', 'Escape' => $this->backToAreas(),
            default => null,
        };
        $this->clampTopOffset($count);
    }

    private function handleReaderKey(string $key): void
    {
        match ($key) {
            'ArrowDown' => $this->scrollOffset++,
            'ArrowUp' => $this->scrollOffset = max(0, $this->scrollOffset - 1),
            'PageDown' => $this->scrollOffset += 18,
            'PageUp' => $this->scrollOffset = max(0, $this->scrollOffset - 18),
            'Home' => $this->scrollOffset = 0,
            'ArrowRight' => $this->nextMessage(),
            'ArrowLeft' => $this->prevMessage(),
            'Alt+ArrowRight', 'Alt+u' => $this->nextUnreadMessage(),
            'Alt+ArrowLeft' => $this->prevUnreadMessage(),
            'Alt+j' => $this->toggleReadUnread(),
            '-' => $this->goToParent(),
            '+' => $this->goToFirstReply(),
            '*' => $this->goToNextSibling(),
            'k' => $this->showKludges = ! $this->showKludges,
            'Escape' => $this->backToMessages(),
            default => null,
        };
    }

    private function clampTopOffset(int $count, int $window = 20): void
    {
        if ($this->selectionIndex < $this->topOffset) {
            $this->topOffset = $this->selectionIndex;
        } elseif ($this->selectionIndex >= $this->topOffset + $window) {
            $this->topOffset = $this->selectionIndex - $window + 1;
        }
        $this->topOffset = max(0, min($this->topOffset, max(0, $count - $window)));
    }

    /**
     * Separator-aware topOffset clamp for the area list.
     *
     * topOffset is stored as a display-list position (not an area index) so
     * separators and areas count as equal-height rows.
     */
    private function clampTopOffsetAreas(int $count): void
    {
        $this->selectionIndex = max(0, min($this->selectionIndex, max(0, $count - 1)));

        $displayList = $this->buildDisplayList();
        $total = count($displayList);
        $contentRows = $this->rows - 4; // header + top border + bottom border + status

        $selDisplayPos = 0;
        foreach ($displayList as $pos => $item) {
            if ($item['type'] === 'area' && $item['index'] === $this->selectionIndex) {
                $selDisplayPos = $pos;
                break;
            }
        }

        if ($selDisplayPos < $this->topOffset) {
            $this->topOffset = $selDisplayPos;
        } elseif ($selDisplayPos >= $this->topOffset + $contentRows) {
            $this->topOffset = $selDisplayPos - $contentRows + 1;
        }

        $this->topOffset = max(0, min($this->topOffset, max(0, $total - $contentRows)));
    }

    /** @return list<AreaDisplayItem|SeparatorDisplayItem> */
    private function buildDisplayList(): array
    {
        $areasep = collect(config('golded.areasep', []));
        $lastType = null;
        $list = [];

        foreach ($this->areas() as $areaIndex => $area) {
            $areaType = $area->area_type ?? '';

            if ($areaType !== $lastType && $areaType !== '' && $areasep->isNotEmpty()) {
                $sepEntry = $areasep->firstWhere('area_type', $areaType);
                if ($sepEntry) {
                    $list[] = ['type' => 'sep', 'label' => (string) $sepEntry['label']];
                }
                $lastType = $areaType;
            }

            $list[] = ['type' => 'area', 'index' => (int) $areaIndex, 'area' => $area];
        }

        return $list;
    }

    private function openArea(): void
    {
        $area = $this->areas()->get($this->selectionIndex);
        if (! $area) {
            return;
        }
        $this->areaId = $area->id;
        $this->selectionIndex = 0;
        $this->topOffset = 0;
        $this->screen = 'messages';
        $this->_messages = null;
    }

    private function openMessage(): void
    {
        $message = $this->messages()->get($this->selectionIndex);
        if (! $message) {
            return;
        }
        $this->messageId = $message->id;
        $this->scrollOffset = 0;
        $this->screen = 'reader';
        $this->markRead($message->id);
    }

    private function markRead(int $messageId): void
    {
        $message = Message::find($messageId);
        if (! $message || $message->is_read) {
            return;
        }
        $message->update(['is_read' => true]);
        Area::where('id', $message->area_id)->where('unread_count', '>', 0)->decrement('unread_count');
        $this->_areas = null;
        $this->_messages = null;
    }

    private function markUnread(int $messageId): void
    {
        $message = Message::find($messageId);
        if (! $message || ! $message->is_read) {
            return;
        }
        $message->update(['is_read' => false]);
        Area::where('id', $message->area_id)->increment('unread_count');
        $this->_areas = null;
        $this->_messages = null;
    }

    private function backToAreas(): void
    {
        $this->screen = 'areas';
        $this->selectionIndex = 0;
    }

    private function backToMessages(): void
    {
        $this->screen = 'messages';
        $this->scrollOffset = 0;
    }

    private function nextMessage(): void
    {
        $messages = $this->messages();
        $current = $messages->search(fn ($m): bool => $m->id === $this->messageId);
        if ($current !== false && $current < $messages->count() - 1) {
            $this->messageId = $messages->get($current + 1)->id;
            $this->scrollOffset = 0;
        }
    }

    private function prevMessage(): void
    {
        $messages = $this->messages();
        $current = $messages->search(fn ($m): bool => $m->id === $this->messageId);
        if ($current > 0) {
            $this->messageId = $messages->get($current - 1)->id;
            $this->scrollOffset = 0;
        }
    }

    private function nextUnreadMessage(): void
    {
        $messages = $this->messages();
        $current = $messages->search(fn ($m): bool => $m->id === $this->messageId);
        if ($current === false) {
            return;
        }
        $next = $messages->slice($current + 1)->first(fn ($m): bool => ! $m->is_read);
        if ($next) {
            $this->messageId = $next->id;
            $this->scrollOffset = 0;
            $this->markRead($next->id);
        }
    }

    private function prevUnreadMessage(): void
    {
        $messages = $this->messages();
        $current = $messages->search(fn ($m): bool => $m->id === $this->messageId);
        if ($current === false || $current === 0) {
            return;
        }
        $prev = $messages->slice(0, $current)->last(fn ($m): bool => ! $m->is_read);
        if ($prev) {
            $this->messageId = $prev->id;
            $this->scrollOffset = 0;
            $this->markRead($prev->id);
        }
    }

    private function toggleReadUnread(): void
    {
        if (! $this->messageId) {
            return;
        }
        $message = Message::find($this->messageId);
        if (! $message) {
            return;
        }
        if ($message->is_read) {
            $this->markUnread($this->messageId);
        } else {
            $this->markRead($this->messageId);
        }
    }

    private function goToParent(): void
    {
        $current = $this->currentMessage();
        if (! $current?->reply_to_msgno) {
            return;
        }
        $parent = Message::where('area_id', $this->areaId)->where('msgno', $current->reply_to_msgno)->first();
        if ($parent) {
            $this->messageId = $parent->id;
            $this->scrollOffset = 0;
        }
    }

    private function goToFirstReply(): void
    {
        $current = $this->currentMessage();
        if (! $current?->reply1st_msgno) {
            return;
        }
        $reply = Message::where('area_id', $this->areaId)->where('msgno', $current->reply1st_msgno)->first();
        if ($reply) {
            $this->messageId = $reply->id;
            $this->scrollOffset = 0;
        }
    }

    private function goToNextSibling(): void
    {
        $current = $this->currentMessage();
        if (! $current?->replynext_msgno) {
            return;
        }
        $sibling = Message::where('area_id', $this->areaId)->where('msgno', $current->replynext_msgno)->first();
        if ($sibling) {
            $this->messageId = $sibling->id;
            $this->scrollOffset = 0;
        }
    }

    // ── Screen builders ───────────────────────────────────────────────────────

    /**
     * @return array<int, array<int, array{0: string, 1: string}>>
     */
    public function currentScreen(): array
    {
        return match ($this->screen) {
            'areas' => $this->areasScreen(),
            'messages' => $this->messagesScreen(),
            'reader' => $this->readerScreen(),
            'editor' => $this->editorScreen(),
        };
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    /**
     * Build one line from segments, padded to $width chars.
     *
     * @param  array<int, array{0: string, 1: string}>  $segments
     * @return array<int, array{0: string, 1: string}>
     */
    private function ln(array $segments, ?int $width = null): array
    {
        $actualWidth = $width ?? $this->cols;
        $len = array_sum(array_map(fn (array $s): int => mb_strlen($s[0]), $segments));
        $result = $segments;

        if ($len < $actualWidth) {
            $result[] = [str_repeat(' ', $actualWidth - $len), 'cga-black-lgrey'];
        }

        return $result;
    }

    /**
     * Bordered content row: │ [content] │
     *
     * @param  array<int, array{0: string, 1: string}>  $segments
     * @return array<int, array{0: string, 1: string}>
     */
    private function row(array $segments, string $fillClass = 'cga-black-lgrey'): array
    {
        $b = 'cga-yellow-lgrey';
        $contentWidth = $this->cols - 2;
        $len = array_sum(array_map(fn (array $s): int => mb_strlen($s[0]), $segments));

        $result = [['│', $b]];
        foreach ($segments as $seg) {
            $result[] = $seg;
        }
        if ($len < $contentWidth) {
            $result[] = [str_repeat(' ', $contentWidth - $len), $fillClass];
        }
        $result[] = ['│', $b];

        return $result;
    }

    /**
     * @return array<int, array{0: string, 1: string}>
     */
    private function sep(string $fillClass = 'cga-yellow-lgrey'): array
    {
        return $this->ln([['├'.str_repeat('─', $this->cols - 2).'┤', $fillClass]]);
    }

    /**
     * @return array<int, array{0: string, 1: string}>
     */
    private function bottom(): array
    {
        return $this->ln([['└'.str_repeat('─', $this->cols - 2).'┘', 'cga-yellow-lgrey']]);
    }

    /**
     * Top border with embedded title/section/info.
     *
     * @return array<int, array{0: string, 1: string}>
     */
    private function top(string $title, string $section, string $info, bool $brackets = true): array
    {
        $y = 'cga-yellow-lgrey';
        $r = 'cga-red-lgrey';

        if ($brackets) {
            $inner = '─['.$title.']';
            $titleSegments = [['─[', $y], [$title, $r], [']', $y]];
        } else {
            $inner = '─ '.$title.' ─';
            $titleSegments = [['─ ', $y], [$title, $r], [' ─', $y]];
        }

        $mid = ' '.$section.' ';
        $right = ' '.$info.' ─';

        $dashSpace = ($this->cols - 2)
            - mb_strlen($inner)
            - mb_strlen($mid)
            - mb_strlen($right);

        $dashSpace = max(0, $dashSpace);
        $leftDashes = intdiv($dashSpace, 2);
        $rightDashes = $dashSpace - $leftDashes;

        return $this->ln(array_merge(
            [['┌', $y]],
            $titleSegments,
            [
                [str_repeat('─', $leftDashes), $y],
                [$mid, $r],
                [str_repeat('─', $rightDashes), $y],
                [' ', $y],
                [$info, $r],
                [' ─┐', $y],
            ]
        ));
    }

    /**
     * Status bar text (white-on-blue), centred context string.
     *
     * @return array<int, array{0: string, 1: string}>
     */
    private function status(string $left, string $center, string $right): array
    {
        $l = ' '.$left;
        $r = $right.' ';
        $gap = max(0, $this->cols - mb_strlen($l) - mb_strlen($center) - mb_strlen($r));
        $padLeft = intdiv($gap, 2);

        $text = $l
            .str_repeat(' ', $padLeft)
            .$center
            .str_repeat(' ', $gap - $padLeft)
            .$r;

        return [[$text, 'cga-white-blue']];
    }

    // ── Screen builders ───────────────────────────────────────────────────────

    /**
     * @return array<int, array<int, array{0: string, 1: string}>>
     */
    private function areasScreen(): array
    {
        $contentWidth = $this->cols - 2;
        $descWidth = max(20, $contentWidth - 36); // 36 = fixed columns
        $contentRows = $this->rows - 4;             // header + top + bottom + status

        $y = 'cga-yellow-lgrey';
        $b = 'cga-blue-lgrey';
        $n = 'cga-black-lgrey';
        $s = 'cga-white-blue';
        $sep = 'cga-blue-lgrey';
        $hdr = 'cga-yellow-blue';

        $rows = [];

        // Row 0: header bar
        $rows[] = $this->ln([['>>Pick New Area:', $hdr]]);

        // Row 1: top border with column labels
        $headerDashes = max(0, $contentWidth - 49); // 49 = fixed label chars
        $topContent = '─Area─Description─'
            .str_repeat('─', $headerDashes)
            .'Msgs──'
            .'─'
            .'New──'
            .'─'
            .'EchoID─────────Grp';
        $rows[] = $this->ln([['┌'.$topContent.'┐', $y]]);

        // Content rows
        $displayList = $this->buildDisplayList();

        $emitted = 0;
        for ($pos = $this->topOffset; $pos < count($displayList) && $emitted < $contentRows; $pos++) {
            $item = $displayList[$pos];

            if ($item['type'] === 'sep') {
                $label = $item['label'];
                $dashes = max(0, $contentWidth - 2 - mb_strlen((string) $label));
                $left = intdiv($dashes, 2);
                $right = $dashes - $left;
                $content = str_repeat('─', $left).' '.$label.' '.str_repeat('─', max(0, $right - 2));
                $rows[] = $this->row([[mb_str_pad($content, $contentWidth), $sep]]);
            } else {
                $area = $item['area'];
                $absIndex = $item['index'];
                $selected = $absIndex === $this->selectionIndex;
                $hasUnread = ($area->unread_count ?? 0) > 0;
                $c = $selected ? $s : ($hasUnread ? $b : $n);

                $num = str_pad((string) ($absIndex + 1), 3, ' ', STR_PAD_LEFT);
                $ind = $hasUnread ? '>' : ' ';
                $desc = mb_str_pad(mb_substr((string) $area->name, 0, $descWidth), $descWidth);
                $msgs = $area->message_count !== null
                    ? str_pad((string) $area->message_count, 6, ' ', STR_PAD_LEFT)
                    : '     -';
                $new = $area->message_count !== null
                    ? str_pad((string) ($area->unread_count ?? 0), 5, ' ', STR_PAD_LEFT)
                    : '    -';
                $echo = mb_str_pad(mb_substr((string) ($area->echoid ?? ''), 0, 16), 16);
                $grp = mb_substr((string) ($area->group_id ?? ' '), 0, 1);

                $rows[] = $this->row([
                    [$num, $c],
                    [$ind, $c],
                    [' ', $c],
                    [$desc, $c],
                    [$msgs, $c],
                    [' ', $c],
                    [$new, $c],
                    [' ', $c],
                    [$echo, $c],
                    [' ', $c],
                    [$grp, $c],
                ], $c);
            }

            $emitted++;
        }

        for ($i = $emitted; $i < $contentRows; $i++) {
            $rows[] = $this->row([], $n);
        }

        $rows[] = $this->bottom();

        $area = $this->areas()->get($this->selectionIndex);
        $areaLabel = $area instanceof Area ? ($area->echoid ?? $area->name) : '';
        $areaMsgs = $area instanceof Area ? ($area->message_count ?? 0) : 0;
        $areaUnread = $area instanceof Area ? ($area->unread_count ?? 0) : 0;
        $rows[] = $this->status(
            config('golded.version', 'GoldED'),
            "{$areaLabel}: {$areaMsgs} msgs, {$areaUnread} unread, 0 personal",
            date('H:i:s')
        );

        return $rows;
    }

    /**
     * @return array<int, array<int, array{0: string, 1: string}>>
     */
    private function messagesScreen(): array
    {
        $contentWidth = $this->cols - 2;
        $subjWidth = max(10, $contentWidth - 48); // 48 = fixed columns
        $contentRows = $this->rows - 5;
        $b = 'cga-blue-lgrey';
        $n = 'cga-black-lgrey';
        $s = 'cga-white-blue';

        $rows = [];

        $messages = $this->messages();
        $total = $messages->count();
        $area = $this->areaId ? Area::find($this->areaId) : null;
        $areaName = $area instanceof Area ? $area->name : 'Messages';
        $unread = $messages->where('is_read', false)->count();
        $summary = $unread > 0 ? "{$total} msgs, {$unread} new" : "{$total} msgs";

        $rows[] = $this->top($areaName, 'Message List', $summary, false);

        // Column header
        $rows[] = $this->row([
            ['     #', $b],
            [' ', $b],
            ['        ', $b],
            [' ', $b],
            [' ', $b],
            ['From                 ', $b],
            [' ', $b],
            [mb_str_pad('Subject', $subjWidth), $b],
            ['Date     ', $b],
        ]);

        $rows[] = $this->sep();

        $tree = (new ThreadTree)->build($messages);
        $visible = $messages->slice($this->topOffset, $contentRows);

        foreach ($visible->values() as $i => $msg) {
            $absIndex = $this->topOffset + $i;
            $selected = $absIndex === $this->selectionIndex;
            $c = $selected ? $s : ($msg->is_read ? $n : $b);

            $num = str_pad((string) ($absIndex + 1), 6, ' ', STR_PAD_LEFT);
            $thread = $tree[$msg->id] ?? str_repeat(' ', 8);
            $bk = $msg->is_bookmarked ? '►' : ' ';
            $mk = $msg->is_marked ? '■' : ' ';
            $from = mb_str_pad(mb_substr((string) $msg->from_name, 0, 21), 21);
            $subj = mb_str_pad(mb_substr((string) $msg->subject, 0, $subjWidth), $subjWidth);
            $date = $msg->posted_at
                ? $msg->posted_at->format('d M y')
                : str_repeat(' ', 9);

            $rows[] = $this->row([
                [$num, $c],
                [' ', $c],
                [$thread, $c],
                [$bk, $c],
                [$mk, $c],
                [$from, $c],
                [' ', $c],
                [$subj, $c],
                [$date, $c],
            ], $c);
        }

        $dataRows = max(0, $contentRows - $visible->count());
        for ($i = 0; $i < $dataRows; $i++) {
            $rows[] = $this->row([], $n);
        }

        $rows[] = $this->bottom();
        $rows[] = $this->status(config('golded.version', 'GoldED'), 'Msg '.($this->selectionIndex + 1)." of {$total}", date('H:i:s'));

        return $rows;
    }

    /**
     * @return array<int, array<int, array{0: string, 1: string}>>
     */
    private function readerScreen(): array
    {
        $contentWidth = $this->cols - 2;
        $bodyRows = $this->rows - 7;
        $dg = 'cga-dgrey-lgrey';
        $b = 'cga-blue-lgrey';
        $n = 'cga-black-lgrey';
        $q1 = 'cga-blue-lgrey';
        $tear = 'cga-lblue-lgrey';
        $orig = 'cga-lblue-lgrey';

        $rows = [];

        $msg = $this->currentMessage();
        $messages = $this->messages();
        $total = $messages->count();
        $pos = $messages->search(fn ($m): bool => $m->id === $this->messageId);
        $msgno = $pos !== false ? $pos + 1 : 1;
        $area = $this->areaId ? Area::find($this->areaId) : null;
        $areaName = $area instanceof Area ? $area->name : '';
        $echoid = $area instanceof Area ? ($area->echoid ?? '') : '';

        $fromAddress = $msg instanceof Message ? ($msg->from_address ?? '') : '';
        $rows[] = $this->top("[{$msgno}] {$areaName}", $fromAddress, $echoid, false);

        $dateStr = $msg instanceof Message && $msg->posted_at !== null ? $msg->posted_at->format('d M y') : '';
        $rows[] = $this->row([
            [" Msg: {$msgno} of {$total}", $b],
            [str_repeat(' ', max(0, $contentWidth - 2 - mb_strlen(" Msg: {$msgno} of {$total}") - mb_strlen((string) $dateStr) - 1)).$dateStr.' ', $b],
        ]);

        $fromName = $msg instanceof Message ? ($msg->from_name ?? '') : '';
        $toName = $msg instanceof Message ? ($msg->to_name ?? '') : '';
        $subject = $msg instanceof Message ? ($msg->subject ?? '') : '';

        $rows[] = $this->row([[' From: ', $b], [str_pad(mb_substr($fromName, 0, $contentWidth - 8), $contentWidth - 8).' ', $n]]);
        $rows[] = $this->row([[' To  : ', $b], [str_pad(mb_substr($toName, 0, $contentWidth - 8), $contentWidth - 8).' ', $n]]);
        $rows[] = $this->row([[' Subj: ', $b], [str_pad(mb_substr($subject, 0, $contentWidth - 8), $contentWidth - 8).' ', $n]]);

        $rows[] = $this->sep();

        $rawLines = $msg instanceof Message ? explode("\n", str_replace("\r", '', $msg->body_text)) : [];
        $bodyLines = [];
        $classifier = new LineClassifier;

        foreach ($rawLines as $line) {
            if (! $this->showKludges && $classifier->classify($line) === LineType::Kludge) {
                continue;
            }
            $wrapped = explode("\n", wordwrap($line, $contentWidth - 2, "\n", true));
            foreach ($wrapped as $wl) {
                $bodyLines[] = $wl;
            }
        }

        $visible = array_slice($bodyLines, $this->scrollOffset, $bodyRows);

        foreach ($visible as $line) {
            $type = $classifier->classify($line);
            $class = match ($type) {
                LineType::Kludge => $dg,
                LineType::Tearline => $tear,
                LineType::Origin => $orig,
                LineType::Quote1 => $q1,
                LineType::Quote2 => $b,
                LineType::Normal => $n,
            };
            $display = $type === LineType::Kludge
                ? str_replace("\x01", '@', $line)
                : $line;
            $rows[] = $this->row([[' '.$display, $class]]);
        }

        for ($i = count($visible); $i < $bodyRows; $i++) {
            $rows[] = $this->row([['', $n]]);
        }

        $rows[] = $this->bottom();
        $rows[] = $this->status(config('golded.version', 'GoldED'), "Msg {$msgno} of {$total}", date('H:i:s'));

        return $rows;
    }

    /**
     * @return array<int, array<int, array{0: string, 1: string}>>
     */
    private function editorScreen(): array
    {
        $y = 'cga-yellow-lgrey';
        $r = 'cga-red-lgrey';
        $b = 'cga-blue-lgrey';
        $n = 'cga-black-lgrey';
        $q1 = 'cga-blue-lgrey';

        $rows = [];

        $rows[] = $this->ln([
            ['┌─ ', $y],
            ['Composing new message', $r],
            [' '.str_repeat('─', $this->cols - 3 - 21 - 1).'─┐', $y],
        ]);

        $rows[] = $this->row([[str_repeat(' ', $this->cols - 12).'14 Mar 94 ', $b]]);
        $rows[] = $this->row([[' From : ', $b], ['Odinn Sorensen (2:236/77)', $n]]);
        $rows[] = $this->row([[' To   : ', $b], ['Lars Jensen', $n]]);
        $rows[] = $this->row([[' Subj : ', $b], ['Re: GoldED keybindings', $n]]);
        $rows[] = $this->sep();

        $edit = [
            [' ', $n],
            [' Lars Jensen wrote:', $n],
            [' ', $n],
            [' > I noticed the key binding for AREA select seems odd —', $q1],
            [' > pressing Right should open the area but it doesn\'t?', $q1],
            [' ', $n],
            [' █', $n],
            [' ', $n],
            [' ', $n],
            [' ', $n],
            [' ', $n],
            [' ', $n],
            [' ', $n],
            [' ', $n],
            [' ', $n],
            [' ', $n],
            [' ', $n],
        ];

        foreach ($edit as [$text, $class]) {
            $rows[] = $this->row([[$text, $class]]);
        }

        $rows[] = $this->bottom();
        $rows[] = $this->status(config('golded.version', 'GoldED'), '[INS] Line 7, Col 1  F2=Save  Esc=Abort', date('H:i:s'));

        return $rows;
    }
}
