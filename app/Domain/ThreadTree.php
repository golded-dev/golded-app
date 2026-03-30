<?php

namespace App\Domain;

use Illuminate\Support\Collection;

class ThreadTree
{
    /**
     * Build thread tree prefixes for a collection of messages.
     *
     * Returns array<int, string> — message id => 8-char tree prefix.
     * Uses reply_to_msgno to link children to parents within the collection.
     *
     * @param  Collection<int, object{id: int, msgno: int, reply_to_msgno: ?int}>  $messages
     * @return array<int, string>
     */
    public function build(Collection $messages): array
    {
        /** @var array<int, int> $msgnoToId  msgno => message id */
        $msgnoToId = $messages->pluck('id', 'msgno')->all();

        /** @var array<int, list<int>> $children  id => [child ids in order] */
        $children = [];
        /** @var array<int, int> $parents  child id => parent id */
        $parents = [];

        foreach ($messages as $msg) {
            $children[$msg->id] = [];
        }

        foreach ($messages as $msg) {
            if ($msg->reply_to_msgno !== null && isset($msgnoToId[$msg->reply_to_msgno])) {
                $parentId = $msgnoToId[$msg->reply_to_msgno];
                $children[$parentId][] = $msg->id;
                $parents[$msg->id] = $parentId;
            }
        }

        $prefixes = [];

        foreach ($messages as $msg) {
            $prefixes[$msg->id] = $this->prefix($msg->id, $parents, $children);
        }

        return $prefixes;
    }

    /**
     * Build the 8-char tree prefix for one message by walking up to its root.
     *
     * Each level of nesting contributes 2 chars:
     *   - Last position in parent's children list → '└ ' (leaf) or '  ' (ancestor)
     *   - Non-last position → '├ ' (leaf) or '│ ' (ancestor)
     *
     * @param  array<int, int>  $parents
     * @param  array<int, list<int>>  $children
     */
    private function prefix(int $id, array $parents, array $children): string
    {
        // Walk from this node to root, recording whether each node
        // is the last sibling among its parent's children.
        $path = [];
        $current = $id;

        while (isset($parents[$current])) {
            $parentId = $parents[$current];
            $siblings = $children[$parentId];
            $path[] = end($siblings) === $current ? 'last' : 'more';
            $current = $parentId;
        }

        if (empty($path)) {
            return str_repeat(' ', 8);
        }

        // path is root→leaf order after reversing
        $path = array_reverse($path);
        $depth = count($path);
        $prefix = '';

        for ($i = 0; $i < $depth; $i++) {
            $isLeaf = ($i === $depth - 1);
            $isLast = ($path[$i] === 'last');

            if ($isLeaf) {
                $prefix .= $isLast ? '└ ' : '├ ';
            } else {
                // Continuation: show │ if this ancestor still has siblings below
                $prefix .= $isLast ? '  ' : '│ ';
            }
        }

        return mb_str_pad($prefix, 8);
    }
}
