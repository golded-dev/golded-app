<?php

use App\Domain\ThreadTree;
use App\Models\Area;
use App\Models\Dataset;
use App\Models\Message;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts::terminal')] #[Title('GoldED 7')] class extends Component
{
    public string $screen = 'areas';
    public ?int $datasetId = null;
    public ?int $areaId = null;
    public ?int $messageId = null;
    public int $selectionIndex = 0;
    public int $scrollOffset = 0;
    public int $topOffset = 0;

    public function mount(): void
    {
        $dataset = Dataset::withCount('messages')->orderByDesc('messages_count')->first();
        if ($dataset) {
            $this->datasetId = $dataset->id;
        }
    }

    #[Computed]
    public function areas(): \Illuminate\Database\Eloquent\Collection
    {
        return Area::where('dataset_id', $this->datasetId ?? 0)
            ->orderByRaw('CASE WHEN unread_count > 0 THEN 0 ELSE 1 END') // Y: unread first
            ->orderByDesc('unread_count')                                  // U: most unread first
            ->orderBy('echoid')                                             // E: echoid alphabetical
            ->get();
    }

    #[Computed]
    public function messages(): \Illuminate\Database\Eloquent\Collection
    {
        return Message::where('area_id', $this->areaId ?? 0)->orderBy('msgno')->get();
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
            'ArrowDown' => $this->selectionIndex = min($this->selectionIndex + 1, max(0, $count - 1)),
            'ArrowUp'   => $this->selectionIndex = max(0, $this->selectionIndex - 1),
            'ArrowRight', 'Enter' => $this->openArea(),
            default => null,
        };
        $this->clampTopOffset($count);
    }

    private function handleMessagesKey(string $key): void
    {
        $count = $this->messages->count();
        match ($key) {
            'ArrowDown'  => $this->selectionIndex = min($this->selectionIndex + 1, max(0, $count - 1)),
            'ArrowUp'    => $this->selectionIndex = max(0, $this->selectionIndex - 1),
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

    private function handleReaderKey(string $key): void
    {
        match ($key) {
            'ArrowDown'               => $this->scrollOffset++,
            'ArrowUp'                 => $this->scrollOffset = max(0, $this->scrollOffset - 1),
            'ArrowRight'              => $this->nextMessage(),
            'ArrowLeft'               => $this->prevMessage(),
            'Alt+ArrowRight', 'Alt+u' => $this->nextUnreadMessage(),
            'Alt+ArrowLeft'           => $this->prevUnreadMessage(),
            'Alt+j'                   => $this->toggleReadUnread(),
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
        $gap     = 80 - mb_strlen($l) - mb_strlen($center) - mb_strlen($r);
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
        $y = 'cga-yellow-lgrey';
        $b = 'cga-blue-lgrey';
        $n = 'cga-black-lgrey';
        $s = 'cga-white-blue';

        $rows = [];

        $total   = $this->areas->count();
        $unread  = $this->areas->sum('unread_count');
        $rows[]  = $this->top('GoldED 3.0.1', 'Area List', "{$total} areas, {$unread} new");

        // Column header — columns must match data: num(4) desc(30) msgs(6) chg(1) new(6) sp(1) echo(16)
        $rows[] = $this->row([
            ['  # ', $b],                           // 4
            ['Description                   ', $b], // 30
            ['  Msgs', $b],                         // 6
            [' ', $b],                              // 1 (chg col)
            ['   New', $b],                         // 6
            [' ', $b],                              // 1 (sp col)
            ['EchoID          ', $b],               // 16
        ]);

        $rows[] = $this->sep();

        $visible = $this->areas->slice($this->topOffset, 20);

        foreach ($visible->values() as $i => $area) {
            $absIndex = $this->topOffset + $i;
            $selected = $absIndex === $this->selectionIndex;
            $hasUnread = ($area->unread_count ?? 0) > 0;
            $c        = $selected ? $s : ($hasUnread ? $b : $n);
            $num      = ($selected ? '► ' : '  ') . ($absIndex + 1) . ' ';
            $desc     = str_pad(mb_substr($area->name, 0, 28), 30);
            $msgs     = str_pad((string) ($area->message_count ?? '-'), 6, ' ', STR_PAD_LEFT);
            $new      = str_pad((string) ($area->unread_count ?? '-'), 6, ' ', STR_PAD_LEFT);
            $echo     = str_pad(mb_substr((string) ($area->echoid ?? ''), 0, 16), 16);
            $rows[]   = $this->row([
                [$num, $c], [$desc, $c], [$msgs, $c], [' ', $c],
                [$new, $c], [' ', $c], [$echo, $c], ['   ', $c],
            ], $c);
        }

        $dataRows = max(0, 20 - $visible->count());
        for ($i = 0; $i < $dataRows; $i++) {
            $rows[] = $this->row([], $n);
        }

        $rows[] = $this->bottom();
        $rows[] = $this->status('GoldED 3.0.1', 'Area ' . ($this->selectionIndex + 1) . " of {$total}", '13:45:22');

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
        // msgno(6) sp(1) bk(1) mk(1) sp(1) thread(8) from(20) sp(1) subj(30) date(9) = 78
        $rows[] = $this->row([
            ['     #', $b],   // 6
            [' ', $b],         // 1
            [' ', $b],         // 1 bookmark
            [' ', $b],         // 1 mark
            [' ', $b],         // 1
            ['        ', $b],  // 8 thread
            ['From                ', $b],  // 20
            [' ', $b],         // 1
            ['Subject                       ', $b], // 30
            ['Date     ', $b], // 9
        ]);

        $rows[] = $this->sep();

        $tree    = (new ThreadTree)->build($messages);
        $visible = $messages->slice($this->topOffset, 20);

        foreach ($visible->values() as $i => $msg) {
            $absIndex = $this->topOffset + $i;
            $selected = $absIndex === $this->selectionIndex;
            $c        = $selected ? $s : ($msg->is_read ? $n : $b);

            $num    = str_pad((string) ($absIndex + 1), 6, ' ', STR_PAD_LEFT);  // 6
            $bk     = $msg->is_bookmarked ? '►' : ' ';                           // 1
            $mk     = $msg->is_marked ? '■' : ' ';                               // 1
            $thread = $tree[$msg->id] ?? str_repeat(' ', 8);                     // 8
            $from   = mb_str_pad(mb_substr($msg->from_name, 0, 20), 20);         // 20
            $subj   = mb_str_pad(mb_substr($msg->subject, 0, 30), 30);           // 30
            $date   = $msg->posted_at
                ? mb_str_pad($msg->posted_at->format('j M y'), 9)
                : str_repeat(' ', 9);                                             // 9

            $rows[] = $this->row([
                [$num, $c],    // 6
                [' ', $c],     // 1
                [$bk, $c],     // 1
                [$mk, $c],     // 1
                [' ', $c],     // 1
                [$thread, $c], // 8
                [$from, $c],   // 20
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
        $rows[] = $this->status('GoldED 3.0.1', 'Msg ' . ($this->selectionIndex + 1) . " of {$total}", '13:45:22');

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
        foreach ($rawLines as $line) {
            $wrapped = explode("\n", wordwrap($line, 76, "\n", true));
            foreach ($wrapped as $wl) {
                $bodyLines[] = $wl;
            }
        }
        $visible = array_slice($bodyLines, $this->scrollOffset, 18);

        foreach ($visible as $line) {
            $rows[] = $this->row([[' ' . $line, $n]]);
        }

        for ($i = count($visible); $i < 18; $i++) {
            $rows[] = $this->row([['', $n]]);
        }

        $rows[] = $this->bottom();
        $rows[] = $this->status('GoldED 3.0.1', "Msg {$msgno} of {$total}", '13:45:22');

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
        $rows[] = $this->status('GoldED 3.0.1', '[INS] Line 7, Col 1  F2=Save  Esc=Abort', '13:45:22');

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
