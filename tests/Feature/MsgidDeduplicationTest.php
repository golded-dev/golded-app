<?php

use App\Models\Area;
use App\Models\Message;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('rejects duplicate source identity inside an area', function (): void {
    $area = Area::factory()->create();

    Message::factory()->for($area)->create([
        'source_type' => 'jam',
        'source_uid' => 'jam:offset:256',
    ]);

    expect(fn () => Message::factory()->for($area)->create([
        'source_type' => 'jam',
        'source_uid' => 'jam:offset:256',
    ]))->toThrow(UniqueConstraintViolationException::class);
});

it('rejects a repeated non-null external_id inside the same area', function (): void {
    $area = Area::factory()->create();

    Message::factory()->for($area)->create([
        'source_uid' => 'jam:offset:256',
        'external_id' => 'test:abc123',
    ]);

    expect(fn () => Message::factory()->for($area)->create([
        'source_uid' => 'jam:offset:512',
        'external_id' => 'test:abc123',
    ]))->toThrow(UniqueConstraintViolationException::class);
});

it('allows the same external_id in different areas', function (): void {
    $firstArea = Area::factory()->create();
    $secondArea = Area::factory()->create();

    Message::factory()->for($firstArea)->create(['external_id' => 'test:abc123']);
    Message::factory()->for($secondArea)->create(['external_id' => 'test:abc123']);

    expect(Message::where('external_id', 'test:abc123')->count())->toBe(2);
});

it('allows multiple messages with null external_id', function (): void {
    Message::factory()->create(['external_id' => null]);
    Message::factory()->create(['external_id' => null]);

    expect(Message::whereNull('external_id')->count())->toBe(2);
});
