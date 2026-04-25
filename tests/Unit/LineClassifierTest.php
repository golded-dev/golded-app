<?php

use App\Domain\LineClassifier;
use App\Domain\LineType;

it('classifies kludge lines', function (): void {
    $classifier = new LineClassifier;

    expect($classifier->classify("\x01MSGID: 1:234/567 deadbeef"))->toBe(LineType::Kludge);
    expect($classifier->classify("\x01PID: GoldED 3.0.1"))->toBe(LineType::Kludge);
});

it('classifies tearlines', function (): void {
    $classifier = new LineClassifier;

    expect($classifier->classify('--- GoldED 3.0.1'))->toBe(LineType::Tearline);
    expect($classifier->classify('--- '))->toBe(LineType::Tearline);
});

it('classifies origin lines', function (): void {
    $classifier = new LineClassifier;

    expect($classifier->classify(' * Origin: Goldware BBS (2:236/77)'))->toBe(LineType::Origin);
});

it('classifies odd-depth quotes as Quote1', function (): void {
    $classifier = new LineClassifier;

    expect($classifier->classify('> some quoted text'))->toBe(LineType::Quote1);
    expect($classifier->classify('| some quoted text'))->toBe(LineType::Quote1);
    expect($classifier->classify('> > > triple quoted'))->toBe(LineType::Quote1);
});

it('classifies even-depth quotes as Quote2', function (): void {
    $classifier = new LineClassifier;

    expect($classifier->classify('> > double quoted'))->toBe(LineType::Quote2);
    expect($classifier->classify('> > > > quad quoted'))->toBe(LineType::Quote2);
});

it('classifies normal lines', function (): void {
    $classifier = new LineClassifier;

    expect($classifier->classify('Hello, this is a normal line.'))->toBe(LineType::Normal);
    expect($classifier->classify(''))->toBe(LineType::Normal);
    expect($classifier->classify('  some indented text'))->toBe(LineType::Normal);
});
