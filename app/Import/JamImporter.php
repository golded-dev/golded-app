<?php

declare(strict_types=1);

namespace App\Import;

use App\Models\Area;
use App\Models\Message;
use Golded\Ftn\Jam\JamReader;
use Golded\Ftn\ParsedMessage;
use Golded\Ftn\ReaderOptions;

class JamImporter
{
    use ReadsGoldedConfig;

    public function __construct(
        private readonly JamReader $reader = new JamReader,
    ) {}

    /**
     * Import all messages from a JAM base (path without extension).
     * Returns count of messages imported.
     */
    public function import(string $basePath, ?Area $area = null): int
    {
        if (! $area instanceof Area) {
            $areaName = strtoupper(basename($basePath));
            $area = Area::firstOrCreate(
                ['code' => $areaName, 'source_type' => 'jam'],
                ['name' => $areaName, 'sort_order' => 0],
            );
            $this->applyAreaDefMeta($area, $basePath);
        }

        $inserted = 0;
        $records = [];

        foreach ($this->reader->read($basePath, new ReaderOptions($this->areaFallbackCharset($area->code))) as $message) {
            $records[] = $this->recordFor($message, $area);

            if (count($records) >= 500) {
                $inserted += Message::insertOrIgnore($records);
                $records = [];
            }
        }

        if ($records !== []) {
            $inserted += Message::insertOrIgnore($records);
        }

        $area->update(['message_count' => Message::where('area_id', $area->id)->count()]);

        return $inserted;
    }

    /**
     * @return array<string, mixed>
     */
    private function recordFor(ParsedMessage $message, Area $area): array
    {
        return [
            'area_id' => $area->id,
            'msgno' => $message->msgno,
            'external_id' => $message->externalId,
            'from_name' => $message->fromName,
            'from_address' => $message->fromAddress,
            'to_name' => $message->toName,
            'to_address' => $message->toAddress,
            'subject' => $message->subject,
            'body_text' => $message->bodyText,
            'attributes_raw' => $message->attributesRaw,
            'reply_to_msgno' => $message->replyToMsgno,
            'reply1st_msgno' => $message->reply1stMsgno,
            'replynext_msgno' => $message->replyNextMsgno,
            'posted_at' => $message->postedAt,
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }
}
