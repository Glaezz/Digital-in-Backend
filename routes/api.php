<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BalanceController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\TransactionController;
use App\Http\Controllers\UserController;
use App\Http\Middleware\BearerMiddleware;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');




Route::prefix('/v1')->group(function () {
    route::group([
        "middleware" => ["Bearer", "Admin"]
    ], function () {
        Route::prefix('/admin')->group(function () {
            Route::prefix('/product')->group(function () {
                Route::get('/', [ProductController::class, "index"]);
                Route::post('/', [ProductController::class, "store"]);
                Route::get('/{id}', [ProductController::class, "show"]);
                Route::put('/{id}', [ProductController::class, "update"]);
                Route::delete('/{id}', [ProductController::class, "destroy"]);
                Route::patch('/fetch', [ProductController::class, "fetchProduct"]);
            });
            Route::prefix('/category')->group(function () {
                Route::get('/', [CategoryController::class, "index"]);
                Route::get('/{id}', [CategoryController::class, "show"]);
                Route::put('/{id}', [CategoryController::class, "update"]);
            });
            Route::prefix('/user')->group(function () {
                Route::get('/', [AdminController::class, "index"]);
                Route::get('/{id}', [AdminController::class, "show"]);
                Route::put('/{id}', [AdminController::class, "update"]);
                Route::delete('/{id}', [AdminController::class, "destroy"]);
            });
            Route::prefix('/transaction')->group(function () {
                Route::get('/', [TransactionController::class, "index"]);
                Route::get('/{id}', [TransactionController::class, "show"]);
            });
            Route::prefix('/balance')->group(function () {
                Route::prefix('/transaction')->group(function () {
                    Route::get('/', [BalanceController::class, "index"]);
                    Route::get('/{id}', [BalanceController::class, "show"]);
                });
            });
            Route::prefix("statistic")->group(function () {
                Route::get('/', [AdminController::class, "statistic"]);
            });
        });
    });
    Route::middleware(["Bearer"])->group(function () {
        Route::prefix('/balance')->group(function () {
            Route::get('/', [BalanceController::class, "userBalanceHistory"]);
            Route::prefix('/transaction')->group(function () {
                Route::get('/payment', [PaymentController::class, "index"]);
                // Route::get('/', [BalanceController::class, "userIndex"]);
                Route::get('/{id}', [BalanceController::class, "userShow"]);
                Route::post('/', [BalanceController::class, "userCharge"]);
                Route::post('/{id}/cancel', [BalanceController::class, "userCancel"]);
                Route::post('/callback', [BalanceController::class, "midtransCallback"])->withoutMiddleware(BearerMiddleware::class);
            });
        });
        Route::prefix('/transaction')->group(function () {
            Route::get('/', [TransactionController::class, "userIndex"]);
            Route::post('/', [TransactionController::class, "createTransaction"]);
            Route::get('/callback', [TransactionController::class, "okeconnectCallback"])->withoutMiddleware(BearerMiddleware::class);
            Route::get('/{id}', [TransactionController::class, "userShow"]);
        });
        Route::prefix('/user')->group(function () {
            Route::get('/', [UserController::class, "index"]);
            Route::put('/', [UserController::class, "update"]);
        });
    });

    Route::prefix('/auth')->group(function () {
        Route::post('/signup', [AuthController::class, "signup"]);
        Route::post('/signin', [AuthController::class, "signin"]);
        Route::middleware(BearerMiddleware::class)->group(function () {
            Route::post('/signout', [AuthController::class, "signout"]);
        });
        Route::post('/forgot', [AuthController::class, "forgot"]);
        Route::get('/forgot/{enc}', [AuthController::class, "verifyForgot"]);
        Route::put("forgot/{enc}", [AuthController::class, "resetPassword"]);
        Route::get("/verify/{enc}", [AuthController::class, "verifyEmail"]);
    });
    Route::prefix('/payment')->group(function () {

        Route::get('/', [PaymentController::class, "index"]);
        Route::get('/{id}', [PaymentController::class, "show"]);
    });
    Route::prefix('/product')->group(function () {
        Route::get('/', [ProductController::class, "productShow"]);
    });
});
