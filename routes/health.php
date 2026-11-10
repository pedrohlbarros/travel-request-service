<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Interfaces\Http\Shared\Controller\LiveController;
use Interfaces\Http\Shared\Controller\ReadyController;

Route::get(
    '/health/live',
    LiveController::class,
)
    ->name('health.live');
Route::get(
    '/health/ready',
    ReadyController::class,
)
    ->name('health.ready');
