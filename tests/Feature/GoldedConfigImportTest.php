<?php

use App\Config\GoldedConfigParser;

function cfgPath(): string
{
    return base_path('samples/config/GOLDED.CFG');
}

// ── Tracer bullet ────────────────────────────────────────────────────────────

it('extracts username from GOLDED.CFG', function (): void {
    $config = (new GoldedConfigParser)->parse(cfgPath());

    expect($config['username'])->toBe('Demo User');
});

// ── Field extraction ─────────────────────────────────────────────────────────

it('extracts address', function (): void {
    $config = (new GoldedConfigParser)->parse(cfgPath());

    expect($config['address'])->toBe('2:999/1');
});

it('extracts global charset_import', function (): void {
    $config = (new GoldedConfigParser)->parse(cfgPath());

    expect($config['charset_import'])->toBe('CP850');
});

it('extracts multiple origin lines', function (): void {
    $config = (new GoldedConfigParser)->parse(cfgPath());

    expect($config['origins'])
        ->toBeArray()
        ->toContain('GoldED 7 public demo');
    expect($config['origins'])->not()->toBeEmpty();
});

it('extracts tearline', function (): void {
    $config = (new GoldedConfigParser)->parse(cfgPath());

    expect($config['tearline'])->toBe('@longpid @version');
});

// ── Conditional blocks ───────────────────────────────────────────────────────

it('skips IF 0 blocks', function (): void {
    $config = (new GoldedConfigParser)->parse(cfgPath());

    expect($config['taglines'])->toBeEmpty();

    expect($config['username'])->not()->toBeNull();
    expect($config['charset_import'])->not()->toBeNull();
});

// ── Artisan command ──────────────────────────────────────────────────────────

it('writes config/golded.php via artisan command', function (): void {
    $output = base_path('config/golded.php');
    $original = file_get_contents($output);

    try {
        $this->artisan('golded:config', ['path' => cfgPath()])
            ->assertSuccessful();

        expect(file_exists($output))->toBeTrue();
        $written = require $output;

        expect($written['username'])->toBe('Demo User');
        expect($written['address'])->toBe('2:999/1');
    } finally {
        file_put_contents($output, $original);
    }
});
