<?php

namespace App\Domain;

class CharsetDetector
{
    /**
     * Map of FidoNet CHRS charset names → PHP mbstring encoding names.
     * Only the first token of the CHRS value is matched (e.g. "LATIN-1" from "LATIN-1 2").
     */
    private const MAP = [
        // CP850 variants
        'CP850' => 'CP850',
        'IBM850' => 'CP850',
        'IBMPC' => 'CP850',
        'IBM' => 'CP850',

        // ISO-8859-1 variants
        'LATIN-1' => 'ISO-8859-1',
        'LATIN1' => 'ISO-8859-1',
        '8859-1' => 'ISO-8859-1',
        'ISO-8859-1' => 'ISO-8859-1',
        'ISO8859-1' => 'ISO-8859-1',

        // ASCII
        'ASCII' => 'ASCII',
        'USASCII' => 'ASCII',

        // Russian
        'CP866' => 'CP866',
        'IBM866' => 'CP866',
        'KOI8-R' => 'KOI8-R',
        'KOI8R' => 'KOI8-R',

        // Other CP variants pass through if mbstring knows them
        'CP437' => 'CP437',
        'IBM437' => 'CP437',
        'CP1251' => 'CP1251',
        'CP1252' => 'CP1252',
        'CP1250' => 'CP1250',

        // Latin-2
        'LATIN-2' => 'ISO-8859-2',
        'ISO-8859-2' => 'ISO-8859-2',
    ];

    /**
     * Detect the PHP charset name from a raw FidoNet message body.
     * Scans for the first \x01CHRS: or \x01CHARSET: kludge line.
     * Returns 'CP850' if none found or the charset name is unrecognised.
     */
    public static function detect(string $rawBody, string $fallback = 'CP850'): string
    {
        if (preg_match('/\x01(?:CHRS|CHARSET):\s*(\S+)/i', $rawBody, $m)) {
            $name = strtoupper($m[1]);

            return self::MAP[$name] ?? $fallback;
        }

        return $fallback;
    }
}
