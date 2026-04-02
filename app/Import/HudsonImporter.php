<?php

namespace App\Import;

use App\Domain\CharsetDetector;
use App\Models\Area;
use App\Models\Dataset;
use App\Models\Message;
use Carbon\Carbon;

class HudsonImporter
{
    // Hudson message base (all files in one flat directory):
    //
    // MSGIDX.BBS  — one 3-byte record per message, in order:
    //   v  msgno    (uint16, 0xFFFF = deleted)
    //   C  board    (uint8,  1-based board number)
    //
    // MSGHDR.BBS  — one 187-byte HudsHdr per message, parallel to MSGIDX:
    //   v  msgno      uint16
    //   v  replyto    uint16  (0 = none)
    //   v  reply1st   uint16  (0 = none)
    //   v  timesread  uint16
    //   v  startrec   uint16  (starting 128-byte record in MSGTXT.BBS)
    //   v  numrecs    uint16  (number of 128-byte text records)
    //   v  destnet    uint16
    //   v  destnode   uint16
    //   v  orignet    uint16
    //   v  orignode   uint16
    //   C  destzone   uint8
    //   C  origzone   uint8
    //   v  cost       uint16
    //   C  msgattr    uint8
    //   C  netattr    uint8
    //   C  board      uint8
    //   a6 time       Pascal string (length byte + "HH:MM")
    //   a9 date       Pascal string (length byte + "MM-DD-YY")
    //   a36 to
    //   a36 by        (from_name)
    //   a73 re        (subject)
    //
    // MSGTXT.BBS  — message text in 128-byte records.
    //   Each message occupies numrecs records starting at startrec.
    //   Text uses \r as line separator; kludge lines start with \x01.

    private const HDR_SIZE = 187;

    private const IDX_SIZE = 3;

    private const TXT_RECORD = 128;

    private const DELETED_MSGNO = 0xFFFF;

    /**
     * Import all messages from a Hudson message base directory.
     * Returns count of messages imported.
     */
    public function import(string $basePath, Dataset $dataset): int
    {
        $idxPath = $this->findFile($basePath, 'MSGIDX.BBS');
        $hdrPath = $this->findFile($basePath, 'MSGHDR.BBS');
        $txtPath = $this->findFile($basePath, 'MSGTXT.BBS');

        if (! $idxPath || ! $hdrPath || ! $txtPath) {
            return 0;
        }

        $fidx = fopen($idxPath, 'rb');
        $fhdr = fopen($hdrPath, 'rb');
        $ftxt = fopen($txtPath, 'rb');

        try {
            $count = $this->importMessages($fidx, $fhdr, $ftxt, $dataset);
        } finally {
            fclose($fidx);
            fclose($fhdr);
            fclose($ftxt);
        }

        return $count;
    }

    private function importMessages($fidx, $fhdr, $ftxt, Dataset $dataset): int
    {
        $areas = [];
        $records = [];
        $count = 0;
        $position = 0;

        while (! feof($fidx)) {
            $idxRaw = fread($fidx, self::IDX_SIZE);
            if (strlen($idxRaw) < self::IDX_SIZE) {
                break;
            }

            $idx = unpack('vmsgno/Cboard', $idxRaw);

            // Skip deleted messages
            if ($idx['msgno'] === self::DELETED_MSGNO) {
                $position++;

                continue;
            }

            // Read the parallel header record
            fseek($fhdr, $position * self::HDR_SIZE);
            $hdrRaw = fread($fhdr, self::HDR_SIZE);
            if (strlen($hdrRaw) < self::HDR_SIZE) {
                $position++;

                continue;
            }

            $hdr = unpack(
                'vmsgno/vreplyto/vreply1st/vtimesread/vstartrec/vnumrecs/vdestnet/vdestnode/vorignet/vorignode/Cdestzone/Corigzone/vcost/Cmsgattr/Cnetattr/Cboard/a6time/a9date/a36to/a36by/a73re',
                $hdrRaw,
            );

            // Resolve area (lazy-created per board)
            $board = $idx['board'];
            if (! isset($areas[$board])) {
                $areaCode = 'BOARD'.$board;
                $areas[$board] = Area::firstOrCreate(
                    ['dataset_id' => $dataset->id, 'code' => $areaCode],
                    ['name' => $areaCode, 'sort_order' => $board],
                );
            }
            $area = $areas[$board];

            // Read body text
            fseek($ftxt, $hdr['startrec'] * self::TXT_RECORD);
            $txtRaw = $hdr['numrecs'] > 0 ? fread($ftxt, $hdr['numrecs'] * self::TXT_RECORD) : '';
            $charset = CharsetDetector::detect($txtRaw);
            $body = $this->parseBody($txtRaw);

            $records[] = [
                'dataset_id' => $dataset->id,
                'area_id' => $area->id,
                'msgno' => $hdr['msgno'],
                'from_name' => $this->toUtf8(substr($hdr['by'], 1), $charset),
                'to_name' => $this->toUtf8(substr($hdr['to'], 1), $charset),
                'subject' => $this->toUtf8(substr($hdr['re'], 1), $charset),
                'body_text' => $this->toUtf8($body, $charset),
                'attributes_raw' => $hdr['msgattr'] | ($hdr['netattr'] << 8),
                'reply_to_msgno' => $hdr['replyto'] ?: null,
                'reply1st_msgno' => $hdr['reply1st'] ?: null,
                'replynext_msgno' => null,
                'posted_at' => $this->parseDate($hdr['date'], $hdr['time']),
                'created_at' => now(),
                'updated_at' => now(),
            ];

            $count++;
            $position++;

            if (count($records) >= 500) {
                Message::insert($records);
                $records = [];
            }
        }

        if ($records) {
            Message::insert($records);
        }

        // Update message counts per area
        foreach ($areas as $area) {
            $area->update(['message_count' => Message::where('area_id', $area->id)->count()]);
        }

        return $count;
    }

    private function parseBody(string $raw): string
    {
        // Strip leading 0xFF (soft CR marker used by some implementations)
        $raw = ltrim($raw, "\xFF");
        $raw = rtrim($raw, "\x00");
        $raw = str_replace(["\r\n", "\r"], ["\n", "\n"], $raw);

        return $raw;
    }

    /**
     * Parse Hudson date/time Pascal strings into Carbon.
     *
     * Both fields are length-prefixed: first byte = length, rest = content.
     * date[9]: length + "MM-DD-YY"
     * time[6]: length + "HH:MM"
     */
    private function parseDate(string $date, string $time): ?Carbon
    {
        // Skip the length prefix byte
        $dateStr = substr($date, 1);
        $timeStr = substr($time, 1);

        if (! $dateStr || ! $timeStr) {
            return null;
        }

        // Format: MM-DD-YY HH:MM
        $dt = Carbon::createFromFormat('m-d-y H:i', trim($dateStr).' '.trim($timeStr));

        return $dt ?: null;
    }

    private function toUtf8(string $str, string $charset = 'CP850'): string
    {
        return mb_convert_encoding(rtrim($str, "\x00"), 'UTF-8', $charset);
    }

    private function findFile(string $dir, string $filename): ?string
    {
        $path = rtrim($dir, '/').'/'.$filename;
        if (file_exists($path)) {
            return $path;
        }
        // Try lowercase extension
        $lower = rtrim($dir, '/').'/'.strtolower($filename);
        if (file_exists($lower)) {
            return $lower;
        }

        return null;
    }
}
