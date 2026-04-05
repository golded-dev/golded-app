<?php

namespace App\Golded;

class AnsiRenderer
{
    /**
     * Full CGA → ANSI escape code map.
     *
     * Format: fg;bg (foreground;background SGR codes)
     * Light grey background = 47, Blue background = 44
     *
     * @var array<string, string>
     */
    private const CGA_MAP = [
        'cga-black-lgrey' => '30;47',  // black on light grey
        'cga-blue-lgrey' => '34;47',  // blue on light grey
        'cga-green-lgrey' => '32;47',  // green on light grey
        'cga-cyan-lgrey' => '36;47',  // cyan on light grey
        'cga-red-lgrey' => '31;47',  // red on light grey
        'cga-magenta-lgrey' => '35;47', // magenta on light grey
        'cga-brown-lgrey' => '33;47',  // brown/dark yellow on light grey
        'cga-lgrey-lgrey' => '37;47',  // light grey on light grey
        'cga-dgrey-lgrey' => '90;47',  // dark grey on light grey
        'cga-lblue-lgrey' => '94;47',  // light blue on light grey
        'cga-lgreen-lgrey' => '92;47',  // light green on light grey
        'cga-lcyan-lgrey' => '96;47',  // light cyan on light grey
        'cga-lred-lgrey' => '91;47',  // light red on light grey
        'cga-lmagenta-lgrey' => '95;47', // light magenta on light grey
        'cga-yellow-lgrey' => '93;47',  // yellow on light grey
        'cga-white-lgrey' => '97;47',  // white on light grey
        // Blue background variants
        'cga-black-blue' => '30;44',
        'cga-blue-blue' => '34;44',
        'cga-white-blue' => '97;44',  // white on blue (status bar)
        'cga-yellow-blue' => '93;44',  // yellow on blue (header bar)
        'cga-lblue-blue' => '94;44',
        'cga-lgreen-blue' => '92;44',
        'cga-lcyan-blue' => '96;44',
    ];

    /**
     * Render a Segment[][] screen as a single ANSI escape string.
     *
     * Output: \033[H (cursor home) + rows joined by \n, each segment
     * wrapped in its ANSI color code and reset, written atomically.
     *
     * @param  array<int, array<int, array{0: string, 1: string}>>  $screen
     */
    public function renderScreen(array $screen, int $cols, int $rows): string
    {
        $output = "\033[H"; // cursor home

        foreach ($screen as $i => $row) {
            // Absolute row positioning avoids wrap + \n = blank line in raw mode.
            $output .= sprintf("\033[%d;1H", $i + 1);

            foreach ($row as [$text, $class]) {
                $ansi = self::CGA_MAP[$class] ?? null;
                $output .= $ansi !== null
                    ? "\033[{$ansi}m{$text}\033[0m"
                    : "{$text}\033[0m";
            }
        }

        return $output;
    }
}
