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
        private readonly MessageImportRecordMapper $mapper = new MessageImportRecordMapper,
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
                ['name' => $areaName, 'source_sort_order' => 0],
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
        return $this->mapper->map($message, $area, 'jam');
    }
}
