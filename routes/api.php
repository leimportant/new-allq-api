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
use App\Http\Controllers\Api\UploadController;
use App\Http\Controllers\Api\OrdersController;
use App\Http\Controllers\Api\MaterialController;
use App\Http\Controllers\Api\SupplierController;
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
Route::get('/public/image/{application}', [UploadController::class, 'loadImage']);
Route::get('/qrcode/{application}', [OrdersController::class, 'qrcode']);

Route::middleware('auth:sanctum')->get('/user/{application}', function (Request $request) {
    return $request->user();
});

Route::post('/logout/{application}', [AuthController::class, 'logout'])->middleware('auth:sanctum');
Route::post('/refresh', [AuthController::class, 'refresh']);

Route::group(['middleware' => ['auth:sanctum']], function () {
    Route::get('/combo/{application}', [ComboController::class, 'combo']);

    Route::post('/supplier/list/{application}', [SupplierController::class, 'list']);
    Route::post('/supplier/view/{application}', [SupplierController::class, 'view']);
    Route::post('/supplier/store/{application}', [SupplierController::class, 'store']);

    Route::post('/material/list/{application}', [MaterialController::class, 'list']);
    Route::post('/material/view/{application}', [MaterialController::class, 'view']);
    Route::post('/material/store/{application}', [MaterialController::class, 'store']);

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
    Route::post('/notification/mark-as-read/{application}', [NotificationController::class, 'markAsRead']);
    Route::post('/approval/list/{application}', [ApprovalController::class, 'list']);
    Route::post('/approve/{application}', [ApprovalController::class, 'approve']);
    Route::post('/reject/{application}', [ApprovalController::class, 'reject']);

    Route::post('/purchase-order/list/{application}', [PurchaseorderController::class, 'list']);
    Route::post('/purchase-order/view/{application}', [PurchaseorderController::class, 'view']);
    Route::post('/purchase-order/store/{application}', [PurchaseorderController::class, 'store']);

    #upload
    Route::post('/upload/list/{application}', [UploadController::class, 'list']);
    Route::post('/upload/store/{application}', [UploadController::class, 'store']);
    Route::post('/upload/delete/{application}', [UploadController::class, 'delete']);
    Route::get('/image/{application}', [UploadController::class, 'loadImage']);

    #Create Order
    Route::post('/order-model/list/{application}', [OrdersController::class, 'list']);
    Route::post('/order-model/view/{application}', [OrdersController::class, 'view']);
    Route::post('/order-model/store/{application}', [OrdersController::class, 'store']);
    Route::post('/order-model/finish/{application}', [OrdersController::class, 'finish']);

    Route::post('/good-issue-material/view/{application}', [OrdersController::class, 'viewMaterial']);
    Route::post('/good-issue-material/store/{application}', [OrdersController::class, 'storeMaterial']);

    Route::post('/order-assignjob/view/{application}', [OrdersController::class, 'viewAssignjob']);
    Route::post('/order-assignjob/store/{application}', [OrdersController::class, 'storeAssignjob']);
    
    

});