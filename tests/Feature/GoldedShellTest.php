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

// ── PgUp / PgDn / Home / End ──────────────────────────────────────────────────

it('jumps selection forward 20 on PageDown in area list', function () {
    $dataset = Dataset::factory()->create();
    Area::factory()->for($dataset)->count(25)->create();

    Livewire::test('pages::golded-shell')
        ->call('handleKey', 'PageDown')
        ->assertSet('selectionIndex', 20);
});

it('clamps PageDown at last area', function () {
    $dataset = Dataset::factory()->create();
    Area::factory()->for($dataset)->count(5)->create();

    Livewire::test('pages::golded-shell')
        ->call('handleKey', 'PageDown')
        ->assertSet('selectionIndex', 4);
});

it('jumps selection to 0 on Home in area list', function () {
    $dataset = Dataset::factory()->create();
    Area::factory()->for($dataset)->count(5)->create();

    Livewire::test('pages::golded-shell')
        ->call('handleKey', 'ArrowDown')
        ->call('handleKey', 'ArrowDown')
        ->call('handleKey', 'Home')
        ->assertSet('selectionIndex', 0);
});

it('jumps selection to last on End in area list', function () {
    $dataset = Dataset::factory()->create();
    Area::factory()->for($dataset)->count(5)->create();

    Livewire::test('pages::golded-shell')
        ->call('handleKey', 'End')
        ->assertSet('selectionIndex', 4);
});

it('jumps selection forward 20 on PageDown in message list', function () {
    $dataset = Dataset::factory()->create();
    $area = Area::factory()->for($dataset)->create(['sort_order' => 1]);
    Message::factory()->for($area)->for($dataset)->count(25)->sequence(fn ($s) => ['msgno' => $s->index + 1])->create();

    Livewire::test('pages::golded-shell')
        ->call('handleKey', 'Enter') // open area
        ->call('handleKey', 'PageDown')
        ->assertSet('selectionIndex', 20);
});

it('scrolls reader body forward 18 lines on PageDown', function () {
    $dataset = Dataset::factory()->create();
    $area = Area::factory()->for($dataset)->create(['sort_order' => 1]);
    Message::factory()->for($area)->for($dataset)->create([
        'msgno' => 1,
        'body_text' => implode("\n", array_fill(0, 40, 'line')),
    ]);

    Livewire::test('pages::golded-shell')
        ->call('handleKey', 'Enter')
        ->call('handleKey', 'Enter')
        ->call('handleKey', 'PageDown')
        ->assertSet('scrollOffset', 18);
});

it('scrolls reader body back 18 lines on PageUp', function () {
    $dataset = Dataset::factory()->create();
    $area = Area::factory()->for($dataset)->create(['sort_order' => 1]);
    Message::factory()->for($area)->for($dataset)->create([
        'msgno' => 1,
        'body_text' => implode("\n", array_fill(0, 40, 'line')),
    ]);

    Livewire::test('pages::golded-shell')
        ->call('handleKey', 'Enter')
        ->call('handleKey', 'Enter')
        ->call('handleKey', 'PageDown')
        ->call('handleKey', 'PageDown')
        ->call('handleKey', 'PageUp')
        ->assertSet('scrollOffset', 18);
});

it('jumps reader to top on Home', function () {
    $dataset = Dataset::factory()->create();
    $area = Area::factory()->for($dataset)->create(['sort_order' => 1]);
    Message::factory()->for($area)->for($dataset)->create([
        'msgno' => 1,
        'body_text' => implode("\n", array_fill(0, 40, 'line')),
    ]);

    Livewire::test('pages::golded-shell')
        ->call('handleKey', 'Enter')
        ->call('handleKey', 'Enter')
        ->call('handleKey', 'PageDown')
        ->call('handleKey', 'Home')
        ->assertSet('scrollOffset', 0);
});

// ── Reply navigation ──────────────────────────────────────────────────────────

it('navigates to parent message with -', function () {
    $dataset = Dataset::factory()->create();
    $area = Area::factory()->for($dataset)->create(['sort_order' => 1]);
    $parent = Message::factory()->for($area)->for($dataset)->create(['msgno' => 1]);
    $reply = Message::factory()->for($area)->for($dataset)->create([
        'msgno' => 2,
        'reply_to_msgno' => 1,
    ]);

    Livewire::test('pages::golded-shell')
        ->call('handleKey', 'Enter')          // open area
        ->call('handleKey', 'ArrowDown')       // select reply (index 1)
        ->call('handleKey', 'Enter')           // open reply in reader
        ->call('handleKey', '-')               // go to parent
        ->assertSet('messageId', $parent->id);
});

it('does nothing on - when message has no parent', function () {
    $dataset = Dataset::factory()->create();
    $area = Area::factory()->for($dataset)->create(['sort_order' => 1]);
    $msg = Message::factory()->for($area)->for($dataset)->create(['msgno' => 1, 'reply_to_msgno' => null]);

    Livewire::test('pages::golded-shell')
        ->call('handleKey', 'Enter')
        ->call('handleKey', 'Enter')
        ->call('handleKey', '-')
        ->assertSet('messageId', $msg->id);
});

it('navigates to first reply with +', function () {
    $dataset = Dataset::factory()->create();
    $area = Area::factory()->for($dataset)->create(['sort_order' => 1]);
    $parent = Message::factory()->for($area)->for($dataset)->create(['msgno' => 1, 'reply1st_msgno' => 2]);
    $reply = Message::factory()->for($area)->for($dataset)->create(['msgno' => 2, 'reply_to_msgno' => 1]);

    Livewire::test('pages::golded-shell')
        ->call('handleKey', 'Enter')
        ->call('handleKey', 'Enter') // open parent
        ->call('handleKey', '+')
        ->assertSet('messageId', $reply->id);
});

it('navigates to next sibling with *', function () {
    $dataset = Dataset::factory()->create();
    $area = Area::factory()->for($dataset)->create(['sort_order' => 1]);
    $parent = Message::factory()->for($area)->for($dataset)->create(['msgno' => 1, 'reply1st_msgno' => 2]);
    $first = Message::factory()->for($area)->for($dataset)->create(['msgno' => 2, 'reply_to_msgno' => 1, 'replynext_msgno' => 3]);
    $second = Message::factory()->for($area)->for($dataset)->create(['msgno' => 3, 'reply_to_msgno' => 1]);

    Livewire::test('pages::golded-shell')
        ->call('handleKey', 'Enter')
        ->call('handleKey', 'ArrowDown') // select msg 2
        ->call('handleKey', 'Enter')     // open first reply
        ->call('handleKey', '*')         // jump to sibling
        ->assertSet('messageId', $second->id);
});

// ── Quote colouring ───────────────────────────────────────────────────────────

it('applies quote CSS class to quoted lines in reader', function () {
    $dataset = Dataset::factory()->create();
    $area = Area::factory()->for($dataset)->create(['sort_order' => 1]);
    Message::factory()->for($area)->for($dataset)->create([
        'msgno' => 1,
        'body_text' => "> This is a quote\nAnd this is normal text",
    ]);

    Livewire::test('pages::golded-shell')
        ->call('handleKey', 'Enter')
        ->call('handleKey', 'Enter')
        ->assertSee('cga-blue-lgrey'); // Quote1 colour class
});

it('hides kludge lines in reader by default', function () {
    $dataset = Dataset::factory()->create();
    $area = Area::factory()->for($dataset)->create(['sort_order' => 1]);
    Message::factory()->for($area)->for($dataset)->create([
        'msgno' => 1,
        'body_text' => "\x01MSGID: 1:2/3 deadbeef\nHello world",
    ]);

    Livewire::test('pages::golded-shell')
        ->call('handleKey', 'Enter')
        ->call('handleKey', 'Enter')
        ->assertDontSee('cga-dgrey-lgrey'); // kludge colour not present
});

it('shows kludge lines after pressing K in reader', function () {
    $dataset = Dataset::factory()->create();
    $area = Area::factory()->for($dataset)->create(['sort_order' => 1]);
    Message::factory()->for($area)->for($dataset)->create([
        'msgno' => 1,
        'body_text' => "\x01MSGID: 1:2/3 deadbeef\nHello world",
    ]);

    Livewire::test('pages::golded-shell')
        ->call('handleKey', 'Enter')
        ->call('handleKey', 'Enter')
        ->call('handleKey', 'k')
        ->assertSee('cga-dgrey-lgrey'); // kludge colour now visible
});
