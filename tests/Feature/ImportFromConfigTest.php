<?php

use App\Models\Area;
use App\Models\Message;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Override golded config to a single controlled area for test isolation.
 * Uses real archive fixture data, but limits scope to one area.
 */
function withSingleSquishArea(callable $callback): void
{
    config([
        'golded.areas' => [
            'M:\SQUISH\TEST\STEST1' => [
                'echoid' => 'TEST.SQUISH',
                'description' => 'Test Squish area',
                'group_id' => 'T',
                'area_type' => 'Echo',
                'format' => 'squish',
            ],
        ],
    ]);

    $callback();
}

// ── Tracer bullet ─────────────────────────────────────────────────────────────

it('imports a Squish area by its echoid from config', function () {
    withSingleSquishArea(function () {
        $root = base_path('../archive/messages');

        $this->artisan('golded:import-config', ['--root' => $root])
            ->assertExitCode(0);

        expect(Area::where('code', 'TEST.SQUISH')->exists())->toBeTrue()
            ->and(Message::count())->toBeGreaterThan(0);
    });
});

// ── Idempotency ───────────────────────────────────────────────────────────────

it('running import-config twice does not duplicate areas or messages', function () {
    withSingleSquishArea(function () {
        $root = base_path('../archive/messages');

        $this->artisan('golded:import-config', ['--root' => $root])->assertExitCode(0);
        $areaCount = Area::count();
        $messageCount = Message::count();

        $this->artisan('golded:import-config', ['--root' => $root])->assertExitCode(0);

        expect(Area::count())->toBe($areaCount)
            ->and(Message::count())->toBe($messageCount);
    });
});

// ── Integer key (Hudson board) skipping ───────────────────────────────────────

it('skips integer-keyed config entries without error', function () {
    config([
        'golded.areas' => [
            1 => ['echoid' => 'HUDSON.1', 'format' => 'hudson'],
            2 => ['echoid' => 'HUDSON.2', 'format' => 'hudson'],
            'M:\SQUISH\TEST\STEST1' => [
                'echoid' => 'TEST.SQUISH',
                'description' => 'Test Squish area',
                'group_id' => 'T',
                'area_type' => 'Echo',
                'format' => 'squish',
            ],
        ],
    ]);

    $this->artisan('golded:import-config', ['--root' => base_path('../archive/messages')])
        ->assertExitCode(0);

    expect(Area::where('code', 'HUDSON.1')->exists())->toBeFalse()
        ->and(Area::where('code', 'TEST.SQUISH')->exists())->toBeTrue();
});
