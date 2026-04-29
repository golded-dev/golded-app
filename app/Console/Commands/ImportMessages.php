<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Import\HudsonImporter;
use App\Import\JamImporter;
use App\Import\MsgImporter;
use App\Import\SquishImporter;
use App\Models\Area;
use Illuminate\Console\Command;

class ImportMessages extends Command
{
    protected $signature = 'golded:import {format : Message base format (msg, jam, squish, hudson)} {path : Path to message base root} {--fresh : Delete existing areas for this source_type before importing}';

    protected $description = 'Import messages from a FidoNet message base';

    public function handle(): int
    {
        $format = strtolower($this->argument('format'));
        $path = rtrim($this->argument('path'), '/');

        if (! is_dir($path)) {
            $this->error("Path not found: {$path}");

            return self::FAILURE;
        }

        if ($this->option('fresh')) {
            $deleted = Area::where('source_type', $format)->count();
            Area::where('source_type', $format)->delete();
            $this->line("  Dropped {$deleted} existing {$format} areas (cascades messages).");
        }

        if (! in_array($format, ['msg', 'jam', 'squish', 'hudson'], true)) {
            $this->error("Unsupported format: {$format}");

            return self::FAILURE;
        }

        return match ($format) {
            'msg' => $this->importMsg($path),
            'jam' => $this->importJam($path),
            'squish' => $this->importSquish($path),
            'hudson' => $this->importHudson($path),
        };
    }

    private function importMsg(string $basePath): int
    {
        $importer = new MsgImporter;
        $total = 0;
        $areaDirs = glob("{$basePath}/*", GLOB_ONLYDIR) ?: [];

        foreach ($areaDirs as $areaPath) {
            $areaName = strtoupper(basename($areaPath));
            $area = Area::firstOrCreate(
                ['code' => $areaName, 'source_type' => 'msg'],
                ['name' => $areaName, 'source_sort_order' => 0],
            );

            $count = $importer->import($areaPath, $area);
            $this->line("  {$areaName}: {$count} messages");
            $total += $count;
        }

        $this->info("Imported {$total} messages from ".count($areaDirs).' areas.');

        return self::SUCCESS;
    }

    private function importJam(string $basePath): int
    {
        $importer = new JamImporter;
        $total = 0;

        foreach ($this->findJhrFiles($basePath) as $jhrFile) {
            $base = preg_replace('/\.(JHR|jhr)$/', '', (string) $jhrFile);
            $count = $importer->import($base);
            $areaName = strtoupper(basename((string) $base));
            $this->line("  {$areaName}: {$count} messages");
            $total += $count;
        }

        $this->info("Imported {$total} messages.");

        return self::SUCCESS;
    }

    private function importSquish(string $basePath): int
    {
        $importer = new SquishImporter;
        $total = 0;

        foreach ($this->findSqdFiles($basePath) as $sqdFile) {
            $base = preg_replace('/\.(SQD|sqd)$/', '', (string) $sqdFile);
            $count = $importer->import($base);
            $areaName = strtoupper(basename((string) $base));
            $this->line("  {$areaName}: {$count} messages");
            $total += $count;
        }

        $this->info("Imported {$total} messages.");

        return self::SUCCESS;
    }

    private function importHudson(string $basePath): int
    {
        $importer = new HudsonImporter;
        $count = $importer->import($basePath);
        $this->info("Imported {$count} messages.");

        return self::SUCCESS;
    }

    private function findSqdFiles(string $dir): array
    {
        $files = array_merge(
            glob("{$dir}/*.SQD") ?: [],
            glob("{$dir}/*.sqd") ?: [],
        );

        foreach (glob("{$dir}/*", GLOB_ONLYDIR) ?: [] as $sub) {
            $files = array_merge($files, glob("{$sub}/*.SQD") ?: [], glob("{$sub}/*.sqd") ?: []);
        }

        return array_unique($files);
    }

    private function findJhrFiles(string $dir): array
    {
        $files = array_merge(
            glob("{$dir}/*.JHR") ?: [],
            glob("{$dir}/*.jhr") ?: [],
        );

        foreach (glob("{$dir}/*", GLOB_ONLYDIR) ?: [] as $sub) {
            $files = array_merge($files, glob("{$sub}/*.JHR") ?: [], glob("{$sub}/*.jhr") ?: []);
        }

        return array_unique($files);
    }
}
