<?php

use App\Import\MsgImporter;
use App\Models\Area;
use App\Models\Message;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function makeMsgArea(string $name = 'THE_SAFE'): array
{
    $tmpDir = sys_get_temp_dir().'/golded_test_'.uniqid();
    mkdir($tmpDir);

    return ['dir' => $tmpDir, 'name' => $name];
}

function sampleMsgPath(int $msgno = 1): string
{
    return base_path("samples/msg/DEMO/{$msgno}.msg");
}

function copySampleMsg(string $dir, int $msgno = 1): void
{
    $src = sampleMsgPath($msgno);
    copy($src, "{$dir}/{$msgno}.msg");
}

afterEach(function (): void {
    // Clean up temp dirs
    foreach (glob(sys_get_temp_dir().'/golded_test_*') as $dir) {
        array_map(unlink(...), glob("{$dir}/*"));
        rmdir($dir);
    }
});

// ── Tracer bullet ────────────────────────────────────────────────────────────

it('imports from_name from a sample .msg file', function (): void {
    ['dir' => $dir] = makeMsgArea();
    copySampleMsg($dir);
    $area = Area::factory()->create([]);

    (new MsgImporter)->import($dir, $area);

    expect(Message::first()->from_name)->toBe('Demo Sysop');
});

// ── Header fields ─────────────────────────────────────────────────────────────

it('imports to_name and subject', function (): void {
    ['dir' => $dir] = makeMsgArea();
    copySampleMsg($dir);

    $area = Area::factory()->create();
    (new MsgImporter)->import($dir, $area);

    $msg = Message::first();
    expect($msg->to_name)->toBe('Future Reader')
        ->and($msg->subject)->toBe('GoldED 7 public sample');
});

it('imports body text including kludge lines', function (): void {
    ['dir' => $dir] = makeMsgArea();
    copySampleMsg($dir);

    $area = Area::factory()->create();
    (new MsgImporter)->import($dir, $area);

    $body = Message::first()->body_text;
    expect($body)->toContain("\x01")
        ->and($body)->toContain('This is a synthetic .MSG file.');
});

it('sets msgno from filename', function (): void {
    ['dir' => $dir] = makeMsgArea();
    copySampleMsg($dir);

    $area = Area::factory()->create();
    (new MsgImporter)->import($dir, $area);

    expect(Message::first()->msgno)->toBe(1);
});

it('returns count of imported messages', function (): void {
    ['dir' => $dir] = makeMsgArea();
    copySampleMsg($dir, 1);

    $area = Area::factory()->create();
    $count = (new MsgImporter)->import($dir, $area);

    expect($count)->toBe(1);
});

// ── MSGID deduplication ───────────────────────────────────────────────────────

it('populates external_id for all imported MSG messages', function (): void {
    ['dir' => $dir] = makeMsgArea();
    copySampleMsg($dir);
    $area = Area::factory()->create();

    (new MsgImporter)->import($dir, $area);

    expect(Message::whereNull('external_id')->count())->toBe(0);
});

it('uses the MSGID kludge as external_id when present in MSG file', function (): void {
    ['dir' => $dir] = makeMsgArea();
    copySampleMsg($dir);
    $area = Area::factory()->create();

    (new MsgImporter)->import($dir, $area);

    expect(Message::first()->external_id)->not->toStartWith('hash:');
});

it('re-importing the same MSG files is idempotent', function (): void {
    ['dir' => $dir] = makeMsgArea();
    copySampleMsg($dir);
    $area = Area::factory()->create();

    (new MsgImporter)->import($dir, $area);
    $count = Message::count();

    (new MsgImporter)->import($dir, $area);

    expect(Message::count())->toBe($count);
});

// ── Artisan command ───────────────────────────────────────────────────────────

it('imports all areas via artisan command', function (): void {
    $tmpBase = sys_get_temp_dir().'/golded_cmd_test_'.uniqid();
    mkdir("{$tmpBase}/DEMO", 0755, true);
    copy(sampleMsgPath(), "{$tmpBase}/DEMO/1.msg");

    $this->artisan('golded:import msg '.$tmpBase)->assertExitCode(0);

    expect(Message::count())->toBe(1);

    array_map(unlink(...), glob("{$tmpBase}/DEMO/*"));
    rmdir("{$tmpBase}/DEMO");
    rmdir($tmpBase);
});
