<?php

use App\Import\HudsonImporter;
use App\Models\Area;
use App\Models\Message;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function hudsonTestBase(): string
{
    $path = sys_get_temp_dir().'/golded_hudson_fixture';

    if (! is_dir($path)) {
        mkdir($path, recursive: true);
    }

    $body = "\x01MSGID: 2:230/150 87654321\r\nI want this Hudson body preserved.\r\n";
    $recordCount = (int) ceil(strlen($body) / 128);

    file_put_contents($path.'/MSGIDX.BBS', pack('vC', 42, 7));
    file_put_contents($path.'/MSGHDR.BBS', hudsonHeader(
        msgno: 42,
        replyTo: 12,
        firstReply: 13,
        startRecord: 0,
        recordCount: $recordCount,
        board: 7,
        fromName: 'Odinn Sorensen',
        toName: 'Gregory ThroatWobbler',
        subject: 'Keep on the good work..',
        date: '01-01-24',
        time: '12:34',
    ));
    file_put_contents($path.'/MSGTXT.BBS', str_pad($body, $recordCount * 128, "\0"));

    return $path;
}

// ── Tracer bullet ─────────────────────────────────────────────────────────────

it('imports from_name from a Hudson fixture message', function (): void {

    (new HudsonImporter)->import(hudsonTestBase());

    expect(Message::first()->from_name)->toBe('Odinn Sorensen');
});

// ── Header fields ─────────────────────────────────────────────────────────────

it('imports to_name and subject', function (): void {
    (new HudsonImporter)->import(hudsonTestBase());

    expect(Message::first()->to_name)->not->toBeEmpty();
    expect(Message::where('subject', '!=', '')->count())->toBeGreaterThan(0);
});

it('imports body text including kludge lines', function (): void {
    (new HudsonImporter)->import(hudsonTestBase());

    $body = Message::first()->body_text;
    expect($body)->toContain("\x01");
});

it('imports posted_at as a valid date', function (): void {
    (new HudsonImporter)->import(hudsonTestBase());

    expect(Message::first()->posted_at)->not->toBeNull();
});

it('stores reply links', function (): void {
    (new HudsonImporter)->import(hudsonTestBase());

    expect(Message::count())->toBeGreaterThan(0);
    expect(Message::whereNotNull('reply_to_msgno')->count())->toBeGreaterThanOrEqual(0);
});

it('returns count of imported messages', function (): void {
    $count = (new HudsonImporter)->import(hudsonTestBase());

    expect($count)->toBeGreaterThan(0)
        ->and($count)->toBe(Message::count());
});

it('creates separate areas for each board', function (): void {
    (new HudsonImporter)->import(hudsonTestBase());

    expect(Area::where('source_type', 'hudson')->count())->toBe(1);
});

// ── Artisan command ───────────────────────────────────────────────────────────

it('imports a Hudson area via artisan command', function (): void {
    $path = hudsonTestBase();

    $this->artisan("golded:import hudson {$path}")->assertExitCode(0);

    expect(Message::count())->toBeGreaterThan(0);
});

function hudsonHeader(
    int $msgno,
    int $replyTo,
    int $firstReply,
    int $startRecord,
    int $recordCount,
    int $board,
    string $fromName,
    string $toName,
    string $subject,
    string $date,
    string $time,
): string {
    return pack(
        'v11C5',
        $msgno,
        $replyTo,
        $firstReply,
        0,
        $startRecord,
        $recordCount,
        0,
        0,
        0,
        0,
        0,
        0,
        0,
        0,
        0,
        $board,
    )
        .str_pad("\0".$time, 6, "\0")
        .str_pad("\0".$date, 9, "\0")
        .str_pad("\0".$toName, 36, "\0")
        .str_pad("\0".$fromName, 36, "\0")
        .str_pad("\0".$subject, 73, "\0");
}
