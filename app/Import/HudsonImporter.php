<?php

declare(strict_types=1);

namespace App\Import;

use App\Models\Area;
use App\Models\Message;
use Golded\Ftn\Hudson\HudsonReader;
use Golded\Ftn\ParsedMessage;

class HudsonImporter
{
    use ReadsGoldedConfig;

    public function __construct(
        private readonly HudsonReader $reader = new HudsonReader,
    ) {}

    /**
     * Import all messages from a Hudson message base directory.
     * Returns count of messages imported.
     */
    public function import(string $basePath): int
    {
        $areas = [];
        $records = [];
        $count = 0;

        foreach ($this->reader->read($basePath) as $message) {
            $area = $this->areaFor($message, $areas);
            $records[] = $this->recordFor($message, $area);
            $count++;

            if (count($records) >= 500) {
                Message::insertOrIgnore($records);
                $records = [];
            }
        }

        if ($records !== []) {
            Message::insertOrIgnore($records);
        }

        foreach ($areas as $area) {
            $area->update(['message_count' => Message::where('area_id', $area->id)->count()]);
        }

        return $count;
    }

    /**
     * @param  array<string, Area>  $areas
     */
    private function areaFor(ParsedMessage $message, array &$areas): Area
    {
        $areaCode = $message->areaCode ?? 'BOARD0';

        if (! isset($areas[$areaCode])) {
            $areas[$areaCode] = Area::firstOrCreate(
                ['code' => $areaCode, 'source_type' => 'hudson'],
                ['name' => $message->areaName ?? $areaCode, 'sort_order' => $message->areaSortOrder ?? 0],
            );
            $this->applyAreaDefMeta($areas[$areaCode], $message->areaMetaKey ?? strtolower($areaCode));
        }

        return $areas[$areaCode];
    }

    /**
     * @return array<string, mixed>
     */
    private function recordFor(ParsedMessage $message, Area $area): array
    {
        $externalId = $message->externalId
            ?? $this->syntheticId($message->fromName, $message->toName, $message->subject, $message->postedAt?->format(DATE_ATOM), $message->bodyText);

        return [
            'area_id' => $area->id,
            'msgno' => $message->msgno,
            'external_id' => $externalId,
            'from_name' => $message->fromName,
            'to_name' => $message->toName,
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
