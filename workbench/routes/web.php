<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

// Redirect root to the dashboard
Route::get('/', fn () => redirect('/mqtt-broadcast'));
