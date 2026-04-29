<?php

use App\Models\Area;
use App\Models\Message;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('uses app model subclasses for configured FTN relationships', function (): void {
    $area = Area::factory()->create();
    $message = Message::factory()->for($area)->create();

    expect($area->messages()->first())->toBeInstanceOf(Message::class)
        ->and($message->area)->toBeInstanceOf(Area::class);
});
