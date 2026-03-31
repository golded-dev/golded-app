<?php

namespace App\Domain;

use Illuminate\Support\Collection;

class ThreadTree
{
    /**
     * Return messages sorted into depth-first thread order.
     *
     * Mirrors gemlst.cpp recursive_build(): visit root, then first child and
     * its subtree, then next sibling and its subtree.  Within each sibling
     * group children are ordered by msgno (the order they arrived).
     *
     * Root messages (no parent in the collection) are sorted by msgno and
     * act as thread anchors.
     *
     * @param  Collection<int, object{id: int, msgno: int, reply_to_msgno: ?int}>  $messages
     * @return Collection<int, object>
     */
    public function order(Collection $messages): Collection
    {
        if ($messages->isEmpty()) {
            return $messages;
        }

        [$children, $parents] = $this->buildLinks($messages);

        $idToMsg = $messages->keyBy('id')->all();

        $roots = $messages
            ->filter(fn ($m) => ! isset($parents[$m->id]))
            ->sortBy('msgno')
            ->values();

        $ordered = [];
        $visited = [];

        foreach ($roots as $root) {
            $this->visit($root->id, $children, $idToMsg, $ordered, $visited);
        }

        return collect($ordered);
    }

    /**
     * Build thread tree prefixes for a collection of messages.
     *
     * Returns array<int, string> — message id => 8-char tree prefix.
     *
     * Algorithm from gemlst.cpp GenTree():
     *   - Root messages: 8 spaces.
     *   - Own connector: ├ if the message has a next sibling, └ if it is last.
     *   - For each ancestor going up: │ if that ancestor has a next sibling,
     *     space if it was the last child.
     *
     * Call order() first and pass the result here so the prefixes visually
     * connect between consecutive rows in the display.
     *
     * @param  Collection<int, object{id: int, msgno: int, reply_to_msgno: ?int}>  $messages
     * @return array<int, string>
     */
    public function build(Collection $messages): array
    {
        [$children, $parents] = $this->buildLinks($messages);
        $replynext = $this->buildReplynext($children);

        $prefixes = [];

        foreach ($messages as $msg) {
            $prefixes[$msg->id] = $this->prefix($msg->id, $replynext, $parents, $children);
        }

        return $prefixes;
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    /**
     * Build children map and parents map from reply_to_msgno links.
     *
     * Children are appended in iteration order (msgno order if the collection
     * is sorted by msgno, which it should be when fetched from the DB).
     *
     * @return array{0: array<int, list<int>>, 1: array<int, int>}
     */
    private function buildLinks(Collection $messages): array
    {
        $msgnoToId = $messages->pluck('id', 'msgno')->all();

        /** @var array<int, list<int>> $children  parent id => [child ids in order] */
        $children = [];
        /** @var array<int, int> $parents  child id => parent id */
        $parents = [];

        foreach ($messages as $msg) {
            $children[$msg->id] = [];
        }

        foreach ($messages as $msg) {
            if (
                $msg->reply_to_msgno !== null
                && isset($msgnoToId[$msg->reply_to_msgno])
                && $msgnoToId[$msg->reply_to_msgno] !== $msg->id // no self-references
            ) {
                $parentId = $msgnoToId[$msg->reply_to_msgno];
                $children[$parentId][] = $msg->id;
                $parents[$msg->id] = $parentId;
            }
        }

        return [$children, $parents];
    }

    /**
     * Build replynext map: for each message, the id of its next sibling (or null).
     *
     * This is derived from the ordered children lists, which encode sibling order.
     *
     * @param  array<int, list<int>>  $children
     * @return array<int, int|null>
     */
    private function buildReplynext(array $children): array
    {
        $replynext = [];

        foreach ($children as $siblings) {
            $count = count($siblings);
            for ($i = 0; $i < $count; $i++) {
                $replynext[$siblings[$i]] = $siblings[$i + 1] ?? null;
            }
        }

        return $replynext;
    }

    /**
     * Depth-first traversal, visiting a node and then all its descendants.
     * Guards against cycles.
     *
     * @param  array<int, list<int>>  $children
     * @param  array<int, object>  $idToMsg
     * @param  list<object>  $ordered
     * @param  array<int, true>  $visited
     */
    private function visit(int $id, array $children, array $idToMsg, array &$ordered, array &$visited): void
    {
        if (isset($visited[$id]) || ! isset($idToMsg[$id])) {
            return;
        }

        $visited[$id] = true;
        $ordered[] = $idToMsg[$id];

        foreach ($children[$id] ?? [] as $childId) {
            $this->visit($childId, $children, $idToMsg, $ordered, $visited);
        }
    }

    /**
     * Build the 8-char prefix for one message (gemlst.cpp GenTree()).
     *
     *   - Root with children: ─┐ + spaces (bend shows thread starting here).
     *   - Root without children: 8 spaces.
     *   - Own connector: ├ if message has a next sibling, else └.
     *   - For each ancestor going up to (but not including) the root:
     *       │ if the ancestor has a next sibling, space if it was last.
     *   - Truncated to 8 chars if nesting exceeds 4 levels.
     *
     * @param  array<int, int|null>  $replynext  id => next-sibling id or null
     * @param  array<int, int>  $parents  child id => parent id
     * @param  array<int, list<int>>  $children  parent id => [child ids]
     */
    private function prefix(int $id, array $replynext, array $parents, array $children): string
    {
        if (! isset($parents[$id])) {
            // Root: show ─┐ bend if it has replies, blank otherwise.
            return ! empty($children[$id])
                ? mb_str_pad('─┐', 8)
                : str_repeat(' ', 8);
        }

        // Own connector — append ─┐ bend when this message has its own replies.
        $connector = isset($replynext[$id]) ? '├' : '└';
        $parts = [! empty($children[$id]) ? $connector.'─┐' : $connector.'─'];

        // Walk up the ancestor chain; stop before root (root has no parent)
        $current = $id;

        while (isset($parents[$current])) {
            $parentId = $parents[$current];

            if (! isset($parents[$parentId])) {
                // Parent is root — no continuation line needed above it
                break;
            }

            $parts[] = isset($replynext[$parentId]) ? '│ ' : '  ';
            $current = $parentId;
        }

        // Reverse: outermost ancestor is leftmost in display.
        // The leading space mirrors GenTree's q+=2 which skips root's continuation
        // char but keeps the space that preceded it — so all non-root messages
        // start with one space before their connector.
        $prefix = ' '.implode('', array_reverse($parts));

        // If nesting is deep, truncate from the LEFT so the connector (└─/├─/└─┐/├─┐)
        // at the right end is always preserved rather than cut off.
        if (mb_strlen($prefix) > 8) {
            $prefix = mb_substr($prefix, -8);
        }

        return mb_str_pad($prefix, 8);
    }
}
