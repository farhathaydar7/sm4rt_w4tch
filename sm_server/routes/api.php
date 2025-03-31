<?php

use App\Http\Controllers\API\UserController;
use App\Http\Controllers\API\CsvUploadController;
use App\Http\Controllers\API\ActivityMetricController;
use App\Http\Controllers\API\AuthController;
use App\Http\Controllers\API\SocialAuthController;
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

// Auth routes (JWT)
Route::group(['prefix' => 'auth'], function () {
    Route::post('register', [AuthController::class, 'register']);
    Route::post('login', [AuthController::class, 'login']);
    Route::post('logout', [AuthController::class, 'logout'])->middleware('auth:api');
    Route::post('refresh', [AuthController::class, 'refresh'])->middleware('auth:api');
    Route::get('me', [AuthController::class, 'me'])->middleware('auth:api');
});

// Social authentication routes
Route::group(['prefix' => 'auth/social'], function () {
    Route::get('{provider}', [SocialAuthController::class, 'redirectToProvider']);
    Route::get('{provider}/callback', [SocialAuthController::class, 'handleProviderCallback']);
    Route::post('{provider}/callback', [SocialAuthController::class, 'handleProviderCallback']);
});

// Legacy routes - will be removed in the future
Route::post('register', [UserController::class, 'register']);
Route::post('login', [UserController::class, 'login']);

// Protected routes
Route::middleware('auth:api')->group(function () {
    Route::get('user', [UserController::class, 'user']);
    Route::post('user/logout', [UserController::class, 'logout']);

    // CSV upload routes
    Route::apiResource('csv-uploads', CsvUploadController::class);
    Route::get('csv-uploads/{id}/status', [CsvUploadController::class, 'checkStatus']);

    // Activity metrics routes
    Route::get('activity-metrics', [ActivityMetricController::class, 'index']);
    Route::get('activity-metrics/{id}', [ActivityMetricController::class, 'show']);
    Route::get('csv-uploads/{csvUploadId}/activity-metrics', [ActivityMetricController::class, 'getByCsvUpload']);

    // Predictions routes (to be implemented)
    // Route::resource('predictions', PredictionController::class);
});

// Activity Routes
Route::prefix('activity')->middleware('auth:sanctum')->group(function () {
    Route::get('/all', [App\Http\Controllers\Api\ActivityController::class, 'getAll']);
    Route::get('/date/{date}', [App\Http\Controllers\Api\ActivityController::class, 'getByDate']);
    Route::get('/week', [App\Http\Controllers\Api\ActivityController::class, 'getWeeklySummary']);
    Route::get('/stats', [App\Http\Controllers\Api\ActivityController::class, 'getStats']);
});

