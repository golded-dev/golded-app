<?php

use App\Golded\TerminalIO;

// Key sequence parsing tested in isolation via parseKey() — no raw terminal needed.

it('parses ArrowUp sequence', function (): void {
    $io = new TerminalIO;
    expect($io->parseKey("\033[A"))->toBe('ArrowUp');
});

it('parses ArrowDown sequence', function (): void {
    $io = new TerminalIO;
    expect($io->parseKey("\033[B"))->toBe('ArrowDown');
});

it('parses ArrowRight sequence', function (): void {
    $io = new TerminalIO;
    expect($io->parseKey("\033[C"))->toBe('ArrowRight');
});

it('parses ArrowLeft sequence', function (): void {
    $io = new TerminalIO;
    expect($io->parseKey("\033[D"))->toBe('ArrowLeft');
});

it('parses PageUp sequence', function (): void {
    $io = new TerminalIO;
    expect($io->parseKey("\033[5~"))->toBe('PageUp');
});

it('parses PageDown sequence', function (): void {
    $io = new TerminalIO;
    expect($io->parseKey("\033[6~"))->toBe('PageDown');
});

it('parses Home sequence', function (): void {
    $io = new TerminalIO;
    expect($io->parseKey("\033[H"))->toBe('Home');
});

it('parses End sequence', function (): void {
    $io = new TerminalIO;
    expect($io->parseKey("\033[F"))->toBe('End');
});

it('parses Alt+ArrowRight sequence', function (): void {
    $io = new TerminalIO;
    expect($io->parseKey("\033[1;3C"))->toBe('Alt+ArrowRight');
});

it('parses Alt+ArrowLeft sequence', function (): void {
    $io = new TerminalIO;
    expect($io->parseKey("\033[1;3D"))->toBe('Alt+ArrowLeft');
});

it('parses Alt+j sequence', function (): void {
    $io = new TerminalIO;
    expect($io->parseKey("\033j"))->toBe('Alt+j');
});

it('parses Alt+u sequence', function (): void {
    $io = new TerminalIO;
    expect($io->parseKey("\033u"))->toBe('Alt+u');
});

it('parses bare Escape (single \033 byte)', function (): void {
    $io = new TerminalIO;
    expect($io->parseKey("\033"))->toBe('Escape');
});

it('parses Enter (carriage return)', function (): void {
    $io = new TerminalIO;
    expect($io->parseKey("\r"))->toBe('Enter');
});

it('parses printable characters as-is', function (): void {
    $io = new TerminalIO;
    expect($io->parseKey('k'))->toBe('k');
    expect($io->parseKey('+'))->toBe('+');
    expect($io->parseKey('-'))->toBe('-');
    expect($io->parseKey('*'))->toBe('*');
    expect($io->parseKey('q'))->toBe('q');
});

it('returns raw bytes for unrecognised sequences', function (): void {
    $io = new TerminalIO;
    expect($io->parseKey("\033[999~"))->toBe("\033[999~");
});
