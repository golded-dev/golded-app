<?php

use App\Golded\AnsiRenderer;

it('emits cursor-home escape at the start of renderScreen', function (): void {
    $renderer = new AnsiRenderer;
    $output = $renderer->renderScreen([], 80, 25);

    expect($output)->toStartWith("\033[H");
});

it('maps cga-black-lgrey to black-on-light-grey ANSI codes', function (): void {
    $renderer = new AnsiRenderer;
    $screen = [[['Hello', 'cga-black-lgrey']]];
    $output = $renderer->renderScreen($screen, 80, 25);

    expect($output)->toContain('[30;47mHello[0m');
});

it('maps cga-white-blue to white-on-blue ANSI codes', function (): void {
    $renderer = new AnsiRenderer;
    $screen = [[['Status', 'cga-white-blue']]];
    $output = $renderer->renderScreen($screen, 80, 25);

    expect($output)->toContain('[97;44mStatus[0m');
});

it('maps cga-yellow-lgrey to yellow-on-light-grey ANSI codes', function (): void {
    $renderer = new AnsiRenderer;
    $screen = [[['│', 'cga-yellow-lgrey']]];
    $output = $renderer->renderScreen($screen, 80, 25);

    expect($output)->toContain("\033[93;47m".'│'."\033[0m");
});

it('maps cga-blue-lgrey to blue-on-light-grey ANSI codes', function (): void {
    $renderer = new AnsiRenderer;
    $screen = [[['text', 'cga-blue-lgrey']]];
    $output = $renderer->renderScreen($screen, 80, 25);

    expect($output)->toContain('[34;47mtext[0m');
});

it('maps cga-red-lgrey to red-on-light-grey ANSI codes', function (): void {
    $renderer = new AnsiRenderer;
    $screen = [[['title', 'cga-red-lgrey']]];
    $output = $renderer->renderScreen($screen, 80, 25);

    expect($output)->toContain('[31;47mtitle[0m');
});

it('maps cga-dgrey-lgrey to dark-grey-on-light-grey ANSI codes', function (): void {
    $renderer = new AnsiRenderer;
    $screen = [[['kludge', 'cga-dgrey-lgrey']]];
    $output = $renderer->renderScreen($screen, 80, 25);

    expect($output)->toContain('[90;47mkludge[0m');
});

it('maps cga-lblue-lgrey to light-blue-on-light-grey ANSI codes', function (): void {
    $renderer = new AnsiRenderer;
    $screen = [[['tear', 'cga-lblue-lgrey']]];
    $output = $renderer->renderScreen($screen, 80, 25);

    expect($output)->toContain('[94;47mtear[0m');
});

it('maps cga-yellow-blue to yellow-on-blue ANSI codes', function (): void {
    $renderer = new AnsiRenderer;
    $screen = [[['header', 'cga-yellow-blue']]];
    $output = $renderer->renderScreen($screen, 80, 25);

    expect($output)->toContain('[93;44mheader[0m');
});

it('renders multiple segments in sequence', function (): void {
    $renderer = new AnsiRenderer;
    $screen = [[
        ['│', 'cga-yellow-lgrey'],
        ['text', 'cga-black-lgrey'],
        ['│', 'cga-yellow-lgrey'],
    ]];
    $output = $renderer->renderScreen($screen, 80, 25);

    expect($output)
        ->toContain("\033[93;47m│\033[0m")
        ->toContain("\033[30;47mtext\033[0m");
});

it('positions each row with absolute cursor escape', function (): void {
    $renderer = new AnsiRenderer;
    $screen = [
        [['row one', 'cga-black-lgrey']],
        [['row two', 'cga-black-lgrey']],
    ];
    $output = $renderer->renderScreen($screen, 80, 25);

    expect($output)
        ->toContain('row one')
        ->toContain('row two')
        ->toContain("\033[1;1H")
        ->toContain("\033[2;1H");
});

it('falls back to default reset for unknown CGA class', function (): void {
    $renderer = new AnsiRenderer;
    $screen = [[['unknown', 'cga-unknown']]];
    $output = $renderer->renderScreen($screen, 80, 25);

    expect($output)->toContain('unknown[0m');
});
