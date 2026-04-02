<?php

namespace App\Config;

class GoldedConfigParser
{
    /**
     * Parse a GOLDED.CFG file (and any INCLUDEd files) into a config array.
     *
     * Handles:
     * - ; and REM comments
     * - IF 1 / IF 0 / IF <platform> conditional blocks (IF 0 and platform guards skipped)
     * - INCLUDE <filename> recursion
     *
     * @return array{
     *     username: string|null,
     *     address: string|null,
     *     charset_import: string,
     *     origins: string[],
     *     tearline: string|null,
     *     taglines: string[],
     * }
     */
    public function parse(string $cfgPath): array
    {
        $result = [
            'username' => null,
            'address' => null,
            'charset_import' => 'CP850',
            'origins' => [],
            'tearline' => null,
            'taglines' => [],
        ];

        $this->parseFile($cfgPath, $result);

        return $result;
    }

    private function parseFile(string $path, array &$result): void
    {
        if (! file_exists($path)) {
            return;
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES);
        $this->processLines($lines, 0, count($lines) - 1, dirname($path), $result);
    }

    /**
     * Process lines from $start to $end (inclusive), respecting IF/ELSE/ENDIF.
     * Returns the index of the line after the last consumed line.
     */
    private function processLines(array $lines, int $start, int $end, string $baseDir, array &$result): int
    {
        $i = $start;

        while ($i <= $end) {
            $raw = $lines[$i];
            $line = trim($raw);
            $i++;

            // Skip blank lines and comments
            if ($line === '' || str_starts_with($line, ';') || preg_match('/^rem\b/i', $line)) {
                continue;
            }

            // IF / ELSE / ENDIF
            if (preg_match('/^IF\s+(\S+)/i', $line, $m)) {
                $cond = strtoupper($m[1]);
                $active = match ($cond) {
                    '1' => true,
                    '0' => false,
                    default => false, // LINUX, OS2, INOS2, etc. — not our platform
                };

                // Find matching ELSE and ENDIF, respecting nesting
                [$elseIdx, $endifIdx] = $this->findIfBounds($lines, $i, $end);

                if ($active) {
                    // Parse IF branch, skip ELSE branch
                    $this->processLines($lines, $i, $elseIdx !== null ? $elseIdx - 1 : $endifIdx - 1, $baseDir, $result);
                } else {
                    // Skip IF branch, parse ELSE branch if present
                    if ($elseIdx !== null) {
                        $this->processLines($lines, $elseIdx + 1, $endifIdx - 1, $baseDir, $result);
                    }
                }

                $i = $endifIdx + 1;

                continue;
            }

            if (preg_match('/^ELSE\b/i', $line) || preg_match('/^ENDIF\b/i', $line)) {
                // Should be consumed by the IF handler above, but guard just in case
                continue;
            }

            // INCLUDE <filename>
            if (preg_match('/^INCLUDE\s+(.+)$/i', $line, $m)) {
                $included = $baseDir.DIRECTORY_SEPARATOR.trim($m[1]);
                $this->parseFile($included, $result);

                continue;
            }

            // Extract keywords
            $this->extractKeyword($line, $result);
        }

        return $i;
    }

    /**
     * Find the ELSE (if any) and ENDIF indices for an IF block starting at $start.
     * Handles nesting.
     *
     * @return array{int|null, int} [elseIdx|null, endifIdx]
     */
    private function findIfBounds(array $lines, int $start, int $end): array
    {
        $depth = 1;
        $elseIdx = null;
        $endifIdx = $end;

        for ($i = $start; $i <= $end; $i++) {
            $line = trim($lines[$i]);

            if (preg_match('/^IF\b/i', $line)) {
                $depth++;
            } elseif (preg_match('/^ELSE\b/i', $line) && $depth === 1) {
                $elseIdx = $i;
            } elseif (preg_match('/^ENDIF\b/i', $line)) {
                $depth--;
                if ($depth === 0) {
                    $endifIdx = $i;
                    break;
                }
            }
        }

        return [$elseIdx, $endifIdx];
    }

    private function extractKeyword(string $line, array &$result): void
    {
        if (! preg_match('/^(\w+)\s*(.*?)$/i', $line, $m)) {
            return;
        }

        $keyword = strtoupper($m[1]);
        $value = trim($m[2]);

        // Strip surrounding quotes
        if (str_starts_with($value, '"') && str_ends_with($value, '"')) {
            $value = substr($value, 1, -1);
        }

        switch ($keyword) {
            case 'USERNAME':
                // Take the first non-empty username
                if ($result['username'] === null && $value !== '') {
                    $result['username'] = $value;
                }
                break;

            case 'ADDRESS':
                if ($result['address'] === null && $value !== '') {
                    $result['address'] = $value;
                }
                break;

            case 'XLATIMPORT':
                // Map FidoNet charset name to PHP mbstring name
                if ($value !== '') {
                    $result['charset_import'] = $this->mapCharset($value);
                }
                break;

            case 'ORIGIN':
                if ($value !== '') {
                    $result['origins'][] = $value;
                }
                break;

            case 'TEARLINE':
                if ($value !== '') {
                    $result['tearline'] = $value;
                }
                break;

            case 'TAGLINE':
                if ($value !== '') {
                    $result['taglines'][] = $value;
                }
                break;
        }
    }

    private function mapCharset(string $name): string
    {
        return match (strtoupper($name)) {
            'IBMPC', 'IBM', 'CP850', 'IBM850' => 'CP850',
            'LATIN-1', 'LATIN1', 'ISO-8859-1' => 'ISO-8859-1',
            'LATIN-2', 'ISO-8859-2' => 'ISO-8859-2',
            'CP866', 'IBM866' => 'CP866',
            'KOI8-R', 'KOI8R' => 'KOI8-R',
            'ASCII', 'USASCII' => 'ASCII',
            default => 'CP850',
        };
    }
}
