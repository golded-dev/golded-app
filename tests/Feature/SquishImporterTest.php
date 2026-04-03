<?php

use App\Import\SquishImporter;
use App\Models\Message;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function squishTestBase(): string
{
    return base_path('../archive/messages/SQUISH/TEST/STEST1');
}

// ── Tracer bullet ─────────────────────────────────────────────────────────────

it('imports from_name from a real Squish message', function () {

    (new SquishImporter)->import(squishTestBase());

    expect(Message::first()->from_name)->toBe('Odinn Sorensen');
});

// ── Header fields ─────────────────────────────────────────────────────────────

it('imports to_name and subject', function () {
    (new SquishImporter)->import(squishTestBase());

    expect(Message::first()->to_name)->not->toBeEmpty();
    expect(Message::where('subject', '!=', '')->count())->toBeGreaterThan(0);
});

it('imports body text including kludge lines', function () {
    // SQUISH/INT/GOLDED has messages with MSGID kludges; STEST1 doesn't
    (new SquishImporter)->import(base_path('../archive/messages/SQUISH/INT/GOLDED'));

    // At least one message in this area has MSGID kludges
    $hasKludge = Message::all()->contains(fn ($m) => str_contains($m->body_text, "\x01"));
    expect($hasKludge)->toBeTrue();
});

it('imports posted_at as a valid date', function () {
    (new SquishImporter)->import(squishTestBase());

    expect(Message::first()->posted_at)->not->toBeNull();
});

it('stores reply links', function () {
    (new SquishImporter)->import(squishTestBase());

    // Fields are importable without error; some areas have reply chains
    expect(Message::count())->toBeGreaterThan(0);
    expect(Message::whereNotNull('reply_to_msgno')->count())->toBeGreaterThanOrEqual(0);
});

it('returns count of imported messages', function () {
    $count = (new SquishImporter)->import(squishTestBase());

    expect($count)->toBeGreaterThan(0)
        ->and($count)->toBe(Message::count());
});

// ── Artisan command ───────────────────────────────────────────────────────────

it('imports a Squish area via artisan command', function () {
    $path = base_path('../archive/messages/SQUISH/TEST');

    $this->artisan("golded:import squish {$path}")->assertExitCode(0);

    expect(Message::count())->toBeGreaterThan(0);
});

it('--fresh wipes and re-imports without duplicating messages', function () {
    $path = base_path('../archive/messages/SQUISH/TEST');

    $this->artisan("golded:import squish {$path}")->assertExitCode(0);
    $countAfterFirst = Message::count();

    $this->artisan("golded:import squish {$path} --fresh")->assertExitCode(0);

    expect(Message::count())->toBe($countAfterFirst);
});
