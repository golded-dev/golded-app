<?php

use Illuminate\Support\Facades\Route;

Route::livewire('/', 'pages::golded-shell')->name('golded');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::view('dashboard', 'dashboard')->name('dashboard');
});

require __DIR__.'/settings.php';
