<?php

use App\Models\Area;
use App\Models\Message;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('imports the public MSG sample after a fresh migration', function (): void {
    $this->artisan('golded:import msg samples/msg --fresh')
        ->assertExitCode(0);

    expect(Area::where('code', 'DEMO')->exists())->toBeTrue()
        ->and(Message::count())->toBeGreaterThanOrEqual(1);
});
