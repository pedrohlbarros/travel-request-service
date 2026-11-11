<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Interfaces\Http\Notification\Controller\BroadcastPlaygroundController;
use Interfaces\Http\Notification\Controller\ListNotificationsController;
use Interfaces\Http\Notification\Controller\MarkNotificationReadController;
use Interfaces\Http\Notification\NotificationHttpServiceProvider;

$notification = '{' . NotificationHttpServiceProvider::ID_PARAMETER . '}';

Route::prefix('api/v1/notifications')
    ->middleware('auth')
    ->whereUuid(NotificationHttpServiceProvider::ID_PARAMETER)
    ->group(static function () use ($notification): void {
        Route::get('', ListNotificationsController::class);
        Route::post($notification . '/read', MarkNotificationReadController::class);
    });

Route::get('docs/notifications', BroadcastPlaygroundController::class);
