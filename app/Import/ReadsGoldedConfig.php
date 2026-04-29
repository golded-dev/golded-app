<?php

declare(strict_types=1);

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
     * Look up the AREADEF entry for the given path and apply source metadata when found.
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
            $updates['source_group_code'] = $def['group_id'];
        }

        if (! empty($def['area_type'])) {
            $updates['area_type'] = $def['area_type'];
        }

        if ($updates !== []) {
            $area->update($updates);
        }
    }
}
