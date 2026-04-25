<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Import\JamImporter;
use App\Import\MsgImporter;
use App\Import\SquishImporter;
use App\Models\Area;
use Illuminate\Console\Command;

class ImportFromConfig extends Command
{
    protected $signature = 'golded:import-config {--root= : Root path to map M:\\ to (default: samples relative to project root)}';

    protected $description = 'Import messages from all areas defined in config/golded.php';

    public function handle(): int
    {
        $root = rtrim($this->option('root') ?? base_path('samples'), '/');
        $areas = config('golded.areas', []);
        $total = 0;

        foreach ($areas as $winPath => $def) {
            if (! is_string($winPath)) {
                continue; // skip integer Hudson board numbers
            }

            $format = strtolower($def['format'] ?? '');

            if (! in_array($format, ['squish', 'jam', 'msg', 'opus'])) {
                continue; // skip unsupported formats
            }

            $unixPath = $this->translatePath($winPath, $root);
            $sourceType = $format === 'opus' ? 'msg' : $format;

            $area = Area::firstOrCreate(
                ['code' => strtoupper((string) $def['echoid'])],
                [
                    'name' => $def['description'] ?? $def['echoid'],
                    'echoid' => $def['echoid'],
                    'group_id' => $def['group_id'] ?? null,
                    'area_type' => $def['area_type'] ?? null,
                    'source_type' => $sourceType,
                    'sort_order' => 0,
                ],
            );

            if (! $this->pathExists($unixPath, $format)) {
                $this->line("  {$def['echoid']}: skipped (path not found: {$unixPath})");

                continue;
            }

            $count = $this->importArea($unixPath, $format, $area);

            if ($count > 0) {
                $this->line("  {$def['echoid']}: {$count} new messages");
                $total += $count;
            }
        }

        $this->info("Imported {$total} new messages.");

        return self::SUCCESS;
    }

    private function importArea(string $unixPath, string $format, Area $area): int
    {
        return match ($format) {
            'squish' => (new SquishImporter)->import($unixPath, $area),
            'jam' => (new JamImporter)->import($unixPath, $area),
            'msg', 'opus' => (new MsgImporter)->import($unixPath, $area),
            default => 0,
        };
    }

    /** Check whether the archive path exists for the given format. */
    private function pathExists(string $unixPath, string $format): bool
    {
        return match ($format) {
            'squish' => file_exists("{$unixPath}.sqd") || file_exists("{$unixPath}.SQD"),
            'jam' => file_exists("{$unixPath}.jhr") || file_exists("{$unixPath}.JHR"),
            'msg', 'opus' => is_dir($unixPath),
            default => false,
        };
    }

    private function translatePath(string $winPath, string $root): string
    {
        // M:\SQUISH\NET\ALL → {root}/SQUISH/NET/ALL
        $relative = ltrim(str_replace('\\', '/', substr($winPath, 2)), '/');

        return $root.'/'.$relative;
    }
}
