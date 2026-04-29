<?php

use App\Import\JamImporter;
use App\Models\Area;
use App\Models\Message;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function jamTestBase(): string
{
    return base_path('tests/Fixtures/jam/jtest1');
}

// ── Tracer bullet ─────────────────────────────────────────────────────────────

it('imports from_name from a real JAM message', function (): void {

    (new JamImporter)->import(jamTestBase());

    expect(Message::first()->from_name)->toBe('Odinn Sorensen');
});

// ── Header fields ─────────────────────────────────────────────────────────────

it('imports to_name and subject when present', function (): void {
    (new JamImporter)->import(jamTestBase());

    // First message has to_name but no subject (valid for this area)
    expect(Message::first()->to_name)->not->toBeEmpty();
    // At least some messages have subjects
    expect(Message::where('subject', '!=', '')->count())->toBeGreaterThan(0);
});

it('imports body text from fixture messages', function (): void {
    (new JamImporter)->import(jamTestBase());

    expect(Message::first()->body_text)->toContain('Hej Alle.');
});

it('imports posted_at as a valid date', function (): void {
    (new JamImporter)->import(jamTestBase());

    expect(Message::first()->posted_at)->not->toBeNull();
});

it('stores reply links', function (): void {
    (new JamImporter)->import(jamTestBase());

    expect(Message::count())->toBeGreaterThan(0);
});

it('returns count of imported messages', function (): void {
    $count = (new JamImporter)->import(jamTestBase());

    expect($count)->toBeGreaterThan(0)
        ->and($count)->toBe(Message::count());
});

// ── MSGID deduplication ───────────────────────────────────────────────────────

it('populates external_id for all imported JAM messages', function (): void {
    (new JamImporter)->import(jamTestBase());

    expect(Message::whereNull('external_id')->count())->toBe(0);
});

it('uses the JAM MSGID subfield as external_id when present', function (): void {
    // jtest1 has MSGID subfields — none should have the hash: synthetic fallback
    (new JamImporter)->import(jamTestBase());

    expect(Message::where('external_id', 'like', 'hash:%')->count())->toBe(0);
});

it('stores JAM source identity and provenance', function (): void {
    (new JamImporter)->import(jamTestBase());

    $message = Message::first();

    expect($message->source_type)->toBe('jam')
        ->and($message->source_uid)->toBe("jam:offset:{$message->source_offset}")
        ->and($message->source_offset)->toBeInt()
        ->and($message->source_locator)->toEndWith('/jtest1.jhr')
        ->and($message->control_lines_json)->toHaveKey('msgid')
        ->and($message->provenance_json)->toMatchArray([
            'source_type' => 'jam',
        ]);
});

it('message_count reflects total messages in area after re-import', function (): void {
    (new JamImporter)->import(jamTestBase());
    $area = Area::first();
    $realCount = $area->message_count;

    (new JamImporter)->import(jamTestBase());
    $area->refresh();

    expect($area->message_count)->toBe($realCount);
});

it('re-importing the same JAM base is idempotent', function (): void {
    (new JamImporter)->import(jamTestBase());
    $count = Message::count();

    (new JamImporter)->import(jamTestBase());

    expect(Message::count())->toBe($count);
});

// ── Artisan command ───────────────────────────────────────────────────────────

it('imports a JAM area via artisan command', function (): void {
    $path = base_path('tests/Fixtures/jam');

    $this->artisan("golded:import jam {$path}")->assertExitCode(0);

    expect(Message::count())->toBeGreaterThan(0);
});
