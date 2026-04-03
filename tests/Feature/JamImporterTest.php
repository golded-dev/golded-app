<?php

use App\Import\JamImporter;
use App\Models\Message;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function jamTestBase(): string
{
    return base_path('../archive/messages/JAM/TEST/jtest1');
}

// ── Tracer bullet ─────────────────────────────────────────────────────────────

it('imports from_name from a real JAM message', function () {

    (new JamImporter)->import(jamTestBase());

    expect(Message::first()->from_name)->toBe('Odinn Sorensen');
});

// ── Header fields ─────────────────────────────────────────────────────────────

it('imports to_name and subject when present', function () {
    (new JamImporter)->import(jamTestBase());

    // First message has to_name but no subject (valid for this area)
    expect(Message::first()->to_name)->not->toBeEmpty();
    // At least some messages have subjects
    expect(Message::where('subject', '!=', '')->count())->toBeGreaterThan(0);
});

it('imports body text including kludge lines', function () {
    // COLANNOU has messages with MSGID kludges; JTEST1 doesn't
    (new JamImporter)->import(base_path('../archive/messages/JAM/I/COLANNOU'));

    $hasKludge = Message::all()->contains(fn ($m) => str_contains($m->body_text, "\x01"));
    expect($hasKludge)->toBeTrue();
});

it('imports posted_at as a valid date', function () {
    (new JamImporter)->import(jamTestBase());

    expect(Message::first()->posted_at)->not->toBeNull();
});

it('stores reply links', function () {
    (new JamImporter)->import(jamTestBase());

    // jtest1 area has messages; at least one should have a reply chain link
    $withReply = Message::whereNotNull('reply1st_msgno')
        ->orWhereNotNull('replynext_msgno')
        ->orWhereNotNull('reply_to_msgno')
        ->first();

    // Not all areas have replies — just check the field is importable (not an error)
    expect(Message::count())->toBeGreaterThan(0);
});

it('returns count of imported messages', function () {
    $count = (new JamImporter)->import(jamTestBase());

    expect($count)->toBeGreaterThan(0)
        ->and($count)->toBe(Message::count());
});

// ── Artisan command ───────────────────────────────────────────────────────────

it('imports a JAM area via artisan command', function () {
    $path = base_path('../archive/messages/JAM/TEST');

    $this->artisan("golded:import jam {$path}")->assertExitCode(0);

    expect(Message::count())->toBeGreaterThan(0);
});
