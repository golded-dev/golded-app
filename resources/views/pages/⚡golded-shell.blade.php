<?php

use App\Domain\LineClassifier;
use App\Domain\LineType;
use App\Domain\ThreadTree;
use App\Models\Area;
use App\Models\Message;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts::terminal')] #[Title('GoldED 7')] class extends Component
{
    public string $screen = 'areas';
    public ?int $areaId = null;
    public ?int $messageId = null;
    public int $selectionIndex = 0;
    public int $scrollOffset = 0;
    public int $topOffset = 0;
    public bool $showKludges = false;

    #[Computed]
    public function areas(): \Illuminate\Database\Eloquent\Collection
    {
        $sort  = strtoupper(config('golded.arealistsort', 'YUE'));
        $query = Area::query();

        // Type order: Net → EMail → Echo → News → Local → (null/other)
        $typeOrder = collect(config('golded.areasep', []))->pluck('area_type')->values();

        foreach (str_split($sort) as $token) {
            match ($token) {
                'T' => $typeOrder->isNotEmpty()
                    ? $query->orderByRaw(
                        'CASE area_type '
                        . $typeOrder->map(fn ($t, $i) => "WHEN '{$t}' THEN {$i}")->implode(' ')
                        . ' ELSE '.$typeOrder->count().' END'
                    )
                    : null,
                'G' => $query->orderBy('group_id'),
                'Y' => $query->orderByRaw('CASE WHEN unread_count > 0 THEN 0 ELSE 1 END'),
                'U' => $query->orderByDesc('unread_count'),
                'E' => $query->orderBy('echoid'),
                'O' => $query->orderBy('sort_order'),
                'N' => $query->orderBy('name'),
                // F = favourite (not yet implemented), S = last-seen — both no-ops
                default => null,
            };
        }

        return $query->get();
    }

    #[Computed]
    public function messages(): \Illuminate\Support\Collection
    {
        $msgs = Message::where('area_id', $this->areaId ?? 0)->orderBy('msgno')->get();

        return (new ThreadTree)->order($msgs);
    }

    #[Computed]
    public function currentMessage(): ?Message
    {
        return $this->messageId ? Message::find($this->messageId) : null;
    }

    public function handleKey(string $key): void
    {
        match ($this->screen) {
            'areas'    => $this->handleAreasKey($key),
            'messages' => $this->handleMessagesKey($key),
            'reader'   => $this->handleReaderKey($key),
            'editor'   => null,
        };
    }

    private function handleAreasKey(string $key): void
    {
        $count = $this->areas->count();
        match ($key) {
            'ArrowDown'  => $this->selectionIndex = min($this->selectionIndex + 1, max(0, $count - 1)),
            'ArrowUp'    => $this->selectionIndex = max(0, $this->selectionIndex - 1),
            'PageDown'   => $this->selectionIndex = min($this->selectionIndex + 20, max(0, $count - 1)),
            'PageUp'     => $this->selectionIndex = max(0, $this->selectionIndex - 20),
            'Home'       => $this->selectionIndex = 0,
            'End'        => $this->selectionIndex = max(0, $count - 1),
            'ArrowRight', 'Enter' => $this->openArea(),
            default => null,
        };
        $this->clampTopOffsetAreas($count);
    }

    private function handleMessagesKey(string $key): void
    {
        $count = $this->messages->count();
        match ($key) {
            'ArrowDown'  => $this->selectionIndex = min($this->selectionIndex + 1, max(0, $count - 1)),
            'ArrowUp'    => $this->selectionIndex = max(0, $this->selectionIndex - 1),
            'PageDown'   => $this->selectionIndex = min($this->selectionIndex + 20, max(0, $count - 1)),
            'PageUp'     => $this->selectionIndex = max(0, $this->selectionIndex - 20),
            'Home'       => $this->selectionIndex = 0,
            'End'        => $this->selectionIndex = max(0, $count - 1),
            'Enter'      => $this->openMessage(),
            'ArrowLeft', 'Escape' => $this->backToAreas(),
            default => null,
        };
        $this->clampTopOffset($count);
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
     * topOffset is stored as a DISPLAY-LIST POSITION (not an area index), so
     * separators and areas are treated as equal-height rows.  This eliminates
     * the 2-row visual jump that occurred when a separator sat at the boundary
     * between one topOffset value and the next.
     */
    private function clampTopOffsetAreas(int $count): void
    {
        $this->selectionIndex = max(0, min($this->selectionIndex, max(0, $count - 1)));

        $displayList = $this->buildDisplayList();
        $total       = count($displayList);
        $contentRows = 21;

        // Find the display-list position of the selected area
        $selDisplayPos = 0;
        foreach ($displayList as $pos => $item) {
            if ($item['type'] === 'area' && $item['index'] === $this->selectionIndex) {
                $selDisplayPos = $pos;
                break;
            }
        }

        // Scroll up
        if ($selDisplayPos < $this->topOffset) {
            $this->topOffset = $selDisplayPos;
        }
        // Scroll down
        elseif ($selDisplayPos >= $this->topOffset + $contentRows) {
            $this->topOffset = $selDisplayPos - $contentRows + 1;
        }

        // Hard-clamp: never scroll past the end of the list
        $this->topOffset = max(0, min($this->topOffset, max(0, $total - $contentRows)));
    }

    /** @return array<int, array{type: string, ...}> */
    private function buildDisplayList(): array
    {
        $areasep  = collect(config('golded.areasep', []));
        $lastType = null;
        $list     = [];

        foreach ($this->areas as $areaIndex => $area) {
            $areaType = $area->area_type ?? '';

            if ($areaType !== $lastType && $areaType !== '' && $areasep->isNotEmpty()) {
                $sepEntry = $areasep->firstWhere('area_type', $areaType);
                if ($sepEntry) {
                    $list[] = ['type' => 'sep', 'label' => $sepEntry['label']];
                }
                $lastType = $areaType;
            }

            $list[] = ['type' => 'area', 'index' => $areaIndex, 'area' => $area];
        }

        return $list;
    }

    private function handleReaderKey(string $key): void
    {
        match ($key) {
            'ArrowDown'               => $this->scrollOffset++,
            'ArrowUp'                 => $this->scrollOffset = max(0, $this->scrollOffset - 1),
            'PageDown'                => $this->scrollOffset += 18,
            'PageUp'                  => $this->scrollOffset = max(0, $this->scrollOffset - 18),
            'Home'                    => $this->scrollOffset = 0,
            'ArrowRight'              => $this->nextMessage(),
            'ArrowLeft'               => $this->prevMessage(),
            'Alt+ArrowRight', 'Alt+u' => $this->nextUnreadMessage(),
            'Alt+ArrowLeft'           => $this->prevUnreadMessage(),
            'Alt+j'                   => $this->toggleReadUnread(),
            '-'                       => $this->goToParent(),
            '+'                       => $this->goToFirstReply(),
            '*'                       => $this->goToNextSibling(),
            'k'                       => $this->showKludges = ! $this->showKludges,
            'Escape'                  => $this->backToMessages(),
            default                   => null,
        };
    }

    private function openArea(): void
    {
        $area = $this->areas->get($this->selectionIndex);
        if (! $area) {
            return;
        }
        $this->areaId         = $area->id;
        $this->selectionIndex = 0;
        $this->topOffset      = 0;
        $this->screen         = 'messages';
    }

    private function openMessage(): void
    {
        $message = $this->messages->get($this->selectionIndex);
        if (! $message) {
            return;
        }
        $this->messageId    = $message->id;
        $this->scrollOffset = 0;
        $this->screen       = 'reader';
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
        unset($this->areas);
    }

    private function markUnread(int $messageId): void
    {
        $message = Message::find($messageId);
        if (! $message || ! $message->is_read) {
            return;
        }
        $message->update(['is_read' => false]);
        Area::where('id', $message->area_id)->increment('unread_count');
        unset($this->areas);
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
        $messages = $this->messages;
        $current = $messages->search(fn ($m) => $m->id === $this->messageId);
        if ($current !== false && $current < $messages->count() - 1) {
            $this->messageId = $messages->get($current + 1)->id;
            $this->scrollOffset = 0;
        }
    }

    private function prevMessage(): void
    {
        $messages = $this->messages;
        $current = $messages->search(fn ($m) => $m->id === $this->messageId);
        if ($current > 0) {
            $this->messageId = $messages->get($current - 1)->id;
            $this->scrollOffset = 0;
        }
    }

    private function nextUnreadMessage(): void
    {
        $messages = $this->messages;
        $current  = $messages->search(fn ($m) => $m->id === $this->messageId);
        if ($current === false) {
            return;
        }
        $next = $messages->slice($current + 1)->first(fn ($m) => ! $m->is_read);
        if ($next) {
            $this->messageId    = $next->id;
            $this->scrollOffset = 0;
            $this->markRead($next->id);
        }
    }

    private function prevUnreadMessage(): void
    {
        $messages = $this->messages;
        $current  = $messages->search(fn ($m) => $m->id === $this->messageId);
        if ($current === false || $current === 0) {
            return;
        }
        $prev = $messages->slice(0, $current)->last(fn ($m) => ! $m->is_read);
        if ($prev) {
            $this->messageId    = $prev->id;
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
        $current = $this->currentMessage;
        if (! $current?->reply_to_msgno) {
            return;
        }
        $parent = Message::where('area_id', $this->areaId)->where('msgno', $current->reply_to_msgno)->first();
        if ($parent) {
            $this->messageId    = $parent->id;
            $this->scrollOffset = 0;
        }
    }

    private function goToFirstReply(): void
    {
        $current = $this->currentMessage;
        if (! $current?->reply1st_msgno) {
            return;
        }
        $reply = Message::where('area_id', $this->areaId)->where('msgno', $current->reply1st_msgno)->first();
        if ($reply) {
            $this->messageId    = $reply->id;
            $this->scrollOffset = 0;
        }
    }

    private function goToNextSibling(): void
    {
        $current = $this->currentMessage;
        if (! $current?->replynext_msgno) {
            return;
        }
        $sibling = Message::where('area_id', $this->areaId)->where('msgno', $current->replynext_msgno)->first();
        if ($sibling) {
            $this->messageId    = $sibling->id;
            $this->scrollOffset = 0;
        }
    }

    /** @return array<int, string> 25 HTML line strings */
    public function currentScreen(): array
    {
        return match ($this->screen) {
            'areas'    => $this->areasScreen(),
            'messages' => $this->messagesScreen(),
            'reader'   => $this->readerScreen(),
            'editor'   => $this->editorScreen(),
        };
    }

    // ── Helpers ──────────────────────────────────────────────

    /**
     * Build one line from segments. Pads to exactly $width chars with spaces.
     *
     * @param  array<int, array{0: string, 1: string}>  $segments  [[text, class], ...]
     */
    private function ln(array $segments, int $width = 80): string
    {
        $html = '';
        $len = 0;

        foreach ($segments as [$text, $class]) {
            $html .= '<span class="' . $class . '">' . e($text) . '</span>';
            $len += mb_strlen($text);
        }

        if ($len < $width) {
            $html .= str_repeat(' ', $width - $len);
        }

        return $html;
    }

    /**
     * Bordered content row: │ [content segments] │
     * Content is padded to exactly 78 chars between the two border chars.
     *
     * @param  array<int, array{0: string, 1: string}>  $segments
     */
    private function row(array $segments, string $fillClass = 'cga-black-lgrey'): string
    {
        $b = 'cga-yellow-lgrey';
        $html = '<span class="' . $b . '">│</span>';
        $len = 0;

        foreach ($segments as [$text, $class]) {
            $html .= '<span class="' . $class . '">' . e($text) . '</span>';
            $len += mb_strlen($text);
        }

        if ($len < 78) {
            $html .= '<span class="' . $fillClass . '">' . str_repeat(' ', 78 - $len) . '</span>';
        }

        $html .= '<span class="' . $b . '">│</span>';
        return $html;
    }

    /** Separator row: ├────...────┤ */
    private function sep(string $fillClass = 'cga-yellow-lgrey'): string
    {
        return $this->ln([['├' . str_repeat('─', 78) . '┤', $fillClass]]);
    }

    /** Bottom border: └────...────┘ */
    private function bottom(): string
    {
        return $this->ln([['└' . str_repeat('─', 78) . '┘', 'cga-yellow-lgrey']]);
    }

    /**
     * Top border. $brackets=true → ─[title] (area list style).
     *             $brackets=false → ─ title ─ (message list / reader style).
     * title/info in RED, dashes/brackets in YELLOW.
     */
    private function top(string $title, string $section, string $info, bool $brackets = true): string
    {
        $y = 'cga-yellow-lgrey';
        $r = 'cga-red-lgrey';

        if ($brackets) {
            $inner         = '─[' . $title . ']';
            $titleSegments = [['─[', $y], [$title, $r], [']', $y]];
        } else {
            $inner         = '─ ' . $title . ' ─';
            $titleSegments = [['─ ', $y], [$title, $r], [' ─', $y]];
        }

        $mid   = ' ' . $section . ' ';
        $right = ' ' . $info . ' ─';

        $dashSpace = 78
            - mb_strlen($inner)
            - mb_strlen($mid)
            - mb_strlen($right);

        $dashSpace   = max(0, $dashSpace);
        $leftDashes  = intdiv($dashSpace, 2);
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

    /** Status bar (row 24): WHITE@BLUE, centred context string */
    private function status(string $left, string $center, string $right): string
    {
        $l = ' ' . $left;
        $r = $right . ' ';
        $gap     = max(0, 80 - mb_strlen($l) - mb_strlen($center) - mb_strlen($r));
        $padLeft = intdiv($gap, 2);

        return $l
            . str_repeat(' ', $padLeft)
            . $center
            . str_repeat(' ', $gap - $padLeft)
            . $r;
    }

    // ── Screens ──────────────────────────────────────────────

    /** @return array<int, string> */
    private function areasScreen(): array
    {
        // Bordered column layout (78 content chars between │ borders):
        // num(3) ind(1) sp(1) desc(42) msgs(6) sp(1) new(5) sp(1) echoid(16) sp(1) grp(1) = 78
        $y   = 'cga-yellow-lgrey';
        $b   = 'cga-blue-lgrey';
        $n   = 'cga-black-lgrey';
        $s   = 'cga-white-blue';
        $sep = 'cga-blue-lgrey';
        $hdr = 'cga-yellow-blue';

        $rows = [];

        // Row 0: header bar (yellow on blue, no border)
        $rows[] = $this->ln([['>>Pick New Area:', $hdr]]);

        // Row 1: top border with embedded column labels
        // 78 content chars: ─Area─Description─(29 dashes)Msgs──(─)New──(─)EchoID─────────Grp─
        // Positions: 0-46=47, 47-52=6, 53=1, 54-58=5, 59=1, 60-77=18
        $topContent = '─Area─Description─'            // 18 chars, pos 0–17
            . str_repeat('─', 29)                     // 29 dashes, pos 18–46
            . 'Msgs──'                                 // 6 chars,  pos 47–52
            . '─'                                     // 1 char,   pos 53
            . 'New──'                                  // 5 chars,  pos 54–58
            . '─'                                     // 1 char,   pos 59
            . 'EchoID─────────Grp';                   // 18 chars, pos 60–77
        $rows[] = $this->ln([['┌'.$topContent.'┐', $y]]);

        // Rows 2–22: content area (21 rows max, then bottom border on row 23)
        $displayList = $this->buildDisplayList();

        // topOffset is a display-list position — start rendering directly from it.
        $contentRows = 21;
        $emitted     = 0;

        for ($pos = $this->topOffset; $pos < count($displayList) && $emitted < $contentRows; $pos++) {
            $item = $displayList[$pos];

            if ($item['type'] === 'sep') {
                $label   = $item['label'];
                $dashes  = max(0, 78 - 2 - mb_strlen($label));
                $left    = intdiv($dashes, 2);
                $right   = $dashes - $left;
                $content = str_repeat('─', $left).' '.$label.' '.str_repeat('─', max(0, $right - 2));
                $rows[]  = $this->row([[mb_str_pad($content, 78), $sep]]);
            } else {
                $area      = $item['area'];
                $absIndex  = $item['index'];
                $selected  = $absIndex === $this->selectionIndex;
                $hasUnread = ($area->unread_count ?? 0) > 0;
                $c         = $selected ? $s : ($hasUnread ? $b : $n);

                $num  = str_pad((string) ($absIndex + 1), 3, ' ', STR_PAD_LEFT);
                $ind  = $hasUnread ? '>' : ' ';
                $desc = mb_str_pad(mb_substr($area->name, 0, 42), 42);
                $msgs = $area->message_count !== null
                    ? str_pad((string) $area->message_count, 6, ' ', STR_PAD_LEFT)
                    : '     -';
                $new  = $area->message_count !== null
                    ? str_pad((string) ($area->unread_count ?? 0), 5, ' ', STR_PAD_LEFT)
                    : '    -';
                $echo = mb_str_pad(mb_substr((string) ($area->echoid ?? ''), 0, 16), 16);
                $grp  = mb_substr((string) ($area->group_id ?? ' '), 0, 1);

                $rows[] = $this->row([
                    [$num,  $c],
                    [$ind,  $c],
                    [' ',   $c],
                    [$desc, $c],
                    [$msgs, $c],
                    [' ',   $c],
                    [$new,  $c],
                    [' ',   $c],
                    [$echo, $c],
                    [' ',   $c],
                    [$grp,  $c],
                ], $c);
            }

            $emitted++;
        }

        // Pad remaining rows up to $contentRows
        for ($i = $emitted; $i < $contentRows; $i++) {
            $rows[] = $this->row([], $n);
        }

        // Row 23: bottom border
        $rows[] = $this->bottom();

        // Row 24: status bar
        $area       = $this->areas->get($this->selectionIndex);
        $areaLabel  = $area?->echoid ?? ($area?->name ?? '');
        $areaMsgs   = $area?->message_count ?? 0;
        $areaUnread = $area?->unread_count ?? 0;
        $rows[]     = $this->status(
            config('golded.version', 'GoldED'),
            "{$areaLabel}: {$areaMsgs} msgs, {$areaUnread} unread, 0 personal",
            date('H:i:s')
        );

        return $rows;
    }

    /** @return array<int, string> */
    private function messagesScreen(): array
    {
        $y = 'cga-yellow-lgrey';
        $b = 'cga-blue-lgrey';
        $n = 'cga-black-lgrey';
        $s = 'cga-white-blue';

        $rows = [];

        $messages = $this->messages;
        $total    = $messages->count();
        $area     = $this->areaId ? \App\Models\Area::find($this->areaId) : null;
        $areaName = $area?->name ?? 'Messages';
        $unread   = $messages->where('is_read', false)->count();
        $summary  = $unread > 0 ? "{$total} msgs, {$unread} new" : "{$total} msgs";

        $rows[] = $this->top($areaName, 'Message List', $summary, false);

        // Column header — must match data column widths below
        // msgno(6) sp(1) thread(8) bk(1) mk(1) from(21) sp(1) subj(30) date(9) = 78
        $rows[] = $this->row([
            ['     #', $b],                         // 6
            [' ', $b],                              // 1
            ['        ', $b],                       // 8 thread
            [' ', $b],                              // 1 bookmark
            [' ', $b],                              // 1 mark
            ['From                 ', $b],          // 21
            [' ', $b],                              // 1
            ['Subject                       ', $b], // 30
            ['Date     ', $b],                      // 9
        ]);

        $rows[] = $this->sep();

        $tree    = (new ThreadTree)->build($messages);
        $visible = $messages->slice($this->topOffset, 20);

        foreach ($visible->values() as $i => $msg) {
            $absIndex = $this->topOffset + $i;
            $selected = $absIndex === $this->selectionIndex;
            $c        = $selected ? $s : ($msg->is_read ? $n : $b);

            $num    = str_pad((string) ($absIndex + 1), 6, ' ', STR_PAD_LEFT);  // 6
            $thread = $tree[$msg->id] ?? str_repeat(' ', 8);                     // 8
            $bk     = $msg->is_bookmarked ? '►' : ' ';                           // 1
            $mk     = $msg->is_marked ? '■' : ' ';                               // 1
            $from   = mb_str_pad(mb_substr($msg->from_name, 0, 21), 21);         // 21
            $subj   = mb_str_pad(mb_substr($msg->subject, 0, 30), 30);           // 30
            $date   = $msg->posted_at
                ? $msg->posted_at->format('d M y')
                : str_repeat(' ', 9);                                             // 9

            $rows[] = $this->row([
                [$num, $c],    // 6
                [' ', $c],     // 1
                [$thread, $c], // 8
                [$bk, $c],     // 1
                [$mk, $c],     // 1
                [$from, $c],   // 21
                [' ', $c],     // 1
                [$subj, $c],   // 30
                [$date, $c],   // 9
            ], $c);
        }

        $dataRows = max(0, 20 - $visible->count());
        for ($i = 0; $i < $dataRows; $i++) {
            $rows[] = $this->row([], $n);
        }

        $rows[] = $this->bottom();
        $rows[] = $this->status(config('golded.version', 'GoldED'), 'Msg ' . ($this->selectionIndex + 1) . " of {$total}", '13:45:22');

        return $rows;
    }

    /** @return array<int, string> */
    private function readerScreen(): array
    {
        $y    = 'cga-yellow-lgrey';
        $dg   = 'cga-dgrey-lgrey';
        $b    = 'cga-blue-lgrey';
        $n    = 'cga-black-lgrey';
        $q1   = 'cga-blue-lgrey';
        $tear = 'cga-lblue-lgrey';
        $orig = 'cga-lblue-lgrey';

        $rows = [];

        $msg      = $this->currentMessage;
        $messages = $this->messages;
        $total    = $messages->count();
        $pos      = $messages->search(fn ($m) => $m->id === $this->messageId);
        $msgno    = $pos !== false ? $pos + 1 : 1;
        $area     = $this->areaId ? \App\Models\Area::find($this->areaId) : null;
        $areaName = $area?->name ?? '';
        $echoid   = $area?->echoid ?? '';

        $rows[] = $this->top("[{$msgno}] {$areaName}", $msg?->from_address ?? '', $echoid, false);

        $dateStr = $msg?->posted_at ? $msg->posted_at->format('d M y') : '';
        $rows[]  = $this->row([
            [" Msg: {$msgno} of {$total}", $b],
            [str_repeat(' ', max(0, 54 - mb_strlen(" Msg: {$msgno} of {$total}"))) . $dateStr . ' ', $b],
        ]);

        $rows[] = $this->row([[' From: ', $b], [str_pad(mb_substr($msg?->from_name ?? '', 0, 62), 62) . ' ', $n]]);
        $rows[] = $this->row([[' To  : ', $b], [str_pad(mb_substr($msg?->to_name ?? '', 0, 62), 62) . ' ', $n]]);
        $rows[] = $this->row([[' Subj: ', $b], [str_pad(mb_substr($msg?->subject ?? '', 0, 62), 62) . ' ', $n]]);

        $rows[] = $this->sep();

        // Render body lines with scroll offset (18 visible lines).
        // Word-wrap at 76 chars so content stays within the 78-char content area.
        $rawLines = $msg ? explode("\n", str_replace("\r", '', $msg->body_text)) : [];
        $bodyLines = [];
        $classifier = new LineClassifier;
        foreach ($rawLines as $line) {
            if (! $this->showKludges && $classifier->classify($line) === LineType::Kludge) {
                continue;
            }
            $wrapped = explode("\n", wordwrap($line, 76, "\n", true));
            foreach ($wrapped as $wl) {
                $bodyLines[] = $wl;
            }
        }
        $visible = array_slice($bodyLines, $this->scrollOffset, 18);

        foreach ($visible as $line) {
            $type  = $classifier->classify($line);
            $class = match ($type) {
                LineType::Kludge   => $dg,
                LineType::Tearline => $tear,
                LineType::Origin   => $orig,
                LineType::Quote1   => $q1,
                LineType::Quote2   => $b,
                LineType::Normal   => $n,
            };
            // Render \x01 as '@' to match original GoldED display
            $display = $type === LineType::Kludge
                ? str_replace("\x01", '@', $line)
                : $line;
            $rows[] = $this->row([[' ' . $display, $class]]);
        }

        for ($i = count($visible); $i < 18; $i++) {
            $rows[] = $this->row([['', $n]]);
        }

        $rows[] = $this->bottom();
        $rows[] = $this->status(config('golded.version', 'GoldED'), "Msg {$msgno} of {$total}", '13:45:22');

        return $rows;
    }

    /** @return array<int, string> */
    private function editorScreen(): array
    {
        $y  = 'cga-yellow-lgrey';
        $r  = 'cga-red-lgrey';
        $b  = 'cga-blue-lgrey';
        $n  = 'cga-black-lgrey';
        $q1 = 'cga-blue-lgrey';

        $rows = [];

        // Row 0: top border — same yellow-lgrey style as other screens, no right section
        $rows[] = $this->ln([
            ['┌─ ', $y],
            ['Composing new message', $r],
            [' ' . str_repeat('─', 78 - 3 - 21 - 1) . '─┐', $y],
        ]);

        // Row 1: date only (no Msg counter for new compose); right-aligned like reader
        $rows[] = $this->row([[str_repeat(' ', 68) . '14 Mar 94 ', $b]]);
        // Rows 2-4: editable fields (gehdre.cpp rows 2,3,4 — no Area field in original)
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
        $rows[] = $this->status(config('golded.version', 'GoldED'), '[INS] Line 7, Col 1  F2=Save  Esc=Abort', '13:45:22');

        return $rows;
    }
}
?>

<div
    x-data
    @keydown.window="$wire.handleKey(($event.altKey ? 'Alt+' : '') + $event.key)"
    class="golded-shell"
    tabindex="-1"
>
    <pre class="golded-pre">@foreach ($this->currentScreen() as $i => $line)<span class="{{ $i === 24 ? 'golded-row-status' : 'golded-row' }}">{!! $line !!}</span>@endforeach</pre>

</div>
