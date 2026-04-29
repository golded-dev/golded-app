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
        private readonly MessageImportRecordMapper $mapper = new MessageImportRecordMapper,
    ) {}

    /** Import all .msg files from $path into the given Area. Returns count imported. */
    public function import(string $path, Area $area): int
    {
        $this->applyAreaDefMeta($area, $path);
        $inserted = 0;

        foreach ($this->reader->read($path, new ReaderOptions($this->areaFallbackCharset($area->code))) as $message) {
            $inserted += $this->persist($message, $area);
        }

        $area->update(['message_count' => Message::where('area_id', $area->id)->count()]);

        return $inserted;
    }

    private function persist(ParsedMessage $message, Area $area): int
    {
        return Message::insertOrIgnore([
            $this->mapper->map($message, $area, 'msg'),
        ]);
    }
}
