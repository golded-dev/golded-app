<?php

declare(strict_types=1);

use App\Domain\CharsetDetector;

// ── Tracer bullet ─────────────────────────────────────────────────────────────

it('defaults to CP850 when no CHRS kludge is present', function (): void {
    expect(CharsetDetector::detect("Hello world\nNo kludges here"))->toBe('CP850');
});

// ── Known charset names ───────────────────────────────────────────────────────

it('detects IBMPC as CP850', function (): void {
    expect(CharsetDetector::detect("\x01CHRS: IBMPC 2\nBody text"))->toBe('CP850');
});

it('detects CP850 as CP850', function (): void {
    expect(CharsetDetector::detect("\x01CHRS: CP850 2\nBody text"))->toBe('CP850');
});

it('detects IBM as CP850', function (): void {
    expect(CharsetDetector::detect("\x01CHRS: IBM 2\nBody text"))->toBe('CP850');
});

it('detects LATIN-1 as ISO-8859-1', function (): void {
    expect(CharsetDetector::detect("\x01CHRS: LATIN-1 2\nBody text"))->toBe('ISO-8859-1');
});

it('detects 8859-1 as ISO-8859-1', function (): void {
    expect(CharsetDetector::detect("\x01CHRS: 8859-1 2\nBody text"))->toBe('ISO-8859-1');
});

it('detects ASCII as ASCII', function (): void {
    expect(CharsetDetector::detect("\x01CHRS: ASCII 2\nBody text"))->toBe('ASCII');
});

it('detects CP866 as CP866', function (): void {
    expect(CharsetDetector::detect("\x01CHRS: CP866 2\nBody text"))->toBe('CP866');
});

it('detects KOI8-R as KOI8-R', function (): void {
    expect(CharsetDetector::detect("\x01CHRS: KOI8-R 2\nBody text"))->toBe('KOI8-R');
});

// ── Edge cases ────────────────────────────────────────────────────────────────

it('handles CHARSET: kludge as well as CHRS:', function (): void {
    expect(CharsetDetector::detect("\x01CHARSET: LATIN-1\nBody text"))->toBe('ISO-8859-1');
});

it('falls back to CP850 for unknown charset names', function (): void {
    // Malformed values from real archives e.g. "IBMPC HEJ", "LATIN-1 HELLO"
    // should use only the first token, mapped if known
    expect(CharsetDetector::detect("\x01CHRS: IBMPC HEJ\nBody"))->toBe('CP850');
});

it('falls back to CP850 for completely unrecognised names', function (): void {
    expect(CharsetDetector::detect("\x01CHRS: FIDOMAZ 2\nBody"))->toBe('CP850');
});
