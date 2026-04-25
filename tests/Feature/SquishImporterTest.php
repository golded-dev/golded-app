<?php

use App\Import\SquishImporter;
use App\Models\Area;
use App\Models\Message;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function squishTestBase(): string
{
    $base = sys_get_temp_dir().'/golded_squish_fixture/stest1';

    if (! is_dir(dirname($base))) {
        mkdir(dirname($base), recursive: true);
    }

    $control = "\x01MSGID: 2:230/150 12345678\x00";
    $body = "I want this Squish body preserved.\r\n";
    $header = squishHeader(
        fromName: 'Odinn Sorensen',
        toName: 'Gregory ThroatWobbler',
        subject: 'Keep on the good work..',
        dateWritten: squishDate('2024-01-01 12:34:56'),
        replyTo: 7,
        firstReply: 9,
    );
    $frameOffset = 256;
    $frame = squishFrame(totsize: strlen($header.$control.$body), controlSize: strlen($control));

    file_put_contents($base.'.sqd', str_repeat("\0", $frameOffset).$frame.$header.$control.$body);
    file_put_contents($base.'.sqi', pack('lVV', $frameOffset, 1, 0));

    return $base;
}

// ── Tracer bullet ─────────────────────────────────────────────────────────────

it('imports from_name from a real Squish message', function (): void {

    (new SquishImporter)->import(squishTestBase());

    expect(Message::first()->from_name)->toBe('Odinn Sorensen');
});

// ── Header fields ─────────────────────────────────────────────────────────────

it('imports to_name and subject', function (): void {
    (new SquishImporter)->import(squishTestBase());

    expect(Message::first()->to_name)->not->toBeEmpty();
    expect(Message::where('subject', '!=', '')->count())->toBeGreaterThan(0);
});

it('imports body text including kludge lines', function (): void {
    (new SquishImporter)->import(squishTestBase());

    $hasKludge = Message::all()->contains(fn ($m): bool => str_contains((string) $m->body_text, "\x01"));
    expect($hasKludge)->toBeTrue();
});

it('imports posted_at as a valid date', function (): void {
    (new SquishImporter)->import(squishTestBase());

    expect(Message::first()->posted_at)->not->toBeNull();
});

it('stores reply links', function (): void {
    (new SquishImporter)->import(squishTestBase());

    expect(Message::count())->toBeGreaterThan(0);
    expect(Message::where('reply_to_msgno', 7)->count())->toBe(1);
});

it('returns count of imported messages', function (): void {
    $count = (new SquishImporter)->import(squishTestBase());

    expect($count)->toBeGreaterThan(0)
        ->and($count)->toBe(Message::count());
});

// ── MSGID deduplication ───────────────────────────────────────────────────────

it('populates external_id for all imported Squish messages', function (): void {
    (new SquishImporter)->import(squishTestBase());

    expect(Message::whereNull('external_id')->count())->toBe(0);
});

it('uses the MSGID kludge from the control block as external_id', function (): void {
    (new SquishImporter)->import(squishTestBase());

    expect(Message::where('external_id', 'like', 'hash:%')->count())->toBe(0);
});

it('uses the MSGID kludge as external_id when present in Squish message', function (): void {
    (new SquishImporter)->import(squishTestBase());

    expect(Message::where('external_id', 'not like', 'hash:%')->count())->toBeGreaterThan(0);
});

it('message_count reflects total messages in area after re-import', function (): void {
    (new SquishImporter)->import(squishTestBase());
    $area = Area::first();
    $realCount = $area->message_count;

    (new SquishImporter)->import(squishTestBase());
    $area->refresh();

    expect($area->message_count)->toBe($realCount);
});

it('re-importing the same Squish base is idempotent', function (): void {
    (new SquishImporter)->import(squishTestBase());
    $count = Message::count();

    (new SquishImporter)->import(squishTestBase());

    expect(Message::count())->toBe($count);
});

// ── Artisan command ───────────────────────────────────────────────────────────

it('imports a Squish area via artisan command', function (): void {
    $path = dirname(squishTestBase());

    $this->artisan("golded:import squish {$path}")->assertExitCode(0);

    expect(Message::count())->toBeGreaterThan(0);
});

it('--fresh wipes and re-imports without duplicating messages', function (): void {
    $path = dirname(squishTestBase());

    $this->artisan("golded:import squish {$path}")->assertExitCode(0);
    $countAfterFirst = Message::count();

    $this->artisan("golded:import squish {$path} --fresh")->assertExitCode(0);

    expect(Message::count())->toBe($countAfterFirst);
});

function squishFrame(int $totsize, int $controlSize): string
{
    return pack(
        'VllVVVvv',
        0xAFAE4453,
        0,
        0,
        $totsize,
        $totsize,
        $controlSize,
        0,
        0,
    );
}

function squishHeader(
    string $fromName,
    string $toName,
    string $subject,
    int $dateWritten,
    int $replyTo,
    int $firstReply,
): string {
    return pack('V', 0)
        .str_pad($fromName, 36, "\0")
        .str_pad($toName, 36, "\0")
        .str_pad($subject, 72, "\0")
        .str_repeat("\0", 16)
        .pack('V', $dateWritten)
        .pack('V', 0)
        .pack('v', 0)
        .pack('V', $replyTo)
        .pack('V9', $firstReply, 0, 0, 0, 0, 0, 0, 0, 0)
        .pack('V', 0)
        .str_repeat("\0", 20);
}

function squishDate(string $date): int
{
    $postedAt = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $date);

    if (! $postedAt instanceof DateTimeImmutable) {
        throw new RuntimeException('Invalid Squish fixture date.');
    }

    return (int) $postedAt->format('d')
        | ((int) $postedAt->format('m') << 5)
        | (((int) $postedAt->format('Y') - 1980) << 9)
        | (intdiv((int) $postedAt->format('s'), 2) << 16)
        | ((int) $postedAt->format('i') << 21)
        | ((int) $postedAt->format('H') << 27);
}
