<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\KasbonController;
use App\Http\Controllers\Api\PaymentkasbonController;
use App\Http\Controllers\Api\ComboController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\ApprovalController;
use App\Http\Controllers\Api\PurchaseorderController;
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

Route::middleware('auth:sanctum')->get('/user/{application}', function (Request $request) {
    return $request->user();
});

Route::post('/logout/{application}', [AuthController::class, 'logout'])->middleware('auth:sanctum');
Route::post('/refresh', [AuthController::class, 'refresh']);

Route::group(['middleware' => ['auth:sanctum']], function () {
    Route::get('/combo/{application}', [ComboController::class, 'combo']);

    Route::post('/kasbon/list/{application}', [KasbonController::class, 'list']);
    Route::post('/kasbon/view/{application}', [KasbonController::class, 'view']);
    Route::post('/kasbon/store/{application}', [KasbonController::class, 'store']);
    # Pembayaran Kasbon
    Route::post('/payment-kasbon/view/{application}', [PaymentkasbonController::class, 'view']);
    Route::post('/payment-kasbon/store/{application}', [PaymentkasbonController::class, 'store']);

    // Profile
    Route::post('/profile/update/{application}', [AuthController::class, 'updateUser']);
    Route::post('/profile/validate/{application}', [AuthController::class, 'updateProfile']);
    Route::post('/profile/update-password/{application}', [AuthController::class, 'updatePassword']);

    #notification
    Route::post('/notification/list/{application}', [NotificationController::class, 'list']);
    Route::post('/notification/markAsRead/{application}', [NotificationController::class, 'markAsRead']);
    Route::post('/approval/list/{application}', [ApprovalController::class, 'list']);
    Route::post('/approve/{application}', [ApprovalController::class, 'approve']);
    Route::post('/reject/{application}', [ApprovalController::class, 'reject']);

    Route::post('/purchase-order/list/{application}', [PurchaseorderController::class, 'list']);
    Route::post('/purchase-order/view/{application}', [PurchaseorderController::class, 'view']);
    Route::post('/purchase-order/store/{application}', [PurchaseorderController::class, 'store']);

});