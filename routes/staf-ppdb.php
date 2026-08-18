<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::middleware(['auth', 'role:staf_ppdb'])
    ->prefix('staf-ppdb')
    ->name('staf-ppdb.')
    ->group(function () {
        Route::get('/dashboard', function () {
            return Inertia::render('staf-ppdb/dashboard');
        })->name('dashboard');
    });