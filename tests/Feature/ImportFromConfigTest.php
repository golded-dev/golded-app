<?php

use App\Models\Area;
use App\Models\Message;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function withSingleMsgArea(callable $callback): void
{
    config([
        'golded.areas' => [
            'M:\msg\DEMO' => [
                'echoid' => 'GOLDED.DEMO',
                'description' => 'Synthetic GoldED demo area',
                'group_id' => 'T',
                'area_type' => 'Echo',
                'format' => 'opus',
            ],
        ],
    ]);

    $callback();
}

// ── Tracer bullet ─────────────────────────────────────────────────────────────

it('imports a MSG area by its echoid from config', function (): void {
    withSingleMsgArea(function (): void {
        $root = base_path('samples');

        $this->artisan('golded:import-config', ['--root' => $root])
            ->assertExitCode(0);

        expect(Area::where('code', 'GOLDED.DEMO')->exists())->toBeTrue()
            ->and(Message::count())->toBe(1);
    });
});

// ── Idempotency ───────────────────────────────────────────────────────────────

it('running import-config twice does not duplicate areas or messages', function (): void {
    withSingleMsgArea(function (): void {
        $root = base_path('samples');

        $this->artisan('golded:import-config', ['--root' => $root])->assertExitCode(0);
        $areaCount = Area::count();
        $messageCount = Message::count();

        $this->artisan('golded:import-config', ['--root' => $root])->assertExitCode(0);

        expect(Area::count())->toBe($areaCount)
            ->and(Message::count())->toBe($messageCount);
    });
});

// ── Integer key (Hudson board) skipping ───────────────────────────────────────

it('skips integer-keyed config entries without error', function (): void {
    config([
        'golded.areas' => [
            1 => ['echoid' => 'HUDSON.1', 'format' => 'hudson'],
            2 => ['echoid' => 'HUDSON.2', 'format' => 'hudson'],
            'M:\msg\DEMO' => [
                'echoid' => 'GOLDED.DEMO',
                'description' => 'Synthetic GoldED demo area',
                'group_id' => 'T',
                'area_type' => 'Echo',
                'format' => 'opus',
            ],
        ],
    ]);

    $this->artisan('golded:import-config', ['--root' => base_path('samples')])
        ->assertExitCode(0);

    expect(Area::where('code', 'HUDSON.1')->exists())->toBeFalse()
        ->and(Area::where('code', 'GOLDED.DEMO')->exists())->toBeTrue();
});
