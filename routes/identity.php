<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Interfaces\Http\Identity\Controller\JwksController;
use Interfaces\Http\Identity\Controller\LoginController;
use Interfaces\Http\Identity\Controller\LogoutController;
use Interfaces\Http\Identity\Controller\MeController;
use Interfaces\Http\Identity\Controller\RefreshTokenController;
use Interfaces\Http\Identity\Controller\RegisterController;
use Interfaces\Http\Identity\IdentityHttpServiceProvider;

Route::get('/.well-known/jwks.json', JwksController::class);

Route::prefix('api/v1/auth')
    ->group(static function (): void {
        Route::post('register', RegisterController::class);
        Route::post('login', LoginController::class)
            ->middleware('throttle:' . IdentityHttpServiceProvider::LOGIN_RATE_LIMIT);
        Route::post('refresh', RefreshTokenController::class)
            ->middleware('throttle:' . IdentityHttpServiceProvider::REFRESH_RATE_LIMIT);

        Route::middleware('auth')
            ->group(static function (): void {
                Route::post('logout', LogoutController::class);
                Route::get('me', MeController::class);
            });
    });
