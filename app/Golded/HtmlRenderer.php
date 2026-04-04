<?php

namespace App\Golded;

class HtmlRenderer
{
    /**
     * Convert a Segment[][] screen into an array of HTML line strings.
     *
     * Each row in $screen is an array of [text, cgaClass] tuples.
     * Output: one HTML string per row, segments wrapped in <span class="..."> tags,
     * text HTML-escaped, row padded to $cols visible characters.
     *
     * @param  array<int, array<int, array{0: string, 1: string}>>  $screen
     * @return array<int, string>
     */
    public function renderScreen(array $screen, int $cols): array
    {
        $lines = [];

        foreach ($screen as $row) {
            $html = '';
            $visible = 0;

            foreach ($row as [$text, $class]) {
                $html .= '<span class="'.$class.'">'.e($text).'</span>';
                $visible += mb_strlen($text);
            }

            if ($visible < $cols) {
                $html .= str_repeat(' ', $cols - $visible);
            }

            $lines[] = $html;
        }

        return $lines;
    }
}
