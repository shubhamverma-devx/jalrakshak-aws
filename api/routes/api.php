<?php

use App\Http\Controllers\Api\AlertController;
use App\Http\Controllers\Api\OfficerAuthController;
use App\Http\Controllers\Api\SubscriberController;
use App\Http\Controllers\Api\ZoneController;
use App\Services\MapStorage;
use App\Services\SnsService;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| JalRakshak API
|--------------------------------------------------------------------------
| Public routes feed the citizen page. The officer group needs the bearer
| token handed out by POST /api/officer/login.
*/

Route::get('/health', function (SnsService $sns, MapStorage $maps) {
    return [
        'ok' => true,
        'app' => 'JalRakshak AWS',
        'time' => now()->toIso8601String(),
        'aws' => [
            'sns_enabled' => $sns->enabled(),
            'maps_disk' => $maps->disk(),
            'region' => config('jalrakshak.aws.region'),
            'bucket' => config('jalrakshak.aws.bucket'),
        ],
    ];
});

// Citizen side, no auth.
Route::get('/zones', [ZoneController::class, 'index']);
Route::get('/zones/{zone}', [ZoneController::class, 'show']);
Route::get('/alerts', [AlertController::class, 'index']);
Route::post('/subscribe', [SubscriberController::class, 'store']);

// Officer sign in.
Route::post('/officer/login', [OfficerAuthController::class, 'login']);

// Officer side, bearer token required.
Route::middleware('officer')->prefix('officer')->group(function () {
    Route::post('/zones/{zone}/readings', [ZoneController::class, 'storeReading']);
    Route::post('/zones/{zone}/map', [ZoneController::class, 'uploadMap']);
    Route::post('/zones/{zone}/alert', [AlertController::class, 'trigger']);
});
