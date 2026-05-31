<?php

use App\Http\Controllers\Api\RoomController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes - Room Catalog Service
| Standard Integration Contract (IAE-T2)
|--------------------------------------------------------------------------
*/

Route::prefix('v1')->middleware('iae.key')->group(function () {

    // COLLECTION: GET /api/v1/rooms
    Route::get('/rooms', [RoomController::class, 'index']);

    // RESOURCE: GET /api/v1/rooms/{id}
    Route::get('/rooms/{id}', [RoomController::class, 'show']);

    // ACTION: POST /api/v1/rooms
    Route::post('/rooms', [RoomController::class, 'store']);

    // UPDATE STATUS: PUT /api/v1/rooms/{id}/status
    Route::put('/rooms/{id}/status', [RoomController::class, 'updateStatus']);

    // ASSIGN: POST /api/v1/rooms/{id}/assign
    Route::post('/rooms/{id}/assign', [RoomController::class, 'assign']);
});
