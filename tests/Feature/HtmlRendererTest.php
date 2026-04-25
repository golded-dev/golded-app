<?php

use App\Golded\HtmlRenderer;

it('wraps segment in span with class', function (): void {
    $renderer = new HtmlRenderer;
    $screen = [[['Hello', 'cga-black-lgrey']]];

    $lines = $renderer->renderScreen($screen, 80);

    expect($lines[0])->toContain('<span class="cga-black-lgrey">Hello</span>');
});

it('renders multiple segments on one row', function (): void {
    $renderer = new HtmlRenderer;
    $screen = [[['foo', 'cga-black-lgrey'], ['bar', 'cga-blue-lgrey']]];

    $lines = $renderer->renderScreen($screen, 80);

    expect($lines[0])
        ->toContain('<span class="cga-black-lgrey">foo</span>')
        ->toContain('<span class="cga-blue-lgrey">bar</span>');
});

it('HTML-escapes segment text', function (): void {
    $renderer = new HtmlRenderer;
    $screen = [[['<b>bold</b>', 'cga-black-lgrey']]];

    $lines = $renderer->renderScreen($screen, 80);

    expect($lines[0])
        ->toContain('&lt;b&gt;bold&lt;/b&gt;');
    expect($lines[0])->not()->toContain('<b>bold</b>');
});

it('pads short rows to $cols with spaces', function (): void {
    $renderer = new HtmlRenderer;
    $screen = [[['Hi', 'cga-black-lgrey']]];

    $lines = $renderer->renderScreen($screen, 10);

    // Row should total 10 visible chars (2 from "Hi" + 8 padding)
    $plain = strip_tags($lines[0]);
    expect(mb_strlen($plain))->toBe(10);
});

it('returns one HTML string per screen row', function (): void {
    $renderer = new HtmlRenderer;
    $screen = [
        [['row one', 'cga-black-lgrey']],
        [['row two', 'cga-blue-lgrey']],
    ];

    $lines = $renderer->renderScreen($screen, 80);

    expect($lines)->toHaveCount(2)
        ->and($lines[0])->toContain('row one')
        ->and($lines[1])->toContain('row two');
});

it('renders ampersands correctly escaped', function (): void {
    $renderer = new HtmlRenderer;
    $screen = [[['a & b', 'cga-black-lgrey']]];

    $lines = $renderer->renderScreen($screen, 80);

    expect($lines[0])->toContain('a &amp; b');
});
