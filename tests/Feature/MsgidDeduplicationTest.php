<?php

use App\Models\Message;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('rejects a second message with the same non-null external_id', function (): void {
    Message::factory()->create(['external_id' => 'test:abc123']);

    expect(fn () => Message::factory()->create(['external_id' => 'test:abc123']))
        ->toThrow(UniqueConstraintViolationException::class);
});

it('allows multiple messages with null external_id', function (): void {
    Message::factory()->create(['external_id' => null]);
    Message::factory()->create(['external_id' => null]);

    expect(Message::whereNull('external_id')->count())->toBe(2);
});
