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

function copyRealMsg(string $dir, int $msgno, string $area = 'THE_SAFE'): void
{
    $src = base_path("../archive/messages/MSG/{$area}/{$msgno}.MSG");
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

it('imports from_name from a real .msg file', function (): void {
    ['dir' => $dir] = makeMsgArea();
    copyRealMsg($dir, 1);
    $area = Area::factory()->create([]);

    (new MsgImporter)->import($dir, $area);

    expect(Message::first()->from_name)->toBe("Odinn Sorensen's ME2");
});

// ── Header fields ─────────────────────────────────────────────────────────────

it('imports to_name and subject', function (): void {
    ['dir' => $dir] = makeMsgArea();
    copyRealMsg($dir, 1);

    $area = Area::factory()->create();
    (new MsgImporter)->import($dir, $area);

    $msg = Message::first();
    expect($msg->to_name)->toBe('Gregory ThroatWobbler')
        ->and($msg->subject)->toBe('Keep on the good work..');
});

it('imports body text including kludge lines', function (): void {
    ['dir' => $dir] = makeMsgArea();
    copyRealMsg($dir, 1);

    $area = Area::factory()->create();
    (new MsgImporter)->import($dir, $area);

    $body = Message::first()->body_text;
    expect($body)->toContain("\x01")        // kludge lines preserved
        ->and($body)->toContain('want');    // real body content still present
});

it('sets msgno from filename', function (): void {
    ['dir' => $dir] = makeMsgArea();
    copyRealMsg($dir, 1);

    $area = Area::factory()->create();
    (new MsgImporter)->import($dir, $area);

    expect(Message::first()->msgno)->toBe(1);
});

it('returns count of imported messages', function (): void {
    ['dir' => $dir] = makeMsgArea();
    copyRealMsg($dir, 1);
    copyRealMsg($dir, 2);
    copyRealMsg($dir, 3);

    $area = Area::factory()->create();
    $count = (new MsgImporter)->import($dir, $area);

    expect($count)->toBe(3);
});

// ── MSGID deduplication ───────────────────────────────────────────────────────

it('populates external_id for all imported MSG messages', function (): void {
    ['dir' => $dir] = makeMsgArea();
    copyRealMsg($dir, 1);
    copyRealMsg($dir, 2);
    $area = Area::factory()->create();

    (new MsgImporter)->import($dir, $area);

    expect(Message::whereNull('external_id')->count())->toBe(0);
});

it('uses the MSGID kludge as external_id when present in MSG file', function (): void {
    ['dir' => $dir] = makeMsgArea();
    copyRealMsg($dir, 1); // THE_SAFE/1.MSG has MSGID kludge in body
    $area = Area::factory()->create();

    (new MsgImporter)->import($dir, $area);

    expect(Message::first()->external_id)->not->toStartWith('hash:');
});

it('re-importing the same MSG files is idempotent', function (): void {
    ['dir' => $dir] = makeMsgArea();
    copyRealMsg($dir, 1);
    copyRealMsg($dir, 2);
    $area = Area::factory()->create();

    (new MsgImporter)->import($dir, $area);
    $count = Message::count();

    (new MsgImporter)->import($dir, $area);

    expect(Message::count())->toBe($count);
});

// ── Artisan command ───────────────────────────────────────────────────────────

it('imports all areas via artisan command', function (): void {
    $tmpBase = sys_get_temp_dir().'/golded_cmd_test_'.uniqid();
    mkdir("{$tmpBase}/THE_SAFE", 0755, true);
    mkdir("{$tmpBase}/NETMAIL", 0755, true);
    copy(base_path('../archive/messages/MSG/THE_SAFE/1.MSG'), "{$tmpBase}/THE_SAFE/1.msg");
    copy(base_path('../archive/messages/MSG/NETMAIL/1.MSG'), "{$tmpBase}/NETMAIL/1.msg");

    $this->artisan('golded:import msg '.$tmpBase)->assertExitCode(0);

    expect(Message::count())->toBe(2);

    // Cleanup
    array_map(unlink(...), glob("{$tmpBase}/THE_SAFE/*"));
    array_map(unlink(...), glob("{$tmpBase}/NETMAIL/*"));
    rmdir("{$tmpBase}/THE_SAFE");
    rmdir("{$tmpBase}/NETMAIL");
    rmdir($tmpBase);
});
