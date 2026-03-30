<?php

use App\Models\Area;
use App\Models\Dataset;
use App\Models\Message;
use Livewire\Livewire;

// ── Area list ─────────────────────────────────────────────────────────────────

it('starts with selection at index 0', function () {
    Livewire::test('pages::golded-shell')
        ->assertSet('selectionIndex', 0);
});

it('moves selection down on ArrowDown', function () {
    $dataset = Dataset::factory()->create();
    Area::factory()->for($dataset)->count(3)->create();

    Livewire::test('pages::golded-shell')
        ->call('handleKey', 'ArrowDown')
        ->assertSet('selectionIndex', 1);
});

it('does not move selection above 0 on ArrowUp', function () {
    $dataset = Dataset::factory()->create();
    Area::factory()->for($dataset)->count(3)->create();

    Livewire::test('pages::golded-shell')
        ->call('handleKey', 'ArrowUp')
        ->assertSet('selectionIndex', 0);
});

it('clamps selection at last item on ArrowDown', function () {
    $dataset = Dataset::factory()->create();
    Area::factory()->for($dataset)->count(2)->create();

    Livewire::test('pages::golded-shell')
        ->call('handleKey', 'ArrowDown')
        ->call('handleKey', 'ArrowDown')
        ->call('handleKey', 'ArrowDown')
        ->assertSet('selectionIndex', 1);
});

it('opens area on Enter', function () {
    $dataset = Dataset::factory()->create();
    $area = Area::factory()->for($dataset)->create(['sort_order' => 1]);

    Livewire::test('pages::golded-shell')
        ->call('handleKey', 'Enter')
        ->assertSet('screen', 'messages')
        ->assertSet('areaId', $area->id)
        ->assertSet('selectionIndex', 0);
});

it('opens area on ArrowRight', function () {
    $dataset = Dataset::factory()->create();
    $area = Area::factory()->for($dataset)->create(['sort_order' => 1]);

    Livewire::test('pages::golded-shell')
        ->call('handleKey', 'ArrowRight')
        ->assertSet('screen', 'messages')
        ->assertSet('areaId', $area->id);
});

it('opens the area at the current selection index', function () {
    $dataset = Dataset::factory()->create();
    Area::factory()->for($dataset)->create(['sort_order' => 1]);
    $second = Area::factory()->for($dataset)->create(['sort_order' => 2]);

    Livewire::test('pages::golded-shell')
        ->call('handleKey', 'ArrowDown')
        ->call('handleKey', 'Enter')
        ->assertSet('areaId', $second->id);
});

// ── Message list ──────────────────────────────────────────────────────────────

it('renders message subjects when in the messages screen', function () {
    $dataset = Dataset::factory()->create();
    $area = Area::factory()->for($dataset)->create(['sort_order' => 1]);
    Message::factory()->for($area)->for($dataset)->create(['subject' => 'Help with GoldED config', 'msgno' => 1]);
    Message::factory()->for($area)->for($dataset)->create(['subject' => 'Nodelist update available', 'msgno' => 2]);

    Livewire::test('pages::golded-shell')
        ->call('handleKey', 'Enter') // open first area
        ->assertSee('Help with GoldED config')
        ->assertSee('Nodelist update available');
});

it('navigates up and down in message list', function () {
    $dataset = Dataset::factory()->create();
    $area = Area::factory()->for($dataset)->create(['sort_order' => 1]);
    Message::factory()->for($area)->for($dataset)->count(3)->create();

    Livewire::test('pages::golded-shell')
        ->call('handleKey', 'Enter')
        ->call('handleKey', 'ArrowDown')
        ->assertSet('selectionIndex', 1)
        ->call('handleKey', 'ArrowUp')
        ->assertSet('selectionIndex', 0);
});

it('goes back to areas from message list on ArrowLeft', function () {
    $dataset = Dataset::factory()->create();
    Area::factory()->for($dataset)->create();

    Livewire::test('pages::golded-shell')
        ->call('handleKey', 'Enter')
        ->call('handleKey', 'ArrowLeft')
        ->assertSet('screen', 'areas');
});

it('goes back to areas from message list on Escape', function () {
    $dataset = Dataset::factory()->create();
    Area::factory()->for($dataset)->create();

    Livewire::test('pages::golded-shell')
        ->call('handleKey', 'Enter')
        ->call('handleKey', 'Escape')
        ->assertSet('screen', 'areas');
});

// ── Reader ────────────────────────────────────────────────────────────────────

it('opens reader on Enter from message list', function () {
    $dataset = Dataset::factory()->create();
    $area = Area::factory()->for($dataset)->create(['sort_order' => 1]);
    $msg = Message::factory()->for($area)->for($dataset)->create(['msgno' => 1]);

    Livewire::test('pages::golded-shell')
        ->call('handleKey', 'Enter') // open area
        ->call('handleKey', 'Enter') // open message
        ->assertSet('screen', 'reader')
        ->assertSet('messageId', $msg->id);
});

it('renders message subject and body in reader', function () {
    $dataset = Dataset::factory()->create();
    $area = Area::factory()->for($dataset)->create(['sort_order' => 1]);
    Message::factory()->for($area)->for($dataset)->create([
        'msgno' => 1,
        'subject' => 'Re: Pan Galactic Gargle Blaster',
        'body_text' => 'Always carry a towel.',
    ]);

    Livewire::test('pages::golded-shell')
        ->call('handleKey', 'Enter')
        ->call('handleKey', 'Enter')
        ->assertSee('Re: Pan Galactic Gargle Blaster')
        ->assertSee('Always carry a towel.');
});

it('goes back to messages from reader on Escape', function () {
    $dataset = Dataset::factory()->create();
    $area = Area::factory()->for($dataset)->create(['sort_order' => 1]);
    Message::factory()->for($area)->for($dataset)->create(['msgno' => 1]);

    Livewire::test('pages::golded-shell')
        ->call('handleKey', 'Enter')
        ->call('handleKey', 'Enter')
        ->call('handleKey', 'Escape')
        ->assertSet('screen', 'messages');
});

it('navigates to next message in reader on ArrowRight', function () {
    $dataset = Dataset::factory()->create();
    $area = Area::factory()->for($dataset)->create(['sort_order' => 1]);
    $first = Message::factory()->for($area)->for($dataset)->create(['msgno' => 1]);
    $second = Message::factory()->for($area)->for($dataset)->create(['msgno' => 2]);

    Livewire::test('pages::golded-shell')
        ->call('handleKey', 'Enter')
        ->call('handleKey', 'Enter')
        ->call('handleKey', 'ArrowRight')
        ->assertSet('messageId', $second->id);
});

it('navigates to previous message in reader on ArrowLeft', function () {
    $dataset = Dataset::factory()->create();
    $area = Area::factory()->for($dataset)->create(['sort_order' => 1]);
    $first = Message::factory()->for($area)->for($dataset)->create(['msgno' => 1]);
    $second = Message::factory()->for($area)->for($dataset)->create(['msgno' => 2]);

    Livewire::test('pages::golded-shell')
        ->call('handleKey', 'Enter')
        ->call('handleKey', 'ArrowDown')
        ->call('handleKey', 'Enter')
        ->call('handleKey', 'ArrowLeft')
        ->assertSet('messageId', $first->id);
});

it('scrolls down in reader on ArrowDown', function () {
    $dataset = Dataset::factory()->create();
    $area = Area::factory()->for($dataset)->create(['sort_order' => 1]);
    Message::factory()->for($area)->for($dataset)->create(['msgno' => 1]);

    Livewire::test('pages::golded-shell')
        ->call('handleKey', 'Enter')
        ->call('handleKey', 'Enter')
        ->call('handleKey', 'ArrowDown')
        ->assertSet('scrollOffset', 1);
});

it('does not scroll above 0 in reader', function () {
    $dataset = Dataset::factory()->create();
    $area = Area::factory()->for($dataset)->create(['sort_order' => 1]);
    Message::factory()->for($area)->for($dataset)->create(['msgno' => 1]);

    Livewire::test('pages::golded-shell')
        ->call('handleKey', 'Enter')
        ->call('handleKey', 'Enter')
        ->call('handleKey', 'ArrowUp')
        ->assertSet('scrollOffset', 0);
});

it('renders area names from the database', function () {
    $dataset = Dataset::factory()->create();
    Area::factory()->for($dataset)->create(['name' => 'Galactic Transmissions', 'sort_order' => 1]);
    Area::factory()->for($dataset)->create(['name' => 'Interplanetary Gossip', 'sort_order' => 2]);

    Livewire::test('pages::golded-shell')
        ->assertSee('Galactic Transmissions')
        ->assertSee('Interplanetary Gossip');
});
