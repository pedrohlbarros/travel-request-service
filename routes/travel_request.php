<?php

declare(strict_types=1);

use Domain\TravelRequest\Entity\TravelRequest;
use Illuminate\Support\Facades\Route;
use Interfaces\Http\Shared\Middleware\RequireIfMatch;
use Interfaces\Http\TravelRequest\Controller\ApproveTravelRequestController;
use Interfaces\Http\TravelRequest\Controller\CancelTravelRequestController;
use Interfaces\Http\TravelRequest\Controller\ChangeTravelRequestDetailsController;
use Interfaces\Http\TravelRequest\Controller\CreateTravelRequestController;
use Interfaces\Http\TravelRequest\Controller\ListTravelRequestsController;
use Interfaces\Http\TravelRequest\Controller\ShowTravelRequestController;
use Interfaces\Http\TravelRequest\Policy\TravelRequestPolicy;
use Interfaces\Http\TravelRequest\TravelRequestHttpServiceProvider;

$travelRequest = '{' . TravelRequestHttpServiceProvider::ID_PARAMETER . '}';

Route::prefix('api/v1/travel-requests')
    ->middleware('auth')
    ->whereUuid(TravelRequestHttpServiceProvider::ID_PARAMETER)
    ->group(static function () use ($travelRequest): void {
        Route::post('', CreateTravelRequestController::class);
        Route::get('', ListTravelRequestsController::class);
        Route::get($travelRequest, ShowTravelRequestController::class)
            ->name(TravelRequestHttpServiceProvider::SHOW_ROUTE);
        Route::patch($travelRequest, ChangeTravelRequestDetailsController::class)
            ->middleware(RequireIfMatch::class);
        // The role check comes first, so a regular user gets 403 before any precondition or lookup.
        Route::post($travelRequest . '/approve', ApproveTravelRequestController::class)
            ->middleware(['can:' . TravelRequestPolicy::APPROVE . ',' . TravelRequest::class, RequireIfMatch::class]);
        Route::post($travelRequest . '/cancel', CancelTravelRequestController::class)
            ->middleware(['can:' . TravelRequestPolicy::CANCEL . ',' . TravelRequest::class, RequireIfMatch::class]);
    });
