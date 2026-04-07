<?php

namespace App\Import;

use App\Domain\CharsetDetector;
use App\Models\Area;
use App\Models\Message;
use Carbon\Carbon;

class SquishImporter
{
    use ReadsGoldedConfig;
    // .SQD layout:
    //   0-255     SqshBase (256 bytes)
    //   256+      frames (linked list)
    //
    // SqshBase (256 bytes, all little-endian):
    //   v   size             sizeof(SqshBase)
    //   v   reserved1
    //   V   totalMsgs
    //   V   highestMsg
    //   V   protMsgs
    //   V   hwm
    //   V   nextmsgno
    //   a80 name
    //   l   firstframe       offset of first msg frame
    //   l   lastframe
    //   l   firstfreeframe
    //   l   lastfreeframe
    //   l   endframe
    //   V   maxMsgs
    //   v   daystokeep
    //   v   framesize        sizeof(SqshFrm) — always 28
    //   a124 reserved2
    //
    // SqshFrm (28 bytes):
    //   V   id               must equal SQFRAMEID = 0xAFAE4453
    //   l   next             offset of next frame
    //   l   prev
    //   V   length           total frame length
    //   V   totsize          hdr + ctl + txt data length
    //   V   ctlsize          control/kludge block length
    //   v   type             0=normal, 1=free
    //   v   reserved
    //
    // SqshHdr (238 bytes):
    //   V   attr
    //   a36 from
    //   a36 to
    //   a72 subj
    //   a8  orig   (ftn_addr: zone/net/node/point each uint16)
    //   a8  dest
    //   V   date_written     gopustime (DOS FAT bitfield)
    //   V   date_arrived
    //   v   utc_offset
    //   V   replyto
    //   a36 replies          9 × uint32 reply msgno
    //   V   umsgid
    //   a20 ftsc_date
    //
    // .SQI layout: repeated 12-byte records
    //   l   offset           frame offset in .SQD
    //   V   msgno
    //   V   hash

    private const SQFRAMEID = 0xAFAE4453;

    private const BASE_SIZE = 256;

    private const FRAME_SIZE = 28;

    private const HDR_SIZE = 238;  // sizeof(SqshHdr)

    private const IDX_SIZE = 12;

    /**
     * Import all messages from a Squish base (path without extension).
     * e.g. import('/path/to/NETMAIL')
     * Returns count of messages imported.
     */
    public function import(string $basePath, ?Area $area = null): int
    {
        $sqdPath = $this->findFile($basePath, 'sqd');
        $sqiPath = $this->findFile($basePath, 'sqi');

        if (! $sqdPath || ! $sqiPath) {
            return 0;
        }

        if ($area === null) {
            $areaName = strtoupper(basename($basePath));
            $area = Area::firstOrCreate(
                ['code' => $areaName, 'source_type' => 'squish'],
                ['name' => $areaName, 'sort_order' => 0],
            );
            $this->applyAreaDefMeta($area, $basePath);
        }

        $fsqd = fopen($sqdPath, 'rb');
        $fsqi = fopen($sqiPath, 'rb');

        try {
            $count = $this->importMessages($fsqd, $fsqi, $area);
        } finally {
            fclose($fsqd);
            fclose($fsqi);
        }

        $area->update(['message_count' => Message::where('area_id', $area->id)->count()]);

        return $count;
    }

    private function importMessages($fsqd, $fsqi, Area $area): int
    {
        // Validate SQD base header
        $baseRaw = fread($fsqd, self::BASE_SIZE);
        if (strlen($baseRaw) < self::BASE_SIZE) {
            return 0;
        }

        $inserted = 0;
        $records = [];

        // Walk SQI index to get (offset, msgno) pairs
        while (! feof($fsqi)) {
            $idxRaw = fread($fsqi, self::IDX_SIZE);
            if (strlen($idxRaw) < self::IDX_SIZE) {
                break;
            }

            $idx = unpack('loffset/Vmsgno/Vhash', $idxRaw);

            if ($idx['offset'] <= 0) {
                continue;
            }

            fseek($fsqd, $idx['offset']);

            // Read and validate frame header
            $frmRaw = fread($fsqd, self::FRAME_SIZE);
            if (strlen($frmRaw) < self::FRAME_SIZE) {
                continue;
            }

            $frm = unpack('Vid/lnext/lprev/Vlength/Vtotsize/Vctlsize/vtype/vreserved', $frmRaw);

            if ($frm['id'] !== self::SQFRAMEID) {
                continue;
            }

            // Skip free frames
            if ($frm['type'] !== 0) {
                continue;
            }

            // Read message header
            $hdrRaw = fread($fsqd, self::HDR_SIZE);
            if (strlen($hdrRaw) < self::HDR_SIZE) {
                continue;
            }

            $hdr = unpack(
                'Vattr/a36from/a36to/a72subj/a8orig/a8dest/Vdate_written/Vdate_arrived/vutc_offset/Vreplyto/a36replies/Vumsgid/a20ftsc_date',
                $hdrRaw,
            );

            // Read kludge control block then body text
            $ctlRaw = $frm['ctlsize'] > 0 ? fread($fsqd, $frm['ctlsize']) : '';
            $txtSize = $frm['totsize'] - self::HDR_SIZE - $frm['ctlsize'];
            $bodyRaw = $txtSize > 0 ? fread($fsqd, $txtSize) : '';

            // Charset may be declared in the kludge control block or body
            $charset = CharsetDetector::detect($ctlRaw.$bodyRaw, $this->areaFallbackCharset($area->code));
            // Convert Squish control block to standard \x01-prefixed kludge lines,
            // then prepend to body so they're stored inline like other formats
            $body = $this->parseCtl($ctlRaw).$this->parseBody($bodyRaw);

            // Decode replies[9] array: 9 × uint32 little-endian
            $replies = array_values(unpack('V9', $hdr['replies']));

            $fromName = $this->toUtf8($hdr['from'], $charset);
            $toName = $this->toUtf8($hdr['to'], $charset);
            $subject = $this->toUtf8($hdr['subj'], $charset);
            $postedAt = $this->parseDate($hdr['date_written']);

            $externalId = $this->extractMsgid($ctlRaw)
                ?? $this->syntheticId($fromName, $toName, $subject, $postedAt?->toIso8601String(), $this->parseBody($bodyRaw));

            $records[] = [
                'area_id' => $area->id,
                'msgno' => $idx['msgno'],
                'external_id' => $externalId,
                'from_name' => $fromName,
                'to_name' => $toName,
                'subject' => $subject,
                'body_text' => $this->toUtf8($body, $charset),
                'attributes_raw' => $hdr['attr'],
                'reply_to_msgno' => $hdr['replyto'] ?: null,
                'reply1st_msgno' => $replies[0] ?: null,
                'replynext_msgno' => null,  // Squish uses replies[] array, not linked list
                'posted_at' => $postedAt,
                'created_at' => now(),
                'updated_at' => now(),
            ];

            if (count($records) >= 500) {
                $inserted += Message::insertOrIgnore($records);
                $records = [];
            }
        }

        if ($records) {
            $inserted += Message::insertOrIgnore($records);
        }

        return $inserted;
    }

    private function extractMsgid(string $ctlRaw): ?string
    {
        if (preg_match('/\x01MSGID:\s*(.+?)(?:\x01|\x00|$)/s', $ctlRaw, $m)) {
            return trim($m[1]);
        }

        return null;
    }

    /**
     * Convert Squish control block to standard kludge lines.
     *
     * Squish stores kludges as: \x01text\x01text\x01... (CTRL_A-delimited, no CR).
     * Output: \x01text\n per entry, skipping AREA: routing lines.
     */
    private function parseCtl(string $ctlRaw): string
    {
        $result = '';
        $i = 0;
        $len = strlen($ctlRaw);

        while ($i < $len && $ctlRaw[$i] === "\x01" && isset($ctlRaw[$i + 1]) && $ctlRaw[$i + 1] !== "\x00") {
            $isArea = substr($ctlRaw, $i + 1, 5) === 'AREA:';
            $i++; // skip leading \x01

            $text = '';
            while ($i < $len && $ctlRaw[$i] !== "\x01" && $ctlRaw[$i] !== "\x00") {
                $text .= $ctlRaw[$i++];
            }

            if (! $isArea && $text !== '') {
                $result .= "\x01".$text."\n";
            }
        }

        return $result;
    }

    /**
     * Decode a gopustime (DOS FAT 32-bit bitfield) to Carbon.
     *
     * Bit layout (little-endian uint32):
     *   bits  0-4  : day
     *   bits  5-8  : month
     *   bits  9-15 : year (+ 1980)
     *   bits 16-20 : seconds / 2
     *   bits 21-26 : minutes
     *   bits 27-31 : hours
     */
    private function parseDate(int $raw): ?Carbon
    {
        if ($raw === 0) {
            return null;
        }

        $day = $raw & 0x1F;
        $month = ($raw >> 5) & 0x0F;
        $year = 1980 + (($raw >> 9) & 0x7F);
        $sec = (($raw >> 16) & 0x1F) * 2;
        $min = ($raw >> 21) & 0x3F;
        $hour = ($raw >> 27) & 0x1F;

        if ($month < 1 || $month > 12 || $day < 1 || $day > 31) {
            return null;
        }

        return Carbon::create($year, $month, $day, $hour, $min, $sec);
    }
}
