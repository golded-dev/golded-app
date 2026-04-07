<?php

namespace App\Import;

use App\Models\Area;

trait ReadsGoldedConfig
{
    /**
     * Return the charset fallback for an area from config/golded.php.
     * Falls back to the global charset_import, then to CP850.
     */
    private function areaFallbackCharset(string $areaCode): string
    {
        $global = config('golded.charset_import', 'CP850');

        return config("golded.areas.{$areaCode}.charset_import", $global);
    }

    /**
     * Look up the AREADEF entry for the given path (or "hudson:<board>") and apply
     * echoid, description, group_id, and area_type to the area if found.
     */
    private function applyAreaDefMeta(Area $area, string $path): void
    {
        $key = rtrim($path, '/\\');

        // Config keys with dots need array-style lookup to avoid dot-notation traversal
        $all = config('golded.areas', []);
        $def = $all[$key] ?? null;

        if (! $def) {
            return;
        }

        $updates = [];

        if (! empty($def['echoid'])) {
            $updates['echoid'] = $def['echoid'];
        }

        if (! empty($def['description'])) {
            $updates['name'] = $def['description'];
        }

        if (! empty($def['group_id'])) {
            $updates['group_id'] = $def['group_id'];
        }

        if (! empty($def['area_type'])) {
            $updates['area_type'] = $def['area_type'];
        }

        if (! empty($updates)) {
            $area->update($updates);
        }
    }

    /**
     * Generate a deterministic fallback ID for messages with no MSGID kludge.
     * Inputs must be stable across re-imports (same encoding, same truncation).
     */
    private function syntheticId(string $from, string $to, string $subj, ?string $date, string $body): string
    {
        return 'hash:'.md5("{$from}\x00{$to}\x00{$subj}\x00{$date}\x00".substr($body, 0, 200));
    }

    /** Strip trailing nulls and normalise line endings to \n. */
    private function parseBody(string $raw): string
    {
        $raw = rtrim($raw, "\x00");
        $raw = str_replace(["\r\n", "\r"], ["\n", "\n"], $raw);

        return $raw;
    }

    /** Convert a string from the given charset to UTF-8, stripping trailing nulls. */
    private function toUtf8(string $str, string $charset = 'CP850'): string
    {
        return mb_convert_encoding(rtrim($str, "\x00"), 'UTF-8', $charset);
    }

    /** Find a file case-insensitively by extension (checks lower then upper). */
    private function findFile(string $basePath, string $ext): ?string
    {
        foreach ([$ext, strtoupper($ext)] as $e) {
            if (file_exists("{$basePath}.{$e}")) {
                return "{$basePath}.{$e}";
            }
        }

        return null;
    }
}
