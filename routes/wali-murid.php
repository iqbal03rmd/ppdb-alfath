<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::middleware(['auth', 'role:wali_murid'])
    ->prefix('wali-murid')
    ->name('wali-murid.')
    ->group(function () {
        Route::get('/dashboard', function () {
            return Inertia::render('wali-murid/dashboard');
        })->name('dashboard');
    });