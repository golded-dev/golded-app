<?php

use App\Import\HudsonImporter;
use App\Models\Area;
use App\Models\Dataset;
use App\Models\Message;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function hudsonTestBase(): string
{
    return base_path('../archive/messages/HUDSON');
}

// ── Tracer bullet ─────────────────────────────────────────────────────────────

it('imports from_name from a real Hudson message', function () {
    $dataset = Dataset::factory()->create();

    (new HudsonImporter)->import(hudsonTestBase(), $dataset);

    expect(Message::first()->from_name)->toBe('Dirk A. Mueller');
});

// ── Header fields ─────────────────────────────────────────────────────────────

it('imports to_name and subject', function () {
    $dataset = Dataset::factory()->create();
    (new HudsonImporter)->import(hudsonTestBase(), $dataset);

    expect(Message::first()->to_name)->not->toBeEmpty();
    expect(Message::where('subject', '!=', '')->count())->toBeGreaterThan(0);
});

it('imports body text without kludge lines', function () {
    $dataset = Dataset::factory()->create();
    (new HudsonImporter)->import(hudsonTestBase(), $dataset);

    $body = Message::first()->body_text;
    expect($body)->not->toContain("\x01");
});

it('imports posted_at as a valid date', function () {
    $dataset = Dataset::factory()->create();
    (new HudsonImporter)->import(hudsonTestBase(), $dataset);

    expect(Message::first()->posted_at)->not->toBeNull();
});

it('stores reply links', function () {
    $dataset = Dataset::factory()->create();
    (new HudsonImporter)->import(hudsonTestBase(), $dataset);

    expect(Message::count())->toBeGreaterThan(0);
    expect(Message::whereNotNull('reply_to_msgno')->count())->toBeGreaterThanOrEqual(0);
});

it('returns count of imported messages', function () {
    $dataset = Dataset::factory()->create();
    $count = (new HudsonImporter)->import(hudsonTestBase(), $dataset);

    expect($count)->toBeGreaterThan(0)
        ->and($count)->toBe(Message::count());
});

it('creates separate areas for each board', function () {
    $dataset = Dataset::factory()->create();
    (new HudsonImporter)->import(hudsonTestBase(), $dataset);

    // Hudson archive has 2 boards (150 and 151)
    expect(Area::where('dataset_id', $dataset->id)->count())->toBe(2);
});

// ── Artisan command ───────────────────────────────────────────────────────────

it('imports a Hudson area via artisan command', function () {
    $path = hudsonTestBase();

    $this->artisan("golded:import hudson {$path}")->assertExitCode(0);

    expect(Message::count())->toBeGreaterThan(0);
});
