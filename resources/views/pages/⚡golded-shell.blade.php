<?php

use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts::terminal')] #[Title('GoldED 7')] class extends Component
{
    public string $screen = 'areas';

    public function handleKey(string $key): void
    {
        match ($key) {
            '1' => $this->screen = 'areas',
            '2' => $this->screen = 'messages',
            '3' => $this->screen = 'reader',
            '4' => $this->screen = 'editor',
            default => null,
        };
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

        $rows[] = $this->top('GoldED 3.0.1', 'Area List', '3 areas, 17 new');

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

        // Area entries  [num+mark, desc(pad to 30), msgs(7), chg(1), new(7), sp(1), echo(16), grp(3)]
        $areas = [
            ['  1 ', 'Goldware Support              ', '   142', ' ', '    12', ' ', 'GOLDED          ', '   ', false],
            ['  2 ', 'FidoNet.General               ', '    89', ' ', '     5', ' ', 'FIDONET         ', '   ', false],
            ['► 3 ', 'NetMail                       ', '    12', ' ', '     2', ' ', 'NETMAIL         ', '   ', true],
            ['  4 ', 'DK.Snak                       ', '    67', ' ', '     0', ' ', 'DK.SNAK         ', '   ', false],
            ['  5 ', 'OS2.General                   ', '    34', ' ', '     3', ' ', 'OS2.GEN         ', '   ', false],
            ['  6 ', 'THE_SAFE                      ', '     8', ' ', '     0', ' ', 'THE_SAFE        ', '   ', false],
        ];

        foreach ($areas as [$num, $desc, $msgs, $chg, $new, $sp, $echo, $grp, $selected]) {
            $c = $selected ? $s : $n;
            $rows[] = $this->row([
                [$num, $c], [$desc, $c], [$msgs, $c], [$chg, $c],
                [$new, $c], [$sp, $c], [$echo, $c], [$grp, $c],
            ], $c);
        }

        for ($i = 9; $i <= 22; $i++) {
            $rows[] = $this->row([], $n);
        }

        $rows[] = $this->bottom();
        $rows[] = $this->status('GoldED 3.0.1', 'Area 3 of 6', '13:45:22');

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

        $rows[] = $this->top('NetMail', 'Message List', '12 msgs, 2 new', false);

        $rows[] = $this->row([
            ['     #', $b],
            ['   ', $b],
            ['  From                ', $b],
            ['  Subject                       ', $b],
            ['  Date', $b],
        ]);

        $rows[] = $this->sep();

        // [num(6), tree(3), from(22), subj(32), date(11)]
        $msgs = [
            ['     1', '   ', '  Bjarne Hansen       ', '  Re: GoldED 3.0 beta           ', '  12 Mar 94', false, false],
            ['     2', '   ', '  Uffe Sorensen       ', '  Nodelist update               ', '  12 Mar 94', true,  false],
            ['  ►  3', '   ', '  Odinn Sorensen      ', '  Re: GoldED keybindings        ', '  13 Mar 94', false, true],
            ['     4', ' ├─', '  Lars Jensen         ', '  Re: GoldED keybindings        ', '  13 Mar 94', false, false],
            ['     5', ' └─', '  Peter Froerup       ', '  Re: GoldED keybindings        ', '  14 Mar 94', false, false],
            ['     6', '   ', '  Thomas Nielsen      ', '  New beta available?           ', '  14 Mar 94', true,  false],
        ];

        foreach ($msgs as [$num, $tree, $from, $subj, $date, $isUnread, $isSel]) {
            $c = $isSel ? $s : ($isUnread ? $b : $n);
            $rows[] = $this->row([
                [$num, $c], [$tree, $c], [$from, $c], [$subj, $c], [$date, $c],
            ], $c);
        }

        for ($i = 9; $i <= 22; $i++) {
            $rows[] = $this->row([], $n);
        }

        $rows[] = $this->bottom();
        $rows[] = $this->status('GoldED 3.0.1', 'Msg 3 of 12', '13:45:22');

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

        $rows[] = $this->top('[3] NetMail', '2:236/77', 'NETMAIL', false);

        $rows[] = $this->row([
            [' Msg: 3 of 12  -1 +4 *5', $b],
            ['                                      13 Mar 94 ', $b],
        ]);

        $rows[] = $this->row([[' From: ', $b], ['Odinn Sorensen                         2:236/77     ', $n]]);
        $rows[] = $this->row([[' To  : ', $b], ['Lars Jensen                            2:236/105    ', $n]]);
        $rows[] = $this->row([[' Subj: ', $b], ['Re: GoldED keybindings                               ', $n]]);

        $rows[] = $this->sep();

        $body = [
            [' ', $n],
            [' Lars Jensen wrote:', $n],
            [' ', $n],
            [' > I noticed the key binding for AREA select seems odd —', $q1],
            [' > pressing Right should open the area but it doesn\'t?', $q1],
            [' ', $n],
            [' Right/Enter both work for AREAselect. Check your GOLDKEYS.CFG.', $n],
            [' The default binding has both mapped:', $n],
            [' ', $n],
            ['   Right  → AREAselect', $n],
            ['   Enter  → AREAselect', $n],
            [' ', $n],
            [' Let me know if you\'re still stuck.', $n],
            [' ', $n],
            [' --- GoldED 3.0.1 Beta 3', $tear],
            ['  * Origin: Goldware BBS, Haslev (2:236/77)', $orig],
            [' ', $n],
            [' ', $n],
        ];

        foreach ($body as [$text, $class]) {
            $rows[] = $this->row([[$text, $class]]);
        }

        $rows[] = $this->bottom();
        $rows[] = $this->status('GoldED 3.0.1', 'Msg 3 of 12 (2 new)', '13:45:22');

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
    @keydown.window="$wire.handleKey($event.key)"
    class="golded-shell"
    tabindex="-1"
>
    <pre class="golded-pre">@foreach ($this->currentScreen() as $i => $line)<span class="{{ $i === 24 ? 'golded-row-status' : 'golded-row' }}">{!! $line !!}</span>@endforeach</pre>
    <div class="golded-hint">Keys 1–4 switch screens</div>
</div>
