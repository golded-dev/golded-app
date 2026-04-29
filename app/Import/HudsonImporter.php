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
        private readonly MessageImportRecordMapper $mapper = new MessageImportRecordMapper,
    ) {}

    /**
     * Import all messages from a Hudson message base directory.
     * Returns count of messages imported.
     */
    public function import(string $basePath): int
    {
        $areas = [];
        $records = [];
        $inserted = 0;

        foreach ($this->reader->read($basePath) as $message) {
            $area = $this->areaFor($message, $areas);
            $records[] = $this->recordFor($message, $area);

            if (count($records) >= 500) {
                $inserted += Message::insertOrIgnore($records);
                $records = [];
            }
        }

        if ($records !== []) {
            $inserted += Message::insertOrIgnore($records);
        }

        foreach ($areas as $area) {
            $area->update(['message_count' => Message::where('area_id', $area->id)->count()]);
        }

        return $inserted;
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
                ['name' => $message->areaName ?? $areaCode, 'source_sort_order' => $message->areaSortOrder ?? 0],
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
        return $this->mapper->map($message, $area, 'hudson');
    }
}
