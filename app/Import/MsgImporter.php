<?php

namespace App\Import;

use App\Domain\CharsetDetector;
use App\Models\Area;
use App\Models\Message;
use Carbon\Carbon;

class MsgImporter
{
    use ReadsGoldedConfig;
    // .MSG header layout (190 bytes total):
    //   0-35   from_name   (36 bytes, null-padded)
    //  36-71   to_name     (36 bytes, null-padded)
    //  72-143  subject     (72 bytes, null-padded)
    // 144-163  date_str    (20 bytes, "DD Mon YY  HH:MM:SS")
    // 164-165  times_read  (uint16 LE)
    // 166-167  dest_node   (uint16 LE)
    // 168-169  orig_node   (uint16 LE)
    // 170-171  cost        (uint16 LE)
    // 172-173  orig_net    (uint16 LE)
    // 174-175  dest_net    (uint16 LE)
    // 176-183  date_arrived (8 bytes)
    // 184-185  reply       (uint16 LE, reply-to msgno)
    // 186-187  attr        (uint16 LE, attribute bitfield)
    // 188-189  up          (uint16 LE, first reply msgno)
    // 190+     body        (null-terminated, \r = line separator)

    private const HEADER_SIZE = 190;

    /** Import all .msg files from $path into the given Area. Returns count imported. */
    public function import(string $path, Area $area): int
    {
        $this->applyAreaDefMeta($area, $path);
        $files = glob("{$path}/*.msg") ?: glob("{$path}/*.MSG") ?: [];
        $count = 0;

        foreach ($files as $file) {
            $msgno = (int) pathinfo($file, PATHINFO_FILENAME);
            if ($msgno < 1) {
                continue;
            }

            $this->importFile($file, $msgno, $area);
            $count++;
        }

        $area->update(['message_count' => $count]);

        return $count;
    }

    private function importFile(string $file, int $msgno, Area $area): void
    {
        $raw = file_get_contents($file);

        if (strlen($raw) < self::HEADER_SIZE) {
            return;
        }

        $fromName = $this->readField($raw, 0, 36);
        $toName = $this->readField($raw, 36, 36);
        $subject = $this->readField($raw, 72, 72);
        $dateStr = $this->readField($raw, 144, 20);
        $attr = unpack('v', substr($raw, 186, 2))[1];
        $bodyRaw = substr($raw, self::HEADER_SIZE);
        $charset = CharsetDetector::detect($bodyRaw, $this->areaFallbackCharset($area->code));
        $body = $this->parseBody($bodyRaw);

        Message::create([
            'area_id' => $area->id,
            'msgno' => $msgno,
            'subject' => $this->toUtf8($subject, $charset),
            'from_name' => $this->toUtf8($fromName, $charset),
            'to_name' => $this->toUtf8($toName, $charset),
            'body_text' => $this->toUtf8($body, $charset),
            'attributes_raw' => $attr,
            'posted_at' => $this->parseDate($dateStr),
        ]);
    }

    /** Read a null-terminated, null-padded fixed-width field. */
    private function readField(string $raw, int $offset, int $length): string
    {
        $field = substr($raw, $offset, $length);

        if (($null = strpos($field, "\x00")) !== false) {
            $field = substr($field, 0, $null);
        }

        return $field;
    }

    /** Strip kludge lines (start with \x01) and convert \r to \n. */
    private function parseBody(string $raw): string
    {
        // Trim trailing null
        $raw = rtrim($raw, "\x00");

        // Convert hard CRs to newlines
        $raw = str_replace("\r\n", "\n", $raw);
        $raw = str_replace("\r", "\n", $raw);

        return $raw;
    }

    private function toUtf8(string $str, string $charset = 'CP850'): string
    {
        return mb_convert_encoding($str, 'UTF-8', $charset);
    }

    private function parseDate(string $dateStr): ?Carbon
    {
        $dateStr = trim($dateStr);

        if (empty($dateStr)) {
            return null;
        }

        try {
            return Carbon::createFromFormat('d M y  H:i:s', $dateStr)
                ?? Carbon::createFromFormat('d M y H:i:s', $dateStr);
        } catch (\Exception) {
            return null;
        }
    }
}
