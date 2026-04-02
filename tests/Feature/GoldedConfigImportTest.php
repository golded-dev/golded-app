<?php

use App\Config\GoldedConfigParser;

function cfgPath(): string
{
    return base_path('../archive/config/GOLDED.CFG');
}

// ── Tracer bullet ────────────────────────────────────────────────────────────

it('extracts username from GOLDED.CFG', function () {
    $config = (new GoldedConfigParser)->parse(cfgPath());

    expect($config['username'])->toBe('Odinn Sorensen');
});

// ── Field extraction ─────────────────────────────────────────────────────────

it('extracts address', function () {
    $config = (new GoldedConfigParser)->parse(cfgPath());

    expect($config['address'])->toBe('2:236/77');
});

it('extracts global charset_import', function () {
    $config = (new GoldedConfigParser)->parse(cfgPath());

    expect($config['charset_import'])->toBe('CP850');
});

it('extracts multiple origin lines', function () {
    $config = (new GoldedConfigParser)->parse(cfgPath());

    expect($config['origins'])
        ->toBeArray()
        ->not->toBeEmpty()
        ->toContain('http://www.goldware.dk * e-mail: odinn@goldware.dk');
});

it('extracts tearline', function () {
    $config = (new GoldedConfigParser)->parse(cfgPath());

    expect($config['tearline'])->toBe('@longpid @version');
});

// ── Conditional blocks ───────────────────────────────────────────────────────

it('skips IF 0 blocks', function () {
    $config = (new GoldedConfigParser)->parse(cfgPath());

    // All TAGLINE entries in GOLDED.CFG live inside IF 0 — should be empty.
    expect($config['taglines'])->toBeEmpty();

    // Parser should still complete cleanly with correct global values.
    expect($config['username'])->not->toBeNull();
    expect($config['charset_import'])->not->toBeNull();
});

// ── Artisan command ──────────────────────────────────────────────────────────

it('writes config/golded.php via artisan command', function () {
    $output = base_path('config/golded.php');

    $this->artisan('golded:config', ['path' => cfgPath()])
        ->assertSuccessful();

    expect(file_exists($output))->toBeTrue();

    // Re-load fresh (opcache may cache old value, so we read raw)
    $written = eval('?>'.file_get_contents($output));
    $written = require $output;

    expect($written['username'])->toBe('Odinn Sorensen');
    expect($written['address'])->toBe('2:236/77');
});
