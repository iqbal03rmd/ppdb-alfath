<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('welcome');
})->name('home');

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
require __DIR__.'/wali-murid.php';
require __DIR__.'/staf-ppdb.php';
require __DIR__.'/kepala-sekolah.php';
require __DIR__.'/super-admin.php';
