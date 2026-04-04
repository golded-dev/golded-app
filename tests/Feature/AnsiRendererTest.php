<?php

use App\Golded\AnsiRenderer;

it('emits cursor-home escape at the start of renderScreen', function () {
    $renderer = new AnsiRenderer;
    $output = $renderer->renderScreen([], 80, 25);

    expect($output)->toStartWith("\033[H");
});

it('maps cga-black-lgrey to black-on-light-grey ANSI codes', function () {
    $renderer = new AnsiRenderer;
    $screen = [[['Hello', 'cga-black-lgrey']]];
    $output = $renderer->renderScreen($screen, 80, 25);

    expect($output)->toContain("\033[30;47m".'Hello'."\033[0m");
});

it('maps cga-white-blue to white-on-blue ANSI codes', function () {
    $renderer = new AnsiRenderer;
    $screen = [[['Status', 'cga-white-blue']]];
    $output = $renderer->renderScreen($screen, 80, 25);

    expect($output)->toContain("\033[97;44m".'Status'."\033[0m");
});

it('maps cga-yellow-lgrey to yellow-on-light-grey ANSI codes', function () {
    $renderer = new AnsiRenderer;
    $screen = [[['│', 'cga-yellow-lgrey']]];
    $output = $renderer->renderScreen($screen, 80, 25);

    expect($output)->toContain("\033[93;47m".'│'."\033[0m");
});

it('maps cga-blue-lgrey to blue-on-light-grey ANSI codes', function () {
    $renderer = new AnsiRenderer;
    $screen = [[['text', 'cga-blue-lgrey']]];
    $output = $renderer->renderScreen($screen, 80, 25);

    expect($output)->toContain("\033[34;47m".'text'."\033[0m");
});

it('maps cga-red-lgrey to red-on-light-grey ANSI codes', function () {
    $renderer = new AnsiRenderer;
    $screen = [[['title', 'cga-red-lgrey']]];
    $output = $renderer->renderScreen($screen, 80, 25);

    expect($output)->toContain("\033[31;47m".'title'."\033[0m");
});

it('maps cga-dgrey-lgrey to dark-grey-on-light-grey ANSI codes', function () {
    $renderer = new AnsiRenderer;
    $screen = [[['kludge', 'cga-dgrey-lgrey']]];
    $output = $renderer->renderScreen($screen, 80, 25);

    expect($output)->toContain("\033[90;47m".'kludge'."\033[0m");
});

it('maps cga-lblue-lgrey to light-blue-on-light-grey ANSI codes', function () {
    $renderer = new AnsiRenderer;
    $screen = [[['tear', 'cga-lblue-lgrey']]];
    $output = $renderer->renderScreen($screen, 80, 25);

    expect($output)->toContain("\033[94;47m".'tear'."\033[0m");
});

it('maps cga-yellow-blue to yellow-on-blue ANSI codes', function () {
    $renderer = new AnsiRenderer;
    $screen = [[['header', 'cga-yellow-blue']]];
    $output = $renderer->renderScreen($screen, 80, 25);

    expect($output)->toContain("\033[93;44m".'header'."\033[0m");
});

it('renders multiple segments in sequence', function () {
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

it('renders multiple rows separated by newlines', function () {
    $renderer = new AnsiRenderer;
    $screen = [
        [['row one', 'cga-black-lgrey']],
        [['row two', 'cga-black-lgrey']],
    ];
    $output = $renderer->renderScreen($screen, 80, 25);

    expect($output)
        ->toContain('row one')
        ->toContain('row two')
        ->toContain("\n");
});

it('falls back to default reset for unknown CGA class', function () {
    $renderer = new AnsiRenderer;
    $screen = [[['unknown', 'cga-unknown']]];
    $output = $renderer->renderScreen($screen, 80, 25);

    expect($output)->toContain('unknown'."\033[0m");
});
