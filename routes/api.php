<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\KasbonController;

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

Route::post('/register/{application}', [AuthController::class, 'createUser']);
Route::post('/login/{application}', [AuthController::class, 'loginUser']);

Route::middleware('auth:sanctum')->get('/user{application}', function (Request $request) {
    return $request->user();
});

Route::post('/logout/{application}', [AuthController::class, 'logout'])->middleware('auth:sanctum');
Route::post('/refresh', [AuthController::class, 'refresh']);

Route::group(['middleware' => ['auth:sanctum']], function () {
    Route::post('/kasbon/list/{application}', [KasbonController::class, 'list']);
    Route::post('/kasbon/view/{application}', [KasbonController::class, 'view']);
    Route::post('/kasbon/store/{application}', [KasbonController::class, 'store']);

    // Profile
    Route::post('/profile/update/{application}', [AuthController::class, 'updateUser']);
    Route::post('/profile/delete/{application}', [AuthController::class, 'deleteUser']);
    Route::post('/profile/update-password/{application}', [AuthController::class, 'updatePassword']);
});