# golded.dev — Full Specification v1

## 1. Vision

Build a browser-native version of GoldED that:

- Feels like the original ASCII interface
- Is keyboard-first
- Can read ALL original message base formats supported by GoldED
- Can write new messages and replies in the same paradigm

Not a retro skin. A working environment.

---

## 2. Product Definition

A web application that:

- Imports historic message bases (multiple formats)
- Normalizes them into a canonical model
- Renders them in a faithful GoldED-style interface
- Allows reading, navigating, and writing messages

---

## 3. Core Principles

1. Fidelity over aesthetics
2. Keyboard over mouse
3. Plain text over formatting
4. Canonical model over format-specific logic
5. Import adapters isolate complexity
6. UI is deterministic, not reactive-chaotic

---

## 4. Supported Formats

The system must support all original GoldED-relevant formats via adapters:

- Raw `.MSG` (FTN message files)
- JAM message base (`.jhr`, `.jdt`, `.jdx`)
- Squish (`.sqd`, `.sqi`)
- Hudson style (if present)
- Archive bundles containing any of the above

Rules:

- Each format is implemented as a separate importer
- No format-specific logic in UI or domain
- Parsers must preserve raw message content exactly

---

## 5. Architecture

Stack:

- Laravel 13
- Livewire
- Blade
- MySQL or PostgreSQL

Layers:

1. Domain (canonical data model)
2. Application (use cases)
3. Infrastructure (importers, persistence)
4. UI (Livewire shell)
5. Input handling (keyboard)
6. Rendering (ASCII layout)

---

## 6. Canonical Data Model

### Dataset

- id
- name
- source_type
- created_at

### Area

- id
- dataset_id
- code
- name
- sort_order

### Message

- id
- dataset_id
- area_id
- external_id
- subject
- from_name
- to_name
- body_text (raw, unchanged)
- reply_to_external_id (nullable)
- thread_key (nullable)
- posted_at (nullable)
- raw_metadata_json (nullable)

### Draft

- id
- dataset_id
- area_id
- reply_to_message_id (nullable)
- subject
- from_name
- to_name
- body_text
- created_at
- updated_at

---

## 7. Import System

### Importer Interface

```php
interface Importer {
    public function import(string $path): Dataset;
}
```

### Requirements

- Each format has its own importer class
- Import is triggered manually (CLI or seed)
- No auto-detection required initially
- Importers must:
  - Parse source files
  - Map to canonical entities
  - Preserve original text exactly
  - Store unknown metadata in raw_metadata_json

### Output

- Dataset created
- Areas populated
- Messages populated

---

## 8. UI Architecture

Single Livewire component:

GoldedShell

Responsibilities:

- Holds all UI state
- Controls screen transitions
- Handles keyboard input
- Renders full interface

No traditional routing between screens.

---

## 9. State Model

```
state = [
  'screen' => 'areas|messages|reader|editor',
  'datasetId',
  'areaId',
  'messageId',
  'draftId',
  'selectionIndex',
  'scrollOffset'
];
```

---

## 10. Screens

### Areas Screen

- List of areas
- Keyboard navigation
- Enter opens selected area

---

### Messages Screen

- Messages in selected area
- Shows:
  - index
  - from
  - subject
- Enter opens reader

---

### Reader Screen

Layout:

```
From : ...
To   : ...
Subj : ...
Date : ...

<raw message body>

---
 * Origin: ...
```

Rules:

- Preserve all whitespace
- Preserve quote levels (`>`, `>>`)
- No text reflow
- No formatting changes

---

### Editor Screen

Supports:

- New message
- Reply

Layout:

```
From : ...
To   : ...
Subj : ...

<editable text buffer>
```

---

## 11. Editor Behavior

### Requirements

- Plain text only
- No markdown or rich text
- Full keyboard control

### Features

- Insert characters
- Delete (backspace)
- New line (enter)
- Cursor movement:
  - left/right/up/down

---

### Reply Behavior

- Quote original message
- Prefix each line with `>`
- Preserve existing quote depth

Example:

```
> original
>> deeper quote
```

---

## 12. Keyboard Model

### Global

- Esc → back
- Tab → switch focus (if needed)

---

### Navigation

- ↑ / ↓ → move selection
- Enter → open
- PgUp / PgDn → scroll

---

### Editor

- typing → insert
- Backspace → delete
- Enter → newline
- Ctrl+S → save draft
- Esc → exit editor

---

## 13. Layout System

Rendering approach:

- `<pre>` or strict grid layout
- Monospace font only
- Fixed width alignment

---

### Regions

```
HEADER
MAIN
FOOTER (status line)
```

---

### Status Line

Always visible:

```
F1 Help  Enter Open  R Reply  N New  Esc Back
```

---

## 14. Rendering Rules

- No modern UI components (cards, panels, etc.)
- Minimal spacing
- High information density
- Clear selection highlight
- Deterministic layout

---

## 15. Selection Model

Only one active focus at a time:

- selected area
- selected message
- editor cursor

---

## 16. Data Integrity Rules

- Never modify original message body
- Preserve:
  - whitespace
  - quotes
  - line breaks
- Drafts are stored separately

---

## 17. Implementation Phases

### Phase 1 — Shell

- Livewire shell
- Hardcoded UI
- Fake data

---

### Phase 2 — Reader

- Render one real message
- Validate layout fidelity

---

### Phase 3 — Navigation

- Areas → messages → reader
- Keyboard navigation

---

### Phase 4 — Import

- Implement first importer
- Load real dataset

---

### Phase 5 — Editor

- Text editing
- Reply quoting
- Draft storage

---

### Phase 6 — Multi-format Support

- Add importers for all supported formats
- Validate across real datasets

---

## 18. Definition of Done

The system is complete when:

- All supported formats can be imported
- User can navigate with keyboard only
- Messages render correctly and faithfully
- User can reply and create messages
- UI feels like GoldED, not a web app

---

## 19. Anti-Goals

- No modern UI redesign
- No premature abstraction
- No visual over-styling
- No mutation of historic data

---

## 20. Success Criteria


User opens app and thinks:

"This behaves like a real message editor"

Not:

"This looks retro"

---

## 21. Development Philosophy — KISS my DDD with TDD

This project follows a strict guiding principle:

KISS my DDD with TDD

This translates to:

### KISS

- Keep structure simple and intentional
- Avoid premature abstraction
- Build one clear path before generalizing
- Prefer working behavior over architectural purity

### DDD

- Use real domain language:
  - Message
  - Area
  - Dataset
  - Draft
- Keep legacy formats isolated in the Infrastructure layer
- Do not leak format-specific concerns into Domain or UI
- Model behavior where it belongs (e.g. quoting, reply logic)

### TDD

- Write tests for:
  - Importers using real sample data
  - Message parsing edge cases
  - Quote depth and formatting
  - Editor behavior
  - State transitions in navigation
- Protect:
  - Raw message integrity
  - Line endings
  - Whitespace
- Tests should validate behavior, not implementation

---

## 22. Internal Motto

```
Make the domain obvious.
Make the UI faithful.
Make the weirdness testable.
```

---

## 23. Post Draft (Reference)

KISS my DDD with TDD

I am rebuilding an old project as a browser-based GoldED.

ASCII UI. Keyboard-first. No decoration.

And I hit the usual fork:

Do I “do it right” with DDD?
Or just build something that works?

So I landed somewhere in between:

KISS my DDD with TDD

Not an enterprise cathedral.
Not spaghetti.

Just:

- One clear domain (messages, areas, drafts)
- Legacy formats pushed to the edges
- Use cases that actually reflect behavior
- Tests for the weird stuff (quotes, line endings, old formats)

Turns out when you deal with old systems, TDD is not optional.

It is the only thing stopping you from breaking something you do not fully understand yet.

And DDD?

It keeps your head straight when `.MSG`, JAM and Squish start blending together.

But only if you keep it simple.

So the working mantra became:

Make the domain obvious.
Make the UI faithful.
Make the weirdness testable.

Everything else has to prove itself.
