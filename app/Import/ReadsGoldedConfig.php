<?php

namespace App\Import;

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
}
