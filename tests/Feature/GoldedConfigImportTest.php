<?php

use App\Config\GoldedConfigParser;

function cfgPath(): string
{
    return base_path('../archive/config/GOLDED.CFG');
}

// ── Tracer bullet ────────────────────────────────────────────────────────────

it('extracts username from GOLDED.CFG', function (): void {
    $config = (new GoldedConfigParser)->parse(cfgPath());

    expect($config['username'])->toBe('Odinn Sorensen');
});

// ── Field extraction ─────────────────────────────────────────────────────────

it('extracts address', function (): void {
    $config = (new GoldedConfigParser)->parse(cfgPath());

    expect($config['address'])->toBe('2:236/77');
});

it('extracts global charset_import', function (): void {
    $config = (new GoldedConfigParser)->parse(cfgPath());

    expect($config['charset_import'])->toBe('CP850');
});

it('extracts multiple origin lines', function (): void {
    $config = (new GoldedConfigParser)->parse(cfgPath());

    expect($config['origins'])
        ->toBeArray()
        ->toContain('http://www.goldware.dk * e-mail: odinn@goldware.dk');
    expect($config['origins'])->not()->toBeEmpty();
});

it('extracts tearline', function (): void {
    $config = (new GoldedConfigParser)->parse(cfgPath());

    expect($config['tearline'])->toBe('@longpid @version');
});

// ── Conditional blocks ───────────────────────────────────────────────────────

it('skips IF 0 blocks', function (): void {
    $config = (new GoldedConfigParser)->parse(cfgPath());

    // All TAGLINE entries in GOLDED.CFG live inside IF 0 — should be empty.
    expect($config['taglines'])->toBeEmpty();

    // Parser should still complete cleanly with correct global values.
    expect($config['username'])->not()->toBeNull();
    expect($config['charset_import'])->not()->toBeNull();
});

// ── Artisan command ──────────────────────────────────────────────────────────

it('writes config/golded.php via artisan command', function (): void {
    $output = base_path('config/golded.php');

    $this->artisan('golded:config', ['path' => cfgPath()])
        ->assertSuccessful();

    expect(file_exists($output))->toBeTrue();
    $written = require $output;

    expect($written['username'])->toBe('Odinn Sorensen');
    expect($written['address'])->toBe('2:236/77');
});
