<?php

use App\Golded\GoldedState;
use App\Models\Area;
use App\Models\Message;

// ── Screen helpers ─────────────────────────────────────────────────────────────

function stateText(GoldedState $state): string
{
    $text = '';
    foreach ($state->currentScreen() as $row) {
        foreach ($row as [$t, $c]) {
            $text .= $t;
        }
    }

    return $text;
}

function stateHasClass(GoldedState $state, string $class): bool
{
    foreach ($state->currentScreen() as $row) {
        foreach ($row as [$t, $c]) {
            if ($c === $class) {
                return true;
            }
        }
    }

    return false;
}

// ── Area list ─────────────────────────────────────────────────────────────────

it('starts with selection at index 0', function (): void {
    $state = new GoldedState;
    expect($state->selectionIndex)->toBe(0);
});

it('moves selection down on ArrowDown', function (): void {
    Area::factory()->count(3)->create();

    $state = new GoldedState;
    $state->handleKey('ArrowDown');

    expect($state->selectionIndex)->toBe(1);
});

it('does not move selection above 0 on ArrowUp', function (): void {
    Area::factory()->count(3)->create();

    $state = new GoldedState;
    $state->handleKey('ArrowUp');

    expect($state->selectionIndex)->toBe(0);
});

it('clamps selection at last item on ArrowDown', function (): void {
    Area::factory()->count(2)->create();

    $state = new GoldedState;
    $state->handleKey('ArrowDown');
    $state->handleKey('ArrowDown');
    $state->handleKey('ArrowDown');

    expect($state->selectionIndex)->toBe(1);
});

it('opens area on Enter', function (): void {
    $area = Area::factory()->create(['sort_order' => 1]);

    $state = new GoldedState;
    $state->handleKey('Enter');

    expect($state->screen)->toBe('messages')
        ->and($state->areaId)->toBe($area->id)
        ->and($state->selectionIndex)->toBe(0);
});

it('opens area on ArrowRight', function (): void {
    $area = Area::factory()->create(['sort_order' => 1]);

    $state = new GoldedState;
    $state->handleKey('ArrowRight');

    expect($state->screen)->toBe('messages')
        ->and($state->areaId)->toBe($area->id);
});

it('opens the area at the current selection index', function (): void {
    $shared = ['source_type' => 'jam', 'area_type' => 'Echo', 'unread_count' => 0];
    Area::factory()->create(array_merge($shared, ['code' => 'FIRST', 'echoid' => 'ALPHA']));
    $second = Area::factory()->create(array_merge($shared, ['code' => 'SECOND', 'echoid' => 'BETA']));

    $state = new GoldedState;
    $state->handleKey('ArrowDown');
    $state->handleKey('Enter');

    expect($state->areaId)->toBe($second->id);
});

// ── Message list ──────────────────────────────────────────────────────────────

it('renders thread tree prefix for replies in message list', function (): void {
    $area = Area::factory()->create(['sort_order' => 1]);
    Message::factory()->for($area)->create(['msgno' => 1, 'subject' => 'Original', 'reply_to_msgno' => null]);
    Message::factory()->for($area)->create(['msgno' => 2, 'subject' => 'Reply', 'reply_to_msgno' => 1]);

    $state = new GoldedState;
    $state->handleKey('Enter');

    expect(stateText($state))->toContain('└');
});

it('shows bookmark indicator on bookmarked message', function (): void {
    $area = Area::factory()->create(['sort_order' => 1]);
    Message::factory()->for($area)->create(['msgno' => 1, 'is_bookmarked' => true]);

    $state = new GoldedState;
    $state->handleKey('Enter');

    expect(stateText($state))->toContain('►');
});

it('renders message subjects when in the messages screen', function (): void {
    $area = Area::factory()->create(['sort_order' => 1]);
    Message::factory()->for($area)->create(['subject' => 'Help with GoldED config', 'msgno' => 1]);
    Message::factory()->for($area)->create(['subject' => 'Nodelist update available', 'msgno' => 2]);

    $state = new GoldedState;
    $state->handleKey('Enter');

    expect(stateText($state))
        ->toContain('Help with GoldED config')
        ->toContain('Nodelist update available');
});

it('navigates up and down in message list', function (): void {
    $area = Area::factory()->create(['sort_order' => 1]);
    Message::factory()->for($area)->count(3)->create();

    $state = new GoldedState;
    $state->handleKey('Enter');
    $state->handleKey('ArrowDown');

    expect($state->selectionIndex)->toBe(1);

    $state->handleKey('ArrowUp');

    expect($state->selectionIndex)->toBe(0);
});

it('goes back to areas from message list on ArrowLeft', function (): void {
    Area::factory()->create();

    $state = new GoldedState;
    $state->handleKey('Enter');
    $state->handleKey('ArrowLeft');

    expect($state->screen)->toBe('areas');
});

it('goes back to areas from message list on Escape', function (): void {
    Area::factory()->create();

    $state = new GoldedState;
    $state->handleKey('Enter');
    $state->handleKey('Escape');

    expect($state->screen)->toBe('areas');
});

// ── Reader ────────────────────────────────────────────────────────────────────

it('opens reader on Enter from message list', function (): void {
    $area = Area::factory()->create(['sort_order' => 1]);
    $msg = Message::factory()->for($area)->create(['msgno' => 1]);

    $state = new GoldedState;
    $state->handleKey('Enter');
    $state->handleKey('Enter');

    expect($state->screen)->toBe('reader')
        ->and($state->messageId)->toBe($msg->id);
});

it('renders message subject and body in reader', function (): void {
    $area = Area::factory()->create(['sort_order' => 1]);
    Message::factory()->for($area)->create([
        'msgno' => 1,
        'subject' => 'Re: Pan Galactic Gargle Blaster',
        'body_text' => 'Always carry a towel.',
    ]);

    $state = new GoldedState;
    $state->handleKey('Enter');
    $state->handleKey('Enter');

    expect(stateText($state))
        ->toContain('Re: Pan Galactic Gargle Blaster')
        ->toContain('Always carry a towel.');
});

it('goes back to messages from reader on Escape', function (): void {
    $area = Area::factory()->create(['sort_order' => 1]);
    Message::factory()->for($area)->create(['msgno' => 1]);

    $state = new GoldedState;
    $state->handleKey('Enter');
    $state->handleKey('Enter');
    $state->handleKey('Escape');

    expect($state->screen)->toBe('messages');
});

it('navigates to next message in reader on ArrowRight', function (): void {
    $area = Area::factory()->create(['sort_order' => 1]);
    Message::factory()->for($area)->create(['msgno' => 1]);
    $second = Message::factory()->for($area)->create(['msgno' => 2]);

    $state = new GoldedState;
    $state->handleKey('Enter');
    $state->handleKey('Enter');
    $state->handleKey('ArrowRight');

    expect($state->messageId)->toBe($second->id);
});

it('navigates to previous message in reader on ArrowLeft', function (): void {
    $area = Area::factory()->create(['sort_order' => 1]);
    $first = Message::factory()->for($area)->create(['msgno' => 1]);
    Message::factory()->for($area)->create(['msgno' => 2]);

    $state = new GoldedState;
    $state->handleKey('Enter');
    $state->handleKey('ArrowDown');
    $state->handleKey('Enter');
    $state->handleKey('ArrowLeft');

    expect($state->messageId)->toBe($first->id);
});

it('scrolls down in reader on ArrowDown', function (): void {
    $area = Area::factory()->create(['sort_order' => 1]);
    Message::factory()->for($area)->create(['msgno' => 1]);

    $state = new GoldedState;
    $state->handleKey('Enter');
    $state->handleKey('Enter');
    $state->handleKey('ArrowDown');

    expect($state->scrollOffset)->toBe(1);
});

it('does not scroll above 0 in reader', function (): void {
    $area = Area::factory()->create(['sort_order' => 1]);
    Message::factory()->for($area)->create(['msgno' => 1]);

    $state = new GoldedState;
    $state->handleKey('Enter');
    $state->handleKey('Enter');
    $state->handleKey('ArrowUp');

    expect($state->scrollOffset)->toBe(0);
});

// ── Unread tracking ───────────────────────────────────────────────────────────

it('marks a message as read when opened in reader', function (): void {
    $area = Area::factory()->create(['sort_order' => 1]);
    $msg = Message::factory()->for($area)->create(['msgno' => 1, 'is_read' => false]);

    $state = new GoldedState;
    $state->handleKey('Enter');
    $state->handleKey('Enter');

    expect($msg->fresh()->is_read)->toBeTrue();
});

it('decrements area unread_count when a message is marked read', function (): void {
    $area = Area::factory()->create(['sort_order' => 1, 'unread_count' => 2]);
    Message::factory()->for($area)->create(['msgno' => 1, 'is_read' => false]);

    $state = new GoldedState;
    $state->handleKey('Enter');
    $state->handleKey('Enter');

    expect($area->fresh()->unread_count)->toBe(1);
});

it('does not decrement area unread_count when opening an already-read message', function (): void {
    $area = Area::factory()->create(['sort_order' => 1, 'unread_count' => 0]);
    Message::factory()->for($area)->create(['msgno' => 1, 'is_read' => true]);

    $state = new GoldedState;
    $state->handleKey('Enter');
    $state->handleKey('Enter');

    expect($area->fresh()->unread_count)->toBe(0);
});

it('sorts areas with unread messages above areas without', function (): void {
    Area::factory()->create(['sort_order' => 1, 'echoid' => 'ALPHA', 'unread_count' => 0]);
    Area::factory()->create(['sort_order' => 2, 'echoid' => 'BETA', 'unread_count' => 5]);

    $state = new GoldedState;
    $areas = $state->areas();

    expect($areas->first()->echoid)->toBe('BETA')
        ->and($areas->last()->echoid)->toBe('ALPHA');
});

it('renders area unread colour for areas with unread messages', function (): void {
    Area::factory()->create(['name' => 'Busy Echo', 'unread_count' => 3, 'message_count' => 10]);
    Area::factory()->create(['unread_count' => 10, 'message_count' => 50]);

    $state = new GoldedState;

    expect(stateText($state))->toContain('Busy Echo')
        ->and(stateHasClass($state, 'cga-blue-lgrey'))->toBeTrue();
});

// ── @J toggle + next/prev unread ─────────────────────────────────────────────

it('toggles a read message back to unread with Alt+j', function (): void {
    $area = Area::factory()->create(['sort_order' => 1, 'unread_count' => 0]);
    $msg = Message::factory()->for($area)->create(['msgno' => 1, 'is_read' => true]);

    $state = new GoldedState;
    $state->handleKey('Enter');
    $state->handleKey('Enter');
    $state->handleKey('Alt+j');

    expect($msg->fresh()->is_read)->toBeFalse()
        ->and($area->fresh()->unread_count)->toBe(1);
});

it('toggles an unread message to read with Alt+j', function (): void {
    $area = Area::factory()->create(['sort_order' => 1, 'unread_count' => 1]);
    $msg = Message::factory()->for($area)->create(['msgno' => 1, 'is_read' => false]);

    $state = new GoldedState;
    $state->handleKey('Enter');
    $state->handleKey('Enter');
    $state->handleKey('Alt+j');

    expect($msg->fresh()->is_read)->toBeFalse();
});

it('jumps to next unread message with Alt+ArrowRight', function (): void {
    $area = Area::factory()->create(['sort_order' => 1, 'unread_count' => 1]);
    Message::factory()->for($area)->create(['msgno' => 1, 'is_read' => true]);
    $second = Message::factory()->for($area)->create(['msgno' => 2, 'is_read' => false]);

    $state = new GoldedState;
    $state->handleKey('Enter');
    $state->handleKey('Enter');
    $state->handleKey('Alt+ArrowRight');

    expect($state->messageId)->toBe($second->id);
});

it('marks message as read when jumping to it via Alt+ArrowRight', function (): void {
    $area = Area::factory()->create(['sort_order' => 1, 'unread_count' => 2]);
    Message::factory()->for($area)->create(['msgno' => 1, 'is_read' => true]);
    $second = Message::factory()->for($area)->create(['msgno' => 2, 'is_read' => false]);

    $state = new GoldedState;
    $state->handleKey('Enter');
    $state->handleKey('Enter');
    $state->handleKey('Alt+ArrowRight');

    expect($second->fresh()->is_read)->toBeTrue();
});

it('renders area names from the database', function (): void {
    Area::factory()->create(['name' => 'Galactic Transmissions', 'sort_order' => 1]);
    Area::factory()->create(['name' => 'Interplanetary Gossip', 'sort_order' => 2]);

    $state = new GoldedState;

    expect(stateText($state))
        ->toContain('Galactic Transmissions')
        ->toContain('Interplanetary Gossip');
});

// ── PgUp / PgDn / Home / End ──────────────────────────────────────────────────

it('jumps selection forward 20 on PageDown in area list', function (): void {
    Area::factory()->count(25)->create();

    $state = new GoldedState;
    $state->handleKey('PageDown');

    expect($state->selectionIndex)->toBe(20);
});

it('clamps PageDown at last area', function (): void {
    Area::factory()->count(5)->create();

    $state = new GoldedState;
    $state->handleKey('PageDown');

    expect($state->selectionIndex)->toBe(4);
});

it('jumps selection to 0 on Home in area list', function (): void {
    Area::factory()->count(5)->create();

    $state = new GoldedState;
    $state->handleKey('ArrowDown');
    $state->handleKey('ArrowDown');
    $state->handleKey('Home');

    expect($state->selectionIndex)->toBe(0);
});

it('jumps selection to last on End in area list', function (): void {
    Area::factory()->count(5)->create();

    $state = new GoldedState;
    $state->handleKey('End');

    expect($state->selectionIndex)->toBe(4);
});

it('jumps selection forward 20 on PageDown in message list', function (): void {
    $area = Area::factory()->create(['sort_order' => 1]);
    Message::factory()->for($area)->count(25)->sequence(fn ($s): array => ['msgno' => $s->index + 1])->create();

    $state = new GoldedState;
    $state->handleKey('Enter');
    $state->handleKey('PageDown');

    expect($state->selectionIndex)->toBe(20);
});

it('scrolls reader body forward 18 lines on PageDown', function (): void {
    $area = Area::factory()->create(['sort_order' => 1]);
    Message::factory()->for($area)->create([
        'msgno' => 1,
        'body_text' => implode("\n", array_fill(0, 40, 'line')),
    ]);

    $state = new GoldedState;
    $state->handleKey('Enter');
    $state->handleKey('Enter');
    $state->handleKey('PageDown');

    expect($state->scrollOffset)->toBe(18);
});

it('scrolls reader body back 18 lines on PageUp', function (): void {
    $area = Area::factory()->create(['sort_order' => 1]);
    Message::factory()->for($area)->create([
        'msgno' => 1,
        'body_text' => implode("\n", array_fill(0, 40, 'line')),
    ]);

    $state = new GoldedState;
    $state->handleKey('Enter');
    $state->handleKey('Enter');
    $state->handleKey('PageDown');
    $state->handleKey('PageDown');
    $state->handleKey('PageUp');

    expect($state->scrollOffset)->toBe(18);
});

it('jumps reader to top on Home', function (): void {
    $area = Area::factory()->create(['sort_order' => 1]);
    Message::factory()->for($area)->create([
        'msgno' => 1,
        'body_text' => implode("\n", array_fill(0, 40, 'line')),
    ]);

    $state = new GoldedState;
    $state->handleKey('Enter');
    $state->handleKey('Enter');
    $state->handleKey('PageDown');
    $state->handleKey('Home');

    expect($state->scrollOffset)->toBe(0);
});

// ── Reply navigation ──────────────────────────────────────────────────────────

it('navigates to parent message with -', function (): void {
    $area = Area::factory()->create(['sort_order' => 1]);
    $parent = Message::factory()->for($area)->create(['msgno' => 1]);
    Message::factory()->for($area)->create(['msgno' => 2, 'reply_to_msgno' => 1]);

    $state = new GoldedState;
    $state->handleKey('Enter');
    $state->handleKey('ArrowDown');
    $state->handleKey('Enter');
    $state->handleKey('-');

    expect($state->messageId)->toBe($parent->id);
});

it('does nothing on - when message has no parent', function (): void {
    $area = Area::factory()->create(['sort_order' => 1]);
    $msg = Message::factory()->for($area)->create(['msgno' => 1, 'reply_to_msgno' => null]);

    $state = new GoldedState;
    $state->handleKey('Enter');
    $state->handleKey('Enter');
    $state->handleKey('-');

    expect($state->messageId)->toBe($msg->id);
});

it('navigates to first reply with +', function (): void {
    $area = Area::factory()->create(['sort_order' => 1]);
    Message::factory()->for($area)->create(['msgno' => 1, 'reply1st_msgno' => 2]);
    $reply = Message::factory()->for($area)->create(['msgno' => 2, 'reply_to_msgno' => 1]);

    $state = new GoldedState;
    $state->handleKey('Enter');
    $state->handleKey('Enter');
    $state->handleKey('+');

    expect($state->messageId)->toBe($reply->id);
});

it('navigates to next sibling with *', function (): void {
    $area = Area::factory()->create(['sort_order' => 1]);
    Message::factory()->for($area)->create(['msgno' => 1, 'reply1st_msgno' => 2]);
    Message::factory()->for($area)->create(['msgno' => 2, 'reply_to_msgno' => 1, 'replynext_msgno' => 3]);
    $second = Message::factory()->for($area)->create(['msgno' => 3, 'reply_to_msgno' => 1]);

    $state = new GoldedState;
    $state->handleKey('Enter');
    $state->handleKey('ArrowDown');
    $state->handleKey('Enter');
    $state->handleKey('*');

    expect($state->messageId)->toBe($second->id);
});

// ── Quote colouring ───────────────────────────────────────────────────────────

it('applies quote CSS class to quoted lines in reader', function (): void {
    $area = Area::factory()->create(['sort_order' => 1]);
    Message::factory()->for($area)->create([
        'msgno' => 1,
        'body_text' => "> This is a quote\nAnd this is normal text",
    ]);

    $state = new GoldedState;
    $state->handleKey('Enter');
    $state->handleKey('Enter');

    expect(stateHasClass($state, 'cga-blue-lgrey'))->toBeTrue();
});

it('hides kludge lines in reader by default', function (): void {
    $area = Area::factory()->create(['sort_order' => 1]);
    Message::factory()->for($area)->create([
        'msgno' => 1,
        'body_text' => "\x01MSGID: 1:2/3 deadbeef\nHello world",
    ]);

    $state = new GoldedState;
    $state->handleKey('Enter');
    $state->handleKey('Enter');

    expect(stateHasClass($state, 'cga-dgrey-lgrey'))->toBeFalse();
});

it('shows kludge lines after pressing k in reader', function (): void {
    $area = Area::factory()->create(['sort_order' => 1]);
    Message::factory()->for($area)->create([
        'msgno' => 1,
        'body_text' => "\x01MSGID: 1:2/3 deadbeef\nHello world",
    ]);

    $state = new GoldedState;
    $state->handleKey('Enter');
    $state->handleKey('Enter');
    $state->handleKey('k');

    expect(stateHasClass($state, 'cga-dgrey-lgrey'))->toBeTrue();
});
