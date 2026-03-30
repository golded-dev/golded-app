<?php

namespace App\Console\Commands;

use App\Import\MsgImporter;
use App\Models\Area;
use App\Models\Dataset;
use Illuminate\Console\Command;

class ImportMessages extends Command
{
    protected $signature = 'golded:import {format : Message base format (msg, jam, squish)} {path : Path to message base root}';

    protected $description = 'Import messages from a FidoNet message base';

    public function handle(): int
    {
        $format = strtolower($this->argument('format'));
        $path = rtrim($this->argument('path'), '/');

        if (! is_dir($path)) {
            $this->error("Path not found: {$path}");

            return self::FAILURE;
        }

        return match ($format) {
            'msg' => $this->importMsg($path),
            default => $this->error("Unsupported format: {$format}") ?: self::FAILURE,
        };
    }

    private function importMsg(string $basePath): int
    {
        $dataset = Dataset::firstOrCreate(['name' => basename($basePath)], ['source_type' => 'msg']);
        $importer = new MsgImporter;
        $total = 0;
        $areaDirs = glob("{$basePath}/*", GLOB_ONLYDIR) ?: [];

        foreach ($areaDirs as $areaPath) {
            $areaName = strtoupper(basename($areaPath));
            $area = Area::firstOrCreate(
                ['dataset_id' => $dataset->id, 'code' => $areaName],
                ['name' => $areaName, 'sort_order' => 0],
            );

            $count = $importer->import($areaPath, $area);
            $this->line("  {$areaName}: {$count} messages");
            $total += $count;
        }

        $this->info("Imported {$total} messages from ".count($areaDirs).' areas.');

        return self::SUCCESS;
    }
}
