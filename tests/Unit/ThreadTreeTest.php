<?php

use App\Domain\ThreadTree;

// Helper: build a fake message object with id, msgno, reply_to_msgno
function msg(int $id, int $msgno, ?int $replyTo = null): object
{
    return (object) ['id' => $id, 'msgno' => $msgno, 'reply_to_msgno' => $replyTo];
}

it('returns blank prefix for a root message with no replies', function () {
    $messages = collect([msg(1, 1)]);
    $tree = (new ThreadTree)->build($messages);

    expect($tree[1])->toBe('        ');
});

it('returns blank prefix for multiple root messages with no replies', function () {
    $messages = collect([msg(1, 1), msg(2, 2)]);
    $tree = (new ThreadTree)->build($messages);

    expect($tree[1])->toBe('        ');
    expect($tree[2])->toBe('        ');
});

it('shows ─┐ bend on root message that has replies', function () {
    $messages = collect([msg(1, 1), msg(2, 2, 1)]);
    $tree = (new ThreadTree)->build($messages);

    expect($tree[1])->toStartWith('─┐');
});

it('marks last child with └', function () {
    // 1 ← root
    //   └ 2 (only reply)
    $messages = collect([msg(1, 1), msg(2, 2, 1)]);
    $tree = (new ThreadTree)->build($messages);

    expect($tree[1])->toStartWith('─┐');
    expect($tree[2])->toStartWith(' └ ');
});

it('marks non-last sibling with ├ and last with └', function () {
    // 1 ← root
    //   ├ 2
    //   └ 3
    $messages = collect([msg(1, 1), msg(2, 2, 1), msg(3, 3, 1)]);
    $tree = (new ThreadTree)->build($messages);

    expect($tree[2])->toStartWith(' ├ ');
    expect($tree[3])->toStartWith(' └ ');
});

it('renders continuation │ on ancestor when it has more siblings', function () {
    // 1 root
    //   ├ 2 (has sibling 3 below)
    //   │  └ 4 (reply to 2)
    //   └ 3
    $messages = collect([
        msg(1, 1),
        msg(2, 2, 1),
        msg(3, 3, 1),
        msg(4, 4, 2),
    ]);
    $tree = (new ThreadTree)->build($messages);

    expect($tree[2])->toStartWith(' ├ ');
    expect($tree[3])->toStartWith(' └ ');
    expect($tree[4])->toStartWith(' │ └ ');
});

it('renders spec §10.5 example correctly', function () {
    // 1: root
    // 2: reply to 1 (not last — 3,5 follow)
    // 3: reply to 1 (not last — 5 follows)
    // 4: reply to 3 (only child of 3; 3 is not-last → │ └)
    // 5: reply to 1 (last)
    $messages = collect([
        msg(1, 1),
        msg(2, 2, 1),
        msg(3, 3, 1),
        msg(4, 4, 3),
        msg(5, 5, 1),
    ]);
    $tree = (new ThreadTree)->build($messages);

    expect($tree[1])->toStartWith('─┐');
    expect($tree[2])->toStartWith(' ├ ');
    expect($tree[3])->toStartWith(' ├ ');
    expect($tree[4])->toStartWith(' │ └ ');
    expect($tree[5])->toStartWith(' └ ');
});

// ── order() ───────────────────────────────────────────────────────────────────

it('orders root-only messages by msgno', function () {
    $messages = collect([msg(3, 3), msg(1, 1), msg(2, 2)]);
    $ordered = (new ThreadTree)->order($messages);

    expect($ordered->pluck('id')->all())->toBe([1, 2, 3]);
});

it('places replies immediately after their parent in depth-first order', function () {
    // 1 root, 2 replies to 1, 3 is a separate root
    $messages = collect([msg(1, 1), msg(2, 2, 1), msg(3, 3)]);
    $ordered = (new ThreadTree)->order($messages);

    expect($ordered->pluck('id')->all())->toBe([1, 2, 3]);
});

it('groups nested replies under their ancestor before moving to next sibling', function () {
    // 1 → 2 → 4 (deep chain), 1 → 3 (sibling of 2)
    $messages = collect([msg(1, 1), msg(2, 2, 1), msg(3, 3, 1), msg(4, 4, 2)]);
    $ordered = (new ThreadTree)->order($messages);

    // Depth-first: 1, 2, 4 (child of 2), 3 (sibling of 2)
    expect($ordered->pluck('id')->all())->toBe([1, 2, 4, 3]);
});

it('handles the spec §10.5 example in correct thread order', function () {
    // 1: root; 2,3,5: direct replies to 1; 4: reply to 3
    $messages = collect([msg(1, 1), msg(2, 2, 1), msg(3, 3, 1), msg(4, 4, 3), msg(5, 5, 1)]);
    $ordered = (new ThreadTree)->order($messages);

    // Depth-first: 1, 2, 3, 4, 5
    expect($ordered->pluck('id')->all())->toBe([1, 2, 3, 4, 5]);
});

it('ignores reply_to_msgno not present in the collection', function () {
    $messages = collect([msg(1, 1, 99)]);
    $tree = (new ThreadTree)->build($messages);

    expect($tree[1])->toBe('        ');
});

it('truncates deep nesting to exactly 8 chars (max 4 levels)', function () {
    // 5 levels deep — prefix would be 10 chars without capping
    $messages = collect([
        msg(1, 1),
        msg(2, 2, 1),
        msg(3, 3, 2),
        msg(4, 4, 3),
        msg(5, 5, 4),
        msg(6, 6, 5), // 5 levels deep
    ]);
    $tree = (new ThreadTree)->build($messages);

    expect(mb_strlen($tree[6]))->toBe(8);
});

it('pads all prefixes to exactly 8 chars', function () {
    $messages = collect([
        msg(1, 1),
        msg(2, 2, 1),
        msg(3, 3, 1),
        msg(4, 4, 3),
        msg(5, 5, 4),
    ]);
    $tree = (new ThreadTree)->build($messages);

    foreach ($tree as $prefix) {
        expect(mb_strlen($prefix))->toBe(8);
    }
});
