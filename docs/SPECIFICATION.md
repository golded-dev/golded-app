# golded.dev — Full Specification v2

> v2 integrates source-level analysis of the original GoldED 3/4 C++ codebase
> (`archive/source/golded3/`) and live configuration from the Goldware BBS
> (`archive/config/`). All UI dimensions, colours, key bindings and layout
> positions are derived from the original source, not guesswork.

---

## Decisions

| # | Decision |
|---|----------|
| Q1 | **Adaptive width** — layout responds to terminal/window width. Default and minimum is 80 columns. Never hardcode 80. |
| Q2 | **Exact CGA RGB values** — use the standard CGA hex values as design tokens. Keep them overridable via config for future theme support. |
| Q3 | **Mouse supported** — the original had optional mouse support; include it. Keyboard remains primary. |
| Q4 | **Demo/faked dataset on first launch** — load with the Goldware BBS archive (or a curated fake) so the app is immediately alive. Dataset picker is a future feature. |
| Q5 | **Sending is out of scope** — drafts yes, actual sending no. FidoNet transmission is v2+. |
| Q6 | **License: MIT** — clean-room rewrite in PHP, zero source code carried over. New original work, MIT licensed. |
| Q7 | **Version: GoldED 7** — last release was 3.0.1 (1999). v4 never shipped. 3+4=7, or `(4+2)++`, or simply the author's favourite number. All equally valid. |

---

## 1. Vision

Build a browser-native version of GoldED that:

- Feels like the original ASCII interface — 1:1 fidelity
- Is keyboard-first
- Can read ALL original message base formats supported by GoldED
- Can write new messages and replies in the same paradigm

Not a retro skin. A working environment.

---

## 2. Product Definition

A web application that:

- Imports historic message bases (multiple formats)
- Normalises them into a canonical model
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

- Raw `.MSG` (FTN message files — standard FidoNet *.msg directories)
- JAM message base (`.jhr`, `.jdt`, `.jdx`, `.jlr`)
- Squish (`.sqd`, `.sqi`, `.sql`)
- Hudson / QuickBBS (`USERS.BBS`, `LASTREAD.BBS` — `MSG/HUDSON/`)
- Goldbase (`LASTREAD.DAT` — `MSG/GOLDBASE/`)
- Archive bundles containing any of the above

Rules:

- Each format is implemented as a separate importer
- Import is triggered manually (CLI or seed)
- No auto-detection required initially
- Parsers must preserve raw message content exactly
- Unknown metadata stored in `raw_metadata_json`

---

## 5. Architecture

Stack:

- Laravel 13
- Livewire
- Blade
- SQLite (development) / MySQL or PostgreSQL (production)

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
- echoid (nullable — FidoNet echo tag)
- group_id (nullable — single char, e.g. 'A')
- sort_order
- message_count (nullable — null = unscanned)
- unread_count (nullable — null = unscanned)
- last_read_msgno (nullable)

### Message
- id
- dataset_id
- area_id
- msgno (original message number in base)
- external_id
- subject
- from_name
- from_address (nullable — FTN or internet)
- to_name
- to_address (nullable)
- body_text (raw, unchanged)
- reply_to_msgno (nullable)
- reply_to_external_id (nullable)
- reply1st_msgno (nullable — first reply to this message)
- replynext_msgno (nullable — next sibling reply)
- thread_key (nullable)
- attributes_raw (int — original FTN attr bits)
- posted_at (nullable)
- arrived_at (nullable)
- is_read (bool)
- is_marked (bool)
- is_bookmarked (bool)
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
    public function import(string $path, Dataset $dataset): void;
}
```

### Requirements

- Each format has its own importer class
- Import is triggered manually (CLI or seed)
- Importers must:
  - Parse source files
  - Map to canonical entities
  - Preserve original text exactly
  - Store unknown metadata in `raw_metadata_json`
  - Handle missing reply links gracefully (JAM has them; MSG does not always)

### Character Encoding

The original messages are CP437 / CP850 / ISO-8859-1 (Danish: æøå).
The `archive/config/XLAT/` directory contains the full GoldED translation
table library (64 `.CHS`/`.ESC` files).

Importer responsibility:
- Detect charset from message kludge headers (`CHRS:`, `CHARSET:`)
- Transcode body to UTF-8 on import
- Store original encoding in `raw_metadata_json`
- Default assumption: CP850 for FidoNet messages without charset declaration

---

## 8. Screen Dimensions

Source: `geall.h`

```
SCREEN WIDTH  : adaptive (minimum/default 80 columns — never hardcoded)
SCREEN HEIGHT : 25 rows (0-indexed, rows 0–24)
HEADER ROWS   : 6 (rows 0–5, context-dependent content)
CONTENT ROWS  : 18 (rows 6–23, MINROW=6, MAXMSGLINES=MAXROW-MINROW-1)
STATUS ROW    : 1 (row 24)
```

All screens share this structure. The header region changes content per screen;
the status bar is always present.

---

## 9. Colour System

Source: `archive/config/GEDCOLOR.CFG`

### Palette

GoldED uses the 16-colour CGA/EGA palette. Background is always LGREY (`#AAAAAA`).

| Name | CGA Index | Hex (approximate) |
|------|-----------|-------------------|
| BLACK | 0 | #000000 |
| BLUE | 1 | #0000AA |
| GREEN | 2 | #00AA00 |
| CYAN | 3 | #00AAAA |
| RED | 4 | #AA0000 |
| MAGENTA | 5 | #AA00AA |
| BROWN | 6 | #AA5500 |
| LGREY | 7 | #AAAAAA |
| DGREY | 8 | #555555 |
| LBLUE | 9 | #5555FF |
| LGREEN | 10 | #55FF55 |
| LCYAN | 11 | #55FFFF |
| LRED | 12 | #FF5555 |
| LMAGENTA | 13 | #FF55FF |
| YELLOW | 14 | #FFFF55 |
| WHITE | 15 | #FFFFFF |

> Exact CGA RGB values are used as design tokens. Values are overridable for future theme support.

### Colour Roles

| Role | Foreground | Background | Usage |
|------|------------|------------|-------|
| `BODY_NORMAL` | BLACK | LGREY | Message body text |
| `BODY_QUOTE1` | BLUE | LGREY | Quote level 1, 3, 5 (odd) |
| `BODY_QUOTE2` | MAGENTA | LGREY | Quote level 2, 4, 6 (even) |
| `BODY_TEARLINE` | LBLUE | LGREY | `--- tearline` |
| `BODY_ORIGIN` | LBLUE | LGREY | ` * Origin:` line |
| `BODY_TAGLINE` | LBLUE | LGREY | Tagline |
| `BODY_KLUDGE` | DGREY | LGREY | Kludge / hidden lines |
| `BODY_SEARCH` | WHITE+BLINK | RED | Search highlight |
| `BODY_BLOCK` | BLACK | CYAN | Selected block |
| `LIST_NORMAL` | BLACK | LGREY | Message list row |
| `LIST_SELECTED` | WHITE | BLUE | Selected row |
| `LIST_UNREAD` | BLUE | LGREY | Unread message |
| `LIST_UNREAD_SEL` | LBLUE | LGREY | Selected unread |
| `LIST_UNSENT` | MAGENTA | LGREY | Unsent message |
| `LIST_UNSENT_SEL` | LMAGENTA | LGREY | Selected unsent |
| `HEADER_LABEL` | BLUE | LGREY | Header field labels |
| `HEADER_INPUT` | BLACK | LGREY | Editable header fields (same background as rest of header) |
| `HEADER_BORDER` | YELLOW | LGREY | Window borders |
| `HEADER_TITLE` | RED | LGREY | Window title text |
| `STATUS_BAR` | WHITE | BLUE | Bottom status line |
| `AREA_NORMAL` | BLACK | LGREY | Area list row |
| `AREA_SELECTED` | WHITE | BLUE | Selected area |
| `AREA_UNREAD` | BLUE | LGREY | Area with unread messages |
| `AREA_HIGHLIGHT` | RED | LGREY | Search match |
| `DIALOG_BG` | LGREY | BLUE | Popup dialog background |
| `DIALOG_BORDER` | LBLUE | BLUE | Popup dialog border |
| `DIALOG_TITLE` | YELLOW | BLUE | Popup dialog title |
| `DIALOG_SELECTED` | WHITE | RED | Selected dialog option |

---

## 10. Screen Prototypes

> Box drawing uses Unicode equivalents of DOS CP437.
> `►` (U+25BA) = selected row indicator (`MMRK_BOOK` = 0x11). Note: NOT `▶` (U+25B6) — CP437 0x11 maps to U+25BA specifically. The difference matters: U+25BA is 3 UTF-8 bytes, which breaks `str_pad()` byte-length assumptions.
> `■` = marked message indicator (`MMRK_MARK` = 0x10).
> Colours indicated in `[brackets]` after relevant sections.

---

### 10.1 Area List Screen

Reference: GEDW32 3.0.1 screenshot (archive).

```
┌─[GoldED 3.0.1]──────────────── Area List ──── 22 areas, 411 new ─┐  [RED@LGREY]
│  #  Area-Description                    Msgs─   New  EchoID              Grp│  [BLUE@LGREY]
├──────────────────────────────────────────────────────────────────────────────┤
│  6  REG GoldED reg.sites                1256      3  GOLDED.SITES          S │  [BLACK@LGREY]
│  7  R20 GoldED Svensk Support Konfer     279     27  R20_GOLDED            R │
│  8► INT GoldED Beta conference           505    133  GOLDED.BETA           I │  [WHITE@BLUE]
│  9  R23 GoldED support conference        537    235  GOLDED_R23.PNT        R │  [BLUE@LGREY unread]
│ 10  INT GoldED support conference        520    278  GOLDED                I │
│  Netmail areas                                                               │  [LBLUE@LGREY section]
│ 11  NET Mailer                             4      0  NET.ALL                 │
│ 12  <I> fdding@winboss.dk e-mail test o   18      0  NET.EMAIL.TEST          │
│ 13  NET FidoNet Z1                       271      0  NET.FIDOZ1              │
│ 19  NEI Netmail Wildcat Test (MSG1)        0      0  NET.TESTW               │
│  E-mail areas                                                                │  [LBLUE@LGREY section]
│ 20  QWK DataShopper EMail                  0      0  EMAIL.DATASHOP          │
│ 21  <I> JAVA mailing list                  0      0  IBM-JAVA                │
│ 24  EMX EMX mailing list                   0      0  LIST.EMX                │
│                                                                              │
└──────────────────────────────────────────────────────────────────────────────┘
 GoldED 3.0.1                  Area 8 of 22               22:30:26  [WHITE@BLUE]
```

**Column layout (80 columns, borders inclusive, 78 content chars between `│`):**

| Col | Width | Field |
|-----|-------|-------|
| 1–3 | 3 | Area number (right-justified) |
| 4 | 1 | Selection indicator (`►` or space) |
| 5 | 1 | Space |
| 6–8 | 3 | Area type code (`REG`, `NET`, `<I>`, etc.) |
| 9 | 1 | Space |
| 10–38 | 29 | Description (left-justified, truncated) |
| 39 | 1 | Space |
| 40–45 | 6 | Message count (right-justified, `-` if unscanned) |
| 46 | 1 | Changed indicator (space normally) |
| 47–52 | 6 | Unread count (right-justified, `-` if unscanned) |
| 53 | 1 | Space |
| 54–73 | 20 | EchoID (left-justified, truncated) |
| 74 | 1 | Space |
| 75–78 | 4 | Group ID (single char left-justified, rest spaces) |

**Section headers:**

When areas are sorted by type (`T` key in `AREALISTSORT`), a label row is injected at each type-group boundary. The row spans the full 78-char content width, indented by 2 spaces, rendered in `LBLUE@LGREY`. It does not count as a selectable row.

| Condition | Label |
|-----------|-------|
| First NET or NEI area | `Netmail areas` |
| First QWK or `<I>` area | `E-mail areas` |
| First LOCAL area | `Local areas` |

Section headers only appear when that type group is present and the sort order includes `T`.

**Sort order (`AREALISTSORT`):**

Source: `geglob.cpp` default `"FYTUE"`, confirmed in `archive/config/GOLDAREA.CFG`. Sort keys are applied left-to-right as a multi-key comparator (`gealst.cpp` `AreaListCmp`):

| Key | Meaning |
|-----|---------|
| `F` | Matched/pinned areas first (filter pattern match) |
| `Y` | Areas with any unread above areas with none |
| `T` | Area type order: NET → EMAIL → ECHO → LOCAL |
| `U` | Unread count descending (most unread first; zero-unread areas sort last) |
| `E` | EchoID alphabetically as final tiebreaker |

**Implementation note:** Until unread tracking is implemented (Phase 4), `Y` and `U` collapse — sort falls back to `E` (echoid/name alphabetically). Full `FYTUE` ordering requires `unread_count` to be populated per-area.

---

### 10.2 Message List Screen

```
┌─ NetMail ─────────────────── 12 messages, 2 new ─── 2:236/77 ─┐  [BORDER/TITLE=RED@LGREY]
│      #      From            Subject                    Date    │  [HEADER_LABEL=BLUE@LGREY]
├────────────────────────────────────────────────────────────────┤
│      1      Bjarne Hansen   Re: GoldED 3.0 beta       12 Mar 94 │  [LIST_NORMAL]
│      2      Uffe Sorensen   Nodelist update            12 Mar 94 │  [LIST_UNREAD=BLUE@LGREY]
│►   ■ 3      Odinn Sorensen  Re: GoldED keybindings     13 Mar 94 │  [LIST_SELECTED=WHITE@BLUE]
│      4   └  Lars Jensen     Re: GoldED keybindings     13 Mar 94 │
│      5   └  Peter Froerup   Re: GoldED keybindings     14 Mar 94 │
│      6      Thomas Nielsen  New beta available?         14 Mar 94 │  [LIST_UNREAD=BLUE@LGREY]
│                                                                │
│                                                                │
│                                                                │
│                                                                │
│                                                                │
│                                                                │
│                                                                │
│                                                                │
│                                                                │
│                                                                │
│                                                                │
│                                                                │
│                                                                │
│                                                                │
│                                                                │
│                                                                │
└────────────────────────────────────────────────────────────────┘
 GoldED 3.0.1                  Msg 3 of 12                13:45:22  [STATUS=WHITE@BLUE]
```

**Column layout:**

| Col | Width | Field |
|-----|-------|-------|
| 1–6 | 6 | Message number (right-justified) |
| 7 | 1 | Space |
| 8 | 1 | Bookmark (`►` U+25BA = 0x11 or space) |
| 9 | 1 | Mark (`■` = 0x10 or space) |
| 10 | 1 | Space |
| 11–18 | 8 | Thread tree structure (ASCII box chars, see §10.5) |
| 19–38 | 20 | From name (left-justified, truncated) |
| 39 | 1 | Space |
| 40–71 | 32 | Subject (left-justified, truncated) |
| 72–79 | 8 | Date (if enabled: `DD MMM YY`) |

---

### 10.3 Reader Screen

Reference: GEDW32 2.23b screenshot (archive).

```
┌─ <I> Funny Humor (Moderated) (2:236/77) ────────── REC.HUMOR.FUNNY ─┐  [RED@LGREY]
│ Msg: 260 of 290                                                      │  [BLUE@LGREY]
│ From: Elan Feingold <feingold@avette.zko.dec.com>  Tue 08 Aug 95 04:30 │
│ To  : ALL                                          Fri 11 Aug 95 02:21 │
│ Subj: How to get rid of unwanted phone calls.                         │
│ 1450                                                                  │
├──────────────────────────────────────────────────────────────────────┤  [YELLOW@LGREY]
│                                                                      │  [BLACK@LGREY]
│ I was just stepping into the shower this morning when my SO          │
│ handed me the phone, telling me it was someone from a long           │
│ distance company.                                                     │
│                                                                      │
│ Me:  Hello?                                                           │
│ Him: Hello, sir. I'm from <Major long distance carrier>.             │
│                                                                      │
│ --- GoldED 2.23b                                                      │  [LBLUE@LGREY]
│  * Origin: Somewhere (2:236/77)                                       │  [LBLUE@LGREY]
└──────────────────────────────────────────────────────────────────────┘
 GoldED 3.0.1             Read:ALL  Msg 260 of 290 (39 left)  16:11:57  [WHITE@BLUE]
```

**Header row layout (rows 0–6):**

| Row | Content | Notes |
|-----|---------|-------|
| 0 | `<type> <Area Name> (<nodeaddr>)  …  <EchoID>` | Top border. Type code + name + local AKA on left, echoid on right. |
| 1 | `Msg: N of Total` | Counter only. Reply links (`-prev +next *thread`) shown here when present. |
| 2 | `From: Name <address>`  +  written date right-aligned | Full internet address when present. Date: `Ddd DD Mon YY HH:MM`. |
| 3 | `To  : Name` + received/arrival date right-aligned | Second date is the arrival date (`MSGSCANNED` or kludge). |
| 4 | `Subj: Subject text` | |
| 5 | Message byte count (e.g. `1450`) | Raw size of the message body in bytes. |
| 6 | Separator (`├────...────┤`) | Divides header from body. |

**Date format:**

`Ddd DD Mon YY HH:MM` — e.g. `Tue 08 Aug 95 04:30`.  
Row 2 shows the **written** date (from the message header).  
Row 3 shows the **arrival** date (from kludge line `MSGID`, `INTL`, or filesystem timestamp). If absent, row 3 date is blank.

**Row 1 counter format (from `geview.cpp`):**
```
Relative mode : "Msg: 3 of 12"
Real msgno    : "Msg: #4729 [12]"
Reply links   : " -2" (replyto), " +4" (reply), " *5" (replynext)
```

**Address column positions (from `GOLDED.CFG` defaults):**
```
DISPHDRNAMESET   8 28    → col 8, 28 chars for name
DISPHDRNODESET  36 24    → col 36, 24 chars for FTN/email/internet address
DISPHDRDATESET -20 20    → 20 chars from right edge for date
```

---

### 10.4 Editor Screen

```
┌─ Composing new message ────────────────────────────────────── ─┐  [BORDER=YELLOW@LGREY,TITLE=RED@LGREY]
│                                                     14 Mar 94  │  [HEADER_LABEL=BLUE@LGREY]
│ From : Odinn Sorensen (2:236/77)                               │  [HEADER_INPUT=BLACK@LGREY]
│ To   : Lars Jensen                                             │
│ Subj : Re: GoldED keybindings                                  │
├────────────────────────────────────────────────────────────────┤
│                                                                │  [BODY_NORMAL=BLACK@LGREY]
│ Lars Jensen wrote:                                             │
│                                                                │
│ > I noticed the key binding for AREA select seems odd —        │  [BODY_QUOTE1=BLUE@LGREY]
│ > pressing Right should open the area but it doesn't?          │
│                                                                │
│ █                                                              │  cursor
│                                                                │
│                                                                │
│                                                                │
│                                                                │
│                                                                │
│                                                                │
│                                                                │
│                                                                │
│                                                                │
│                                                                │
│                                                                │
│                                                                │
└────────────────────────────────────────────────────────────────┘
 GoldED 3.0.1          [INS] Line 11, Col 1  F2=Save  Esc=Abort   [STATUS=WHITE@BLUE]
```

**Header row layout (rows 0–5):**

Source: `geview.cpp` `GMsgHeaderView::Paint()` + `gehdre.cpp` `EditHeaderinfo()`.
The header is the same shared component as the reader. Row 1 always comes from
`geview.cpp` — in compose mode there is no message counter, so only the date is shown.
Rows 2–4 are editable fields (`gehdre.cpp` `add_field()` calls at rows 2, 3, 4).
There is **no Area field** in the original.

| Row | Content |
|-----|---------|
| 0 | Top border with title (`Composing new message`) |
| 1 | Date (right-aligned; no Msg counter for new messages) |
| 2 | `From : <name> (<address>)` |
| 3 | `To   : <name>` |
| 4 | `Subj : <subject>` |
| 5 | Separator |

**Edit buffer:**
- Rows 6–23: 18 lines visible
- Scrolls vertically; cursor position tracked
- `[INS]` / `[OVR]` mode indicator in status bar
- Hard-wrapped at column 79 (configurable via `editwrap`)
- Quote lines prefixed `> ` on reply; preserved verbatim

---

### 10.5 Thread Tree Characters

From `gemlst.cpp`, the thread tree is built with box-drawing characters:

```
  └ message (last or only reply)
  ├ message (more siblings below)
    └ nested reply (last)
    ├ nested reply (more below)

Example in message list:
      1      Odinn Sorensen   Original message
      2   └  Lars Jensen      First reply
      3   ├  Peter Froerup    Second reply
      4   │  └  Uffe Nielsen  Reply to Peter
      5   └  Thomas Hansen    Last reply
```

Characters used (Unicode equivalents of DOS CP437):
- `└` (U+2514) — last or only child
- `├` (U+251C) — child with more siblings
- `│` (U+2502) — continuation line (parent has more children)
- `─` (U+2500) — horizontal connector

---

### 10.6 Popup Dialogs

```
            ┌─ Delete message? ──────────┐    [DIALOG_BORDER=LBLUE@BLUE]
            │                            │    [DIALOG_BG=LGREY@BLUE]
            │  ► Yes — delete message    │    [DIALOG_SELECTED=WHITE@RED]
            │    No  — keep message      │
            │                            │
            └────────────────────────────┘
```

```
            ┌─ Wait ─────────────────────┐
            │                            │
            │    Processing messages...  │
            │    [██████████░░░░░░░░░░]  │
            │         42 of 100          │
            └────────────────────────────┘
```

Dialogs are centred on screen. Shadow rendered one row/col offset in DGREY@BLACK.

---

## 11. Status Bar

Source: `geutil.cpp`, `update_statuslines()`

**Format:**
```
 GoldED 3.0.1  [status message ............................]  13:45:22
│←── ~20 ──→│  │←──────────── variable ──────────────→│  │←──10──→│
```

- Left: GoldED version string (or custom `STATUSLINEHELP` text)
- Centre: Context-dependent status message (area name, message position, editor mode)
- Right: Clock in `HH:MM:SS` (if `STATUSLINECLOCK=YES`)
- Colour: WHITE on BLUE throughout
- Separators between sections: `│`

**Context-specific status content:**

| Screen | Status content |
|--------|---------------|
| Area list | `Area N of Total (X new)` |
| Message list | `Msg N of Total` |
| Reader | `Msg N of Total (X new)` |
| Editor | `[INS/OVR] Line N, Col N  F2=Save  Esc=Abort` |

---

## 12. Keyboard Model

Source: `archive/config/GOLDKEYS.CFG`, `gckeys.cpp`

Key notation: `@` = Alt, `^` = Ctrl, `#` = Shift, bare = unmodified.

### Global

| Key | Action |
|-----|--------|
| `Esc` | Back / abort |
| `Tab` | Jump to next match (area list) |

### Area List

| Key | Action |
|-----|--------|
| `↑` / `↓` | Move selection |
| `PgUp` / `PgDn` | Scroll page |
| `Home` | Go to first area |
| `End` | Go to last area |
| `Enter` / `→` | Open selected area |
| `Ins` / `@T` | Toggle mark on area |
| `@S` | Scan areas for new messages |
| `@H` | Heat (mark all read) |
| `@J` | Jump to next area with new |
| `Tab` / `^Enter` | Jump to next match |
| `Esc` / `@X` / `@F4` | Exit |

### Message List

| Key | Action |
|-----|--------|
| `↑` / `↓` | Move selection |
| `PgUp` / `PgDn` | Scroll page |
| `Home` | Go to first message |
| `End` | Go to last message |
| `→` | Go to next message |
| `←` | Go to previous message |
| `Enter` | Open selected message in reader |
| `Space` | Toggle mark on message |
| `Tab` | Toggle bookmark |
| `^D` | Toggle date column |
| `^B` | Toggle wide subject display |
| `@S` | Marking options |
| `Esc` | Return to area list |

### Reader

| Key | Action |
|-----|--------|
| `↓` | Scroll down one line |
| `↑` | Scroll up one line |
| `PgDn` / `Space` | Page down |
| `PgUp` | Page up |
| `Home` | Top of message |
| `End` | Bottom of message |
| `→` | Next message |
| `←` | Previous message |
| `@→` / `@U` | Next unread message |
| `@←` | Previous unread message |
| `<` | First message in area |
| `>` | Last message in area |
| `-` | Go to message this replies to |
| `+` | Go to first reply |
| `*` | Go to next sibling reply |
| `@R` | Reply to message |
| `@Q` | Quote-reply to message |
| `@E` | New message |
| `@D` / `F5` | Delete message |
| `@L` / `F9` | Back to message list |
| `@H` | Toggle hidden lines |
| `@K` | Toggle kludge lines |
| `@J` | Toggle read/unread |
| `@Z` / `^D` | Find / search |
| `F` | Find in message body |
| `@F` | Find next |
| `@P` | Toggle scrollbar |
| `@Y` | Toggle real message numbers |
| `#F9` | Thread tree view |
| `F10` | Nodelist lookup (origin) |

### Editor

| Key | Action |
|-----|--------|
| `←` / `→` / `↑` / `↓` | Cursor movement |
| `^←` | Word left |
| `^→` | Word right |
| `Home` | Begin of line |
| `End` | End of line |
| `^Home` | Top of message |
| `^End` | Bottom of message |
| `PgUp` / `PgDn` | Page up / down |
| `Enter` | New line (hard wrap) |
| `Ins` | Toggle insert / overwrite |
| `Del` | Delete character at cursor |
| `Backspace` | Delete character left |
| `@Y` / `@K` | Delete to end of line |
| `@D` | Delete entire line |
| `^Backspace` / `@F5` | Delete word left |
| `^F6` / `@T` | Delete word right |
| `Tab` | Insert tab |
| `F2` / `@S` / `^Z` | Save message |
| `Esc` / `@X` / `@F4` | Abort (ask confirmation) |
| `F3` / `@I` | Import text file |
| `@B` | Reflow paragraph |
| `@H` | Edit message header |
| `@C` | Copy block |
| `@M` | Cut block |
| `@P` | Paste block |
| `@U` | Undo last delete |
| `@Q` | Import quote buffer |
| `@W` | Export text to file |
| `@1` | Uppercase selection |
| `@2` | Lowercase selection |
| `@3` | Toggle case |

---

## 13. Message Body Rendering Rules

Source: `geview.cpp`, `gmo_msg.h`, `geline.cpp`

### Line Types and Colours

| Line type | Detection | Colour |
|-----------|-----------|--------|
| Normal | Default | `BODY_NORMAL` (BLACK@LGREY) |
| Quote level 1, 3, 5 (odd) | Starts with `>`, `|`, `:` | `BODY_QUOTE1` (BLUE@LGREY) |
| Quote level 2, 4, 6 (even) | Double-quoted | `BODY_QUOTE2` (MAGENTA@LGREY) |
| Tearline | Starts with `--- ` | `BODY_TEARLINE` (LBLUE@LGREY) |
| Origin | Starts with ` * Origin:` | `BODY_ORIGIN` (LBLUE@LGREY) |
| Tagline | Stored separately, appended | `BODY_TAGLINE` (LBLUE@LGREY) |
| Kludge | Starts with `\x01` | `BODY_KLUDGE` (DGREY@LGREY) |
| Hidden | Flagged kludge-style | `BODY_KLUDGE` (DGREY@LGREY), toggleable |
| Search hit | Matched text region | `BODY_SEARCH` (WHITE+BLINK@RED) |
| Block selected | User selection | `BODY_BLOCK` (BLACK@CYAN) |

### Quote Detection

Multiple characters are valid quote prefixes: `>`, `|`, `:`
Quote depth = count of quote chars at line start.
Odd depth → QUOTE1 colour. Even depth → QUOTE2 colour.

### Tearline Format
```
--- GoldED 3.0.1 Beta 3
```
Stored in `msg->tearline[80]`. Rendered after message body, before origin.

### Origin Line Format
```
 * Origin: Goldware BBS, Haslev (2:236/77)
```
Stored in `msg->origin[160]`.

### Rules

- Preserve all whitespace exactly
- No text reflow in reader
- No markdown or HTML interpretation
- Kludge lines (starting with `\x01`) hidden by default, toggleable with `@K`
- Hidden lines (HIDD flag) hidden by default, toggleable with `@H`
- Scrollbar optional at right column (toggleable with `@P`)

---

## 14. Editor Behaviour

### Text buffer

- Plain text only
- Hard wraps at configured column (default 79)
- `[INS]` / `[OVR]` mode shown in status bar
- Undo available via `@U` (single level)

### Reply quoting

- Original message body is pre-filled with `> ` prefix on each line
- Existing quote prefixes are preserved (`>` → `> >`)
- Tearline and origin are not quoted

Example:
```
Lars Jensen wrote:

> Odinn Sorensen wrote:
>
> > Is the keymap documented?
>
> Yes, see GOLDED.CFG section 4.

That section covers GOLDKEYS.CFG, not GOLDED.CFG.
```

### Tagline and origin

- Tagline appended automatically from `TAGLINES.TXT` (random selection)
- Tearline appended: `--- GoldED <version>`
- Origin line appended from `ORIGIN.LST` (or configured default)
- Node address from active AKA for the area

---

## 15. UI Architecture

Single Livewire component: `GoldedShell`

Responsibilities:
- Holds all UI state
- Controls screen transitions
- Handles keyboard input
- Renders full interface

No traditional routing between screens. All transitions are state changes within the component.

### Rendering

- `<pre>` element with monospace font
- CSS class per line type (drives colour)
- Fixed 80-column width (see Q1)
- No word-wrap on the `<pre>` element
- Font: any CP437-faithful monospace (e.g. `PxPlus IBM VGA8`, `Fixedsys`, `Courier New`)

---

## 16. State Model

```php
state = [
  'screen'         => 'areas|messages|reader|editor',
  'datasetId'      => int|null,
  'areaId'         => int|null,
  'messageId'      => int|null,
  'draftId'        => int|null,
  'selectionIndex' => int,       // current highlighted row
  'scrollOffset'   => int,       // body scroll position (reader)
  'topOffset'      => int,       // list scroll position (list screens)
  'showKludges'    => bool,      // reader: show kludge lines
  'showHidden'     => bool,      // reader: show hidden lines
  'showScrollbar'  => bool,      // reader: show page scrollbar
  'realMsgno'      => bool,      // reader: show real vs relative numbers
  'showDate'       => bool,      // message list: show date column
  'insertMode'     => bool,      // editor: insert vs overwrite
  'cursorRow'      => int,       // editor: cursor line
  'cursorCol'      => int,       // editor: cursor column
];
```

---

## 17. Implementation Phases

### Phase 1 — Shell

- Livewire shell with `<pre>` rendering
- Hardcoded static ASCII layouts for all four screens
- No data, no interaction — pure visual fidelity check
- **Deliverable:** All four screens render correctly at 80 columns with correct colours

### Phase 2 — Navigation

- Area list → message list → reader flow
- Keyboard input wired up
- State machine implemented
- **Deliverable:** Can navigate with keyboard through hardcoded data

### Phase 3 — Reader with real data

- MSG importer (simplest format, no dependencies)
- Load real dataset from `archive/messages/MSG/`
- Reader renders real message body faithfully
- Quote levels, tearlines, origins coloured correctly
- **Deliverable:** Can read real 1990s FidoNet messages

### Phase 4 — Full navigation

- Message list with all columns
- Thread tree rendering
- Unread tracking
- Area list with counts
- **Deliverable:** Full read-only browsing of real dataset

### Phase 5 — Additional importers

- JAM importer (main production format, best reply linking)
- Squish importer
- Hudson / Goldbase importer
- **Deliverable:** All `archive/messages/` formats loadable

### Phase 6 — Editor

- Text editing with cursor
- Reply quoting
- Draft storage
- Tagline / tearline / origin appended
- **Deliverable:** Can compose and save messages

### Phase 7 — Polish

- Character encoding (CP850 → UTF-8 transcoding)
- XLAT table support for international characters
- Search / highlight
- Thread tree view
- All keyboard shortcuts
- **Deliverable:** Full feature parity with original GoldED read/write workflow

---

## 18. Development Philosophy — KISS my DDD with TDD

### KISS

- Keep structure simple and intentional
- Avoid premature abstraction
- Build one clear path before generalising
- Prefer working behaviour over architectural purity

### DDD

Real domain language:
- `Message`, `Area`, `Dataset`, `Draft`
- Legacy formats isolated in Infrastructure layer
- No format-specific concerns in Domain or UI
- Quoting, reply logic, line-type detection belong in Domain

### TDD

Write tests for:
- Importers against real sample files from `archive/messages/`
- Line type detection (quote, tearline, origin, kludge)
- Quote depth counting
- Thread tree generation
- Editor operations (cursor movement, insert, delete, reflow)
- State transitions in navigation

Protect:
- Raw message body integrity (never mutate original text)
- Line endings (FidoNet uses `\r` — handle carefully)
- CP850 encoding on import → UTF-8 in DB

---

## 19. Definition of Done

The system is complete when:

- All supported formats can be imported
- User can navigate with keyboard only
- Messages render correctly and faithfully (colours, line types, thread tree)
- User can compose reply and new message
- UI is indistinguishable from original GoldED at a glance

---

## 20. Anti-Goals

- No modern UI components (cards, panels, gradients)
- No premature abstraction
- No visual over-styling
- No mutation of historic message data
- No FidoNet network connectivity (v1)

---

## 21. Internal Motto

```
Make the domain obvious.
Make the UI faithful.
Make the weirdness testable.
```

---

## 22. Reference Material

All reference material is in `archive/` (co-located in the monorepo):

| Path | Contents |
|------|----------|
| `archive/source/golded3/` | GoldED 3 C++ source (canonical UI reference) |
| `archive/source/golded4/` | GoldED 4 source |
| `archive/source/goldlib/` | Shared library with all format readers |
| `archive/messages/MSG/` | Real FidoNet .msg archives (test data) |
| `archive/messages/JAM/` | JAM message bases (test data) |
| `archive/messages/SQUISH/` | Squish message bases (test data) |
| `archive/config/GOLDED.CFG` | Live GoldED configuration (Goldware BBS) |
| `archive/config/GEDCOLOR.CFG` | Complete colour scheme definitions |
| `archive/config/GOLDKEYS.CFG` | Complete key binding definitions |
| `archive/config/XLAT/` | Character set translation tables (64 files) |
| `archive/config/GOLDAREA.CFG` | Message area definitions |
| `archive/config/TAGLINES.TXT` | Tagline database |
| `archive/bbs/nodelists/` | Danish FidoNet point list (DK-POINT.LST etc.) |
