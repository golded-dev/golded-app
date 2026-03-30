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
    Area::factory()->for($dataset)->create(['echoid' => 'ALPHA', 'unread_count' => 0]);
    $second = Area::factory()->for($dataset)->create(['echoid' => 'BETA', 'unread_count' => 0]);

    Livewire::test('pages::golded-shell')
        ->call('handleKey', 'ArrowDown')
        ->call('handleKey', 'Enter')
        ->assertSet('areaId', $second->id);
});

// ── Message list ──────────────────────────────────────────────────────────────

it('renders thread tree prefix for replies in message list', function () {
    $dataset = Dataset::factory()->create();
    $area = Area::factory()->for($dataset)->create(['sort_order' => 1]);
    Message::factory()->for($area)->for($dataset)->create(['msgno' => 1, 'subject' => 'Original', 'reply_to_msgno' => null]);
    Message::factory()->for($area)->for($dataset)->create(['msgno' => 2, 'subject' => 'Reply', 'reply_to_msgno' => 1]);

    Livewire::test('pages::golded-shell')
        ->call('handleKey', 'Enter')
        ->assertSee('└'); // reply prefix visible
});

it('shows bookmark indicator on bookmarked message', function () {
    $dataset = Dataset::factory()->create();
    $area = Area::factory()->for($dataset)->create(['sort_order' => 1]);
    Message::factory()->for($area)->for($dataset)->create(['msgno' => 1, 'is_bookmarked' => true]);

    Livewire::test('pages::golded-shell')
        ->call('handleKey', 'Enter')
        ->assertSee('►');
});

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

// ── Unread tracking ───────────────────────────────────────────────────────────

it('marks a message as read when opened in reader', function () {
    $dataset = Dataset::factory()->create();
    $area = Area::factory()->for($dataset)->create(['sort_order' => 1]);
    $msg = Message::factory()->for($area)->for($dataset)->create(['msgno' => 1, 'is_read' => false]);

    Livewire::test('pages::golded-shell')
        ->call('handleKey', 'Enter') // open area
        ->call('handleKey', 'Enter'); // open message

    expect($msg->fresh()->is_read)->toBeTrue();
});

it('decrements area unread_count when a message is marked read', function () {
    $dataset = Dataset::factory()->create();
    $area = Area::factory()->for($dataset)->create(['sort_order' => 1, 'unread_count' => 2]);
    Message::factory()->for($area)->for($dataset)->create(['msgno' => 1, 'is_read' => false]);

    Livewire::test('pages::golded-shell')
        ->call('handleKey', 'Enter')
        ->call('handleKey', 'Enter');

    expect($area->fresh()->unread_count)->toBe(1);
});

it('does not decrement area unread_count when opening an already-read message', function () {
    $dataset = Dataset::factory()->create();
    $area = Area::factory()->for($dataset)->create(['sort_order' => 1, 'unread_count' => 0]);
    Message::factory()->for($area)->for($dataset)->create(['msgno' => 1, 'is_read' => true]);

    Livewire::test('pages::golded-shell')
        ->call('handleKey', 'Enter')
        ->call('handleKey', 'Enter');

    expect($area->fresh()->unread_count)->toBe(0);
});

it('sorts areas with unread messages above areas without', function () {
    $dataset = Dataset::factory()->create();
    Area::factory()->for($dataset)->create(['sort_order' => 1, 'echoid' => 'ALPHA', 'unread_count' => 0]);
    Area::factory()->for($dataset)->create(['sort_order' => 2, 'echoid' => 'BETA', 'unread_count' => 5]);

    $component = Livewire::test('pages::golded-shell');

    $areas = $component->get('areas');
    expect($areas->first()->echoid)->toBe('BETA');
    expect($areas->last()->echoid)->toBe('ALPHA');
});

it('renders area unread colour for areas with unread messages', function () {
    $dataset = Dataset::factory()->create();
    Area::factory()->for($dataset)->create(['name' => 'Busy Echo', 'unread_count' => 3]);

    Livewire::test('pages::golded-shell')
        ->assertSee('Busy Echo')
        ->assertSee('cga-blue-lgrey'); // unread row colour class
});

// ── @J toggle + next/prev unread ─────────────────────────────────────────────

it('toggles a read message back to unread with Alt+j', function () {
    $dataset = Dataset::factory()->create();
    $area = Area::factory()->for($dataset)->create(['sort_order' => 1, 'unread_count' => 0]);
    $msg = Message::factory()->for($area)->for($dataset)->create(['msgno' => 1, 'is_read' => true]);

    Livewire::test('pages::golded-shell')
        ->call('handleKey', 'Enter')
        ->call('handleKey', 'Enter') // opens reader — msg is already read, no change
        ->call('handleKey', 'Alt+j'); // toggle → unread

    expect($msg->fresh()->is_read)->toBeFalse();
    expect($area->fresh()->unread_count)->toBe(1);
});

it('toggles an unread message to read with Alt+j', function () {
    $dataset = Dataset::factory()->create();
    $area = Area::factory()->for($dataset)->create(['sort_order' => 1, 'unread_count' => 1]);
    $msg = Message::factory()->for($area)->for($dataset)->create(['msgno' => 1, 'is_read' => false]);

    Livewire::test('pages::golded-shell')
        ->call('handleKey', 'Enter')
        ->call('handleKey', 'Enter') // opens reader — marks read (unread_count → 0)
        ->call('handleKey', 'Alt+j'); // toggle → unread again

    expect($msg->fresh()->is_read)->toBeFalse();
});

it('jumps to next unread message with Alt+ArrowRight', function () {
    $dataset = Dataset::factory()->create();
    $area = Area::factory()->for($dataset)->create(['sort_order' => 1, 'unread_count' => 1]);
    $first = Message::factory()->for($area)->for($dataset)->create(['msgno' => 1, 'is_read' => true]);
    $second = Message::factory()->for($area)->for($dataset)->create(['msgno' => 2, 'is_read' => false]);

    Livewire::test('pages::golded-shell')
        ->call('handleKey', 'Enter')
        ->call('handleKey', 'Enter') // open first (already read)
        ->call('handleKey', 'Alt+ArrowRight') // jump to next unread
        ->assertSet('messageId', $second->id);
});

it('marks message as read when jumping to it via Alt+ArrowRight', function () {
    $dataset = Dataset::factory()->create();
    $area = Area::factory()->for($dataset)->create(['sort_order' => 1, 'unread_count' => 2]);
    Message::factory()->for($area)->for($dataset)->create(['msgno' => 1, 'is_read' => true]);
    $second = Message::factory()->for($area)->for($dataset)->create(['msgno' => 2, 'is_read' => false]);

    Livewire::test('pages::golded-shell')
        ->call('handleKey', 'Enter')
        ->call('handleKey', 'Enter')
        ->call('handleKey', 'Alt+ArrowRight');

    expect($second->fresh()->is_read)->toBeTrue();
});

it('renders area names from the database', function () {
    $dataset = Dataset::factory()->create();
    Area::factory()->for($dataset)->create(['name' => 'Galactic Transmissions', 'sort_order' => 1]);
    Area::factory()->for($dataset)->create(['name' => 'Interplanetary Gossip', 'sort_order' => 2]);

    Livewire::test('pages::golded-shell')
        ->assertSee('Galactic Transmissions')
        ->assertSee('Interplanetary Gossip');
});
