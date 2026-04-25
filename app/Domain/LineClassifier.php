<?php

declare(strict_types=1);

namespace App\Domain;

enum LineType
{
    case Normal;
    case Quote1;
    case Quote2;
    case Tearline;
    case Origin;
    case Kludge;
}

class LineClassifier
{
    public function classify(string $line): LineType
    {
        if (str_starts_with($line, "\x01")) {
            return LineType::Kludge;
        }

        if (str_starts_with($line, '--- ') || $line === '---') {
            return LineType::Tearline;
        }

        if (str_starts_with($line, ' * Origin:')) {
            return LineType::Origin;
        }

        $depth = $this->quoteDepth($line);
        if ($depth > 0) {
            return $depth % 2 === 1 ? LineType::Quote1 : LineType::Quote2;
        }

        return LineType::Normal;
    }

    private function quoteDepth(string $line): int
    {
        $depth = 0;
        $i = 0;
        $len = strlen($line);

        while ($i < $len) {
            // Skip leading spaces between markers
            while ($i < $len && $line[$i] === ' ') {
                $i++;
            }

            if ($i < $len && (in_array($line[$i], ['>', '|', ':'], true))) {
                $depth++;
                $i++;
            } else {
                break;
            }
        }

        return $depth;
    }
}
