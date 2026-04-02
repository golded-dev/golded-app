<?php

namespace App\Import;

use App\Domain\CharsetDetector;
use App\Models\Area;
use App\Models\Dataset;
use App\Models\Message;
use Carbon\Carbon;

class JamImporter
{
    // JHR file layout:
    //   0-1023   JamHdrInfo (file header, 1024 bytes)
    //   1024+    JamHdr (76 bytes) + subfieldlen bytes, repeated per message
    //
    // JamHdr (76 bytes, all little-endian):
    //   a4  signature    "JAM\0"
    //   v   revision
    //   v   reservedword
    //   V   subfieldlen
    //   V   timesread
    //   V   msgidcrc
    //   V   replycrc
    //   V   replyto
    //   V   reply1st
    //   V   replynext
    //   V   datewritten  (Unix timestamp)
    //   V   datereceived
    //   V   dateprocessed
    //   V   messagenumber
    //   V   attribute    (bit 31 = deleted)
    //   V   attribute2
    //   V   offset       (byte offset in .JDT)
    //   V   txtlen
    //   V   passwordcrc
    //   V   cost
    //
    // Subfield header (8 bytes):
    //   v  loid    (type: 0=oaddress, 1=daddress, 2=sendername, 3=receivername, 6=subject)
    //   v  hiid
    //   V  datlen

    private const JAMHDRINFO_SIZE = 1024;

    private const JAMMER_SIZE = 76;

    private const JAMSF_SIZE = 8;

    private const JAMSUB_OADDRESS = 0;

    private const JAMSUB_DADDRESS = 1;

    private const JAMSUB_SENDERNAME = 2;

    private const JAMSUB_RECEIVERNAME = 3;

    private const JAMSUB_MSGID = 4;

    private const JAMSUB_SUBJECT = 6;

    private const JAMATTR_DELETED = 0x80000000;

    /**
     * Import all messages from a JAM base (path without extension).
     * e.g. import('/path/to/NETMAIL', $dataset)
     * Returns count of messages imported.
     */
    public function import(string $basePath, Dataset $dataset): int
    {
        $jhrPath = $this->findFile($basePath, 'jhr');
        $jdtPath = $this->findFile($basePath, 'jdt');

        if (! $jhrPath || ! $jdtPath) {
            return 0;
        }

        $areaName = strtoupper(basename($basePath));
        $area = Area::firstOrCreate(
            ['dataset_id' => $dataset->id, 'code' => $areaName],
            ['name' => $areaName, 'sort_order' => 0],
        );

        $fhr = fopen($jhrPath, 'rb');
        $fdt = fopen($jdtPath, 'rb');

        try {
            $count = $this->importMessages($fhr, $fdt, $area, $dataset);
        } finally {
            fclose($fhr);
            fclose($fdt);
        }

        $area->update(['message_count' => $count]);

        return $count;
    }

    private function importMessages($fhr, $fdt, Area $area, Dataset $dataset): int
    {
        // Skip file header
        $info = fread($fhr, self::JAMHDRINFO_SIZE);
        if (substr($info, 0, 4) !== "JAM\0") {
            return 0;
        }

        $count = 0;
        $records = [];

        while (! feof($fhr)) {
            $hdrRaw = fread($fhr, self::JAMMER_SIZE);
            if (strlen($hdrRaw) < self::JAMMER_SIZE) {
                break;
            }

            if (substr($hdrRaw, 0, 4) !== "JAM\0") {
                break;
            }

            $hdr = unpack(
                'a4sig/vrevision/vreserved/Vsubfieldlen/Vtimesread/Vmsgidcrc/Vreplycrc/'.
                'Vreplyto/Vreply1st/Vreplynext/Vdatewritten/Vdatereceived/Vdateprocessed/'.
                'Vmessagenumber/Vattribute/Vattribute2/Voffset/Vtxtlen/Vpasswordcrc/Vcost',
                $hdrRaw,
            );

            $subRaw = $hdr['subfieldlen'] > 0
                ? fread($fhr, $hdr['subfieldlen'])
                : '';

            // Skip deleted messages
            if ($hdr['attribute'] & self::JAMATTR_DELETED) {
                continue;
            }

            $fields = $this->parseSubfields($subRaw);

            // Read body from JDT
            fseek($fdt, $hdr['offset']);
            $bodyRaw = $hdr['txtlen'] > 0 ? fread($fdt, $hdr['txtlen']) : '';
            $charset = CharsetDetector::detect($bodyRaw);
            $body = $this->parseBody($bodyRaw);

            $records[] = [
                'dataset_id' => $dataset->id,
                'area_id' => $area->id,
                'msgno' => $hdr['messagenumber'],
                'from_name' => $this->toUtf8($fields[self::JAMSUB_SENDERNAME] ?? '', $charset),
                'from_address' => $fields[self::JAMSUB_OADDRESS] ?? null,
                'to_name' => $this->toUtf8($fields[self::JAMSUB_RECEIVERNAME] ?? '', $charset),
                'to_address' => $fields[self::JAMSUB_DADDRESS] ?? null,
                'subject' => $this->toUtf8($fields[self::JAMSUB_SUBJECT] ?? '', $charset),
                'body_text' => $this->toUtf8($body, $charset),
                'attributes_raw' => $hdr['attribute'],
                'reply_to_msgno' => $hdr['replyto'] ?: null,
                'reply1st_msgno' => $hdr['reply1st'] ?: null,
                'replynext_msgno' => $hdr['replynext'] ?: null,
                'posted_at' => $hdr['datewritten'] ? Carbon::createFromTimestamp($hdr['datewritten']) : null,
                'created_at' => now(),
                'updated_at' => now(),
            ];

            $count++;

            // Batch insert every 500 records
            if (count($records) >= 500) {
                Message::insert($records);
                $records = [];
            }
        }

        if ($records) {
            Message::insert($records);
        }

        return $count;
    }

    /** Parse subfield block, returning [loid => data] map. */
    private function parseSubfields(string $raw): array
    {
        $fields = [];
        $offset = 0;
        $len = strlen($raw);

        while ($offset + self::JAMSF_SIZE <= $len) {
            $sf = unpack('vloid/vhiid/Vdatlen', substr($raw, $offset, self::JAMSF_SIZE));
            $offset += self::JAMSF_SIZE;

            if ($sf['datlen'] > 0 && $offset + $sf['datlen'] <= $len) {
                $fields[$sf['loid']] = substr($raw, $offset, $sf['datlen']);
                $offset += $sf['datlen'];
            }
        }

        return $fields;
    }

    private function parseBody(string $raw): string
    {
        $raw = rtrim($raw, "\x00");
        $raw = str_replace(["\r\n", "\r"], ["\n", "\n"], $raw);

        return $raw;
    }

    private function toUtf8(string $str, string $charset = 'CP850'): string
    {
        return mb_convert_encoding(rtrim($str, "\x00"), 'UTF-8', $charset);
    }

    /** Find a file case-insensitively by extension. */
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
