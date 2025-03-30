<?php

use App\Http\Controllers\API\UserController;
use App\Http\Controllers\API\CsvUploadController;
use App\Http\Controllers\API\ActivityMetricController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

// Public routes
Route::post('register', [UserController::class, 'register']);
Route::post('login', [UserController::class, 'login']);

// Protected routes
Route::middleware('auth:sanctum')->group(function () {
    Route::get('user', [UserController::class, 'user']);
    Route::post('logout', [UserController::class, 'logout']);

    // CSV upload routes
    Route::apiResource('csv-uploads', CsvUploadController::class);

    // Activity metrics routes
    Route::get('activity-metrics', [ActivityMetricController::class, 'index']);
    Route::get('activity-metrics/{id}', [ActivityMetricController::class, 'show']);
    Route::get('csv-uploads/{csvUploadId}/activity-metrics', [ActivityMetricController::class, 'getByCsvUpload']);

    // Predictions routes (to be implemented)
    // Route::resource('predictions', PredictionController::class);
});
