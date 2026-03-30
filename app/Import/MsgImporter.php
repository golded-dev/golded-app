<?php

namespace App\Import;

use App\Models\Area;
use App\Models\Message;
use Carbon\Carbon;

class MsgImporter
{
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
        $body = $this->parseBody(substr($raw, self::HEADER_SIZE));

        Message::create([
            'dataset_id' => $area->dataset_id,
            'area_id' => $area->id,
            'msgno' => $msgno,
            'subject' => $this->toUtf8($subject),
            'from_name' => $this->toUtf8($fromName),
            'to_name' => $this->toUtf8($toName),
            'body_text' => $this->toUtf8($body),
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

        // Split, strip kludge lines, rejoin
        $lines = explode("\n", $raw);
        $lines = array_filter($lines, fn ($line) => ! str_starts_with($line, "\x01"));

        return implode("\n", array_values($lines));
    }

    private function toUtf8(string $str): string
    {
        return mb_convert_encoding($str, 'UTF-8', 'CP850');
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
