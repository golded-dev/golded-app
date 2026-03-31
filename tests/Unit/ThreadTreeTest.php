<?php

use App\Domain\ThreadTree;

// Helper: build a fake message object with id, msgno, reply_to_msgno
function msg(int $id, int $msgno, ?int $replyTo = null): object
{
    return (object) ['id' => $id, 'msgno' => $msgno, 'reply_to_msgno' => $replyTo];
}

it('returns blank prefix for a root message', function () {
    $messages = collect([msg(1, 1)]);
    $tree = (new ThreadTree)->build($messages);

    expect($tree[1])->toBe('        ');
});

it('returns blank prefix for multiple root messages', function () {
    $messages = collect([msg(1, 1), msg(2, 2)]);
    $tree = (new ThreadTree)->build($messages);

    expect($tree[1])->toBe('        ');
    expect($tree[2])->toBe('        ');
});

it('marks last child with └', function () {
    // 1 ← root
    //   └ 2 (only reply)
    $messages = collect([msg(1, 1), msg(2, 2, 1)]);
    $tree = (new ThreadTree)->build($messages);

    expect($tree[1])->toBe('        ');
    expect($tree[2])->toStartWith('└ ');
});

it('marks non-last sibling with ├ and last with └', function () {
    // 1 ← root
    //   ├ 2
    //   └ 3
    $messages = collect([msg(1, 1), msg(2, 2, 1), msg(3, 3, 1)]);
    $tree = (new ThreadTree)->build($messages);

    expect($tree[2])->toStartWith('├ ');
    expect($tree[3])->toStartWith('└ ');
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

    expect($tree[2])->toStartWith('├ ');
    expect($tree[3])->toStartWith('└ ');
    expect($tree[4])->toStartWith('│ └ ');
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

    expect($tree[1])->toBe('        ');
    expect($tree[2])->toStartWith('├ ');
    expect($tree[3])->toStartWith('├ ');
    expect($tree[4])->toStartWith('│ └ ');
    expect($tree[5])->toStartWith('└ ');
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
