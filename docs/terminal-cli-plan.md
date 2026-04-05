# Plan: Terminal CLI Version of GoldED 7

## Context

golded-app is a browser-based rebuild of GoldED using Laravel + Livewire. The UI lives in a single Livewire SFC (`⚡golded-shell.blade.php`) that manages state, builds 25-line screen output as HTML via segment helpers, and dispatches key events.

**Goal:** `php artisan golded:run` — renders the GoldED screens in a real terminal using CGA→ANSI color mapping and raw keyboard input, filling the full terminal size.

The spec (Q1) is explicit: **never hardcode 80 columns**. Minimum is 80×25, but the layout must adapt to actual terminal dimensions. `TerminalIO::width()` and `TerminalIO::height()` drive everything — `GoldedState` receives `$cols`/`$rows` on construction and all screen builders size their content areas accordingly. `SIGWINCH` (terminal resize) triggers a re-render with updated dimensions.

Domain logic (models, ThreadTree, LineClassifier, importers) is already fully decoupled. This refactor separates **state + screen data** from **rendering** so both browser and terminal share the same source.

---

## Architecture

Three-layer separation:

| Layer | Class | Output |
|-------|-------|--------|
| State + screen data | `GoldedState` | `Segment[][]` — raw `[text, cgaClass]` tuples |
| Web renderer | `HtmlRenderer` | HTML strings for Livewire |
| Terminal renderer | `AnsiRenderer` | ANSI escape strings |
| Terminal I/O | `TerminalIO` | Raw mode, key reading |
| Entry point | `golded:run` command | Event loop |

The Livewire SFC becomes a thin wrapper: holds a `GoldedState`, delegates key handling, calls `HtmlRenderer`.

---

## Files

| File | Role |
|------|------|
| `resources/views/pages/⚡golded-shell.blade.php` | Slimmed to ~50 lines — thin Livewire wrapper |
| `app/Golded/GoldedState.php` | **NEW** — plain PHP state machine |
| `app/Golded/HtmlRenderer.php` | **NEW** — segments → HTML |
| `app/Golded/AnsiRenderer.php` | **NEW** — segments → ANSI escape codes |
| `app/Golded/TerminalIO.php` | **NEW** — raw mode + key sequence parsing |
| `app/Console/Commands/Run.php` | **NEW** — `golded:run` command |
| `tests/Feature/GoldedShellTest.php` | Must stay green throughout |

---

## Workflow Rules

- **Branch first:** `git checkout -b feature/terminal-cli`
- **TDD:** Write failing test first, implement, refactor — for every new class
- **Phase gate:** Each phase ends with pint + phpstan + rector + full test run, then a commit
- **Pint:** `vendor/bin/pint --dirty --format agent`
- **PHPStan:** `./vendor/bin/phpstan analyse --memory-limit=256M`
- **Rector:** `vendor/bin/rector process app/Golded`

---

## Phases

### Phase 1 — Branch + Extract `GoldedState`

1. `git checkout -b feature/terminal-cli`
2. Write `GoldedStateTest.php` — port existing GoldedShell scenarios against `GoldedState` directly — **red**
3. Extract `GoldedState` from SFC into `app/Golded/GoldedState.php` — **green**
   - State properties: `$screen`, `$areaId`, `$messageId`, `$selectionIndex`, `$scrollOffset`, `$topOffset`, `$showKludges`, `$cols` (default 80), `$rows` (default 25)
   - Computed data: `areas()`, `messages()`, `currentMessage()` (lazy-init, no Livewire `#[Computed]`)
   - All navigation: `handleKey()`, `openArea()`, `openMessage()`, `markRead()`, `markUnread()`, `nextMessage()`, thread navigation, etc.
   - Screen builders: `areasScreen()`, `messagesScreen()`, `readerScreen()`, `editorScreen()` returning `Segment[][]` (not HTML) — all column widths, viewport heights, and line lengths derived from `$cols`/`$rows`, never hardcoded
   - Helpers: `ln()`, `row()`, `sep()`, `bottom()`, `top()`, `status()` — accept `$width` param (default `$this->cols`), return raw segments, no `<span>` or `e()`
4. Pint → PHPStan → Rector → tests
5. **Commit:** `refactor: extract GoldedState from Livewire SFC`

---

### Phase 2 — `HtmlRenderer` + Slim SFC

1. Write `HtmlRendererTest.php` — **red**
2. Implement `HtmlRenderer` — wraps segments in `<span class="{cgaClass}">{e(text)}</span>`, pads to width — **green**
3. Update Livewire SFC to hold `GoldedState`, delegate all methods, call `HtmlRenderer::renderScreen()`
4. `GoldedShellTest` must stay green (regression check)
5. **Browser smoke test:** open web app, visually verify area list → messages → reader, check for regressions using Chrome DevTools
6. Pint → PHPStan → Rector → tests
7. **Commit:** `refactor: introduce HtmlRenderer, slim Livewire SFC`

---

### Phase 3 — `AnsiRenderer`

1. Write `AnsiRendererTest.php` — verify ANSI escape output for known segments — **red**
2. Implement `AnsiRenderer` with full CGA→ANSI color map — **green**

   ```
   cga-black-lgrey   → \033[30;47m     cga-blue-lgrey    → \033[34;47m
   cga-yellow-lgrey  → \033[93;47m     cga-white-blue    → \033[97;44m
   cga-dgrey-lgrey   → \033[90;47m     cga-lblue-lgrey   → \033[94;47m
   cga-red-lgrey     → \033[31;47m     ... (full 16-color map)
   ```

   `renderScreen()` returns a single string: `\033[H` + 25 lines (color → text → reset), written atomically to avoid flicker.

3. Pint → PHPStan → Rector → tests
4. **Commit:** `feat: add AnsiRenderer with CGA→ANSI color mapping`

---

### Phase 4 — `TerminalIO`

1. Write `TerminalIOTest.php` — test key sequence parsing in isolation (not raw terminal calls) — **red**
2. Implement `TerminalIO` — **green**
   - `rawMode()` → `stty raw -echo`
   - `restore()` → `stty sane`
   - `readKey()` → `fread(STDIN, 10)` + byte sequence → key name map
   - `width()` / `height()` → `tput cols` / `tput lines` with 80/25 fallback

   Key map:
   ```
   \033[A  → ArrowUp           \033[B  → ArrowDown
   \033[C  → ArrowRight        \033[D  → ArrowLeft
   \033[5~ → PageUp            \033[6~ → PageDown
   \033[H  → Home              \033[F  → End
   \033[1;3C → Alt+ArrowRight  \033[1;3D → Alt+ArrowLeft
   \033j   → Alt+j             \033u   → Alt+u
   \033 (alone, 50ms timeout)  → Escape
   ```

3. Pint → PHPStan → Rector → tests
4. **Commit:** `feat: add TerminalIO for raw mode and key sequence parsing`

---

### Phase 5 — `golded:run` Command

1. Implement `app/Console/Commands/Run.php` with signature `golded:run`

   Event loop:
   ```php
   public function handle(TerminalIO $io): void
   {
       $io->rawMode();
       $io->watchResize(); // pcntl_signal(SIGWINCH, ...) to detect resize

       $state    = new GoldedState($io->width(), $io->height());
       $renderer = new AnsiRenderer;

       echo "\033[2J\033[H\033[?25l"; // clear screen, hide cursor

       try {
           while (true) {
               if ($io->resized()) {
                   $state->resize($io->width(), $io->height());
               }
               echo $renderer->renderScreen($state->currentScreen(), $state->cols, $state->rows);
               $key = $io->readKey();
               if ($key === 'q' || $key === 'Ctrl+q') { break; }
               $state->handleKey($key);
           }
       } finally {
           $io->restore();
           echo "\033[2J\033[H\033[?25h"; // clear, show cursor
       }
   }
   ```

2. Pint → PHPStan → Rector → tests
3. **Manual verify:** `php artisan golded:run` — all 3 screens navigable, keyboard works, Esc returns correctly
4. **Commit:** `feat: add golded:run Artisan command`

---

## Verification

```bash
# After each phase
vendor/bin/pint --dirty --format agent
./vendor/bin/phpstan analyse --memory-limit=256M
vendor/bin/rector process app/Golded
php artisan test --compact

# Final manual check
php artisan golded:run
# Navigate: area list → Enter → messages → Enter → reader → scroll → Esc → Esc
```
