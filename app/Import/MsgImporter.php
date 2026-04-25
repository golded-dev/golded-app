<?php

declare(strict_types=1);

namespace App\Import;

use App\Models\Area;
use App\Models\Message;
use Golded\Ftn\Msg\MsgReader;
use Golded\Ftn\ParsedMessage;
use Golded\Ftn\ReaderOptions;

class MsgImporter
{
    use ReadsGoldedConfig;

    public function __construct(
        private readonly MsgReader $reader = new MsgReader,
    ) {}

    /** Import all .msg files from $path into the given Area. Returns count imported. */
    public function import(string $path, Area $area): int
    {
        $this->applyAreaDefMeta($area, $path);
        $count = 0;

        foreach ($this->reader->read($path, new ReaderOptions($this->areaFallbackCharset($area->code))) as $message) {
            $this->persist($message, $area);
            $count++;
        }

        $area->update(['message_count' => $count]);

        return $count;
    }

    private function persist(ParsedMessage $message, Area $area): void
    {
        Message::firstOrCreate(
            ['external_id' => $message->externalId],
            [
                'area_id' => $area->id,
                'msgno' => $message->msgno,
                'subject' => $message->subject,
                'from_name' => $message->fromName,
                'to_name' => $message->toName,
                'body_text' => $message->bodyText,
                'attributes_raw' => $message->attributesRaw,
                'posted_at' => $message->postedAt,
            ],
        );
    }
}
