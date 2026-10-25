<?php

use App\Http\Controllers\Api\Admin\OrderController as AdminOrderController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\StripeWebhookController;
use Illuminate\Support\Facades\Route;

/*
| Rutas de la API (prefijo /api). Todas responden JSON.
| Públicas: catálogo y webhook de Stripe (protegido por firma).
| Autenticadas (JWT, guard "api"): perfil, órdenes y pagos.
| Administrativas: middleware "admin" además del JWT.
*/

Route::prefix('auth')->group(function () {
    Route::middleware('throttle:auth')->group(function () {
        Route::post('register', [AuthController::class, 'register'])->name('auth.register');
        Route::post('login', [AuthController::class, 'login'])->name('auth.login');
    });

    Route::middleware('auth:api')->group(function () {
        Route::get('me', [AuthController::class, 'me'])->name('auth.me');
        Route::post('logout', [AuthController::class, 'logout'])->name('auth.logout');
        Route::post('refresh', [AuthController::class, 'refresh'])->middleware('throttle:auth')->name('auth.refresh');
    });
});

Route::get('products', [ProductController::class, 'index'])->name('products.index');
Route::get('products/{product}', [ProductController::class, 'show'])->whereNumber('product')->name('products.show');

Route::middleware('auth:api')->group(function () {
    Route::middleware('admin')->group(function () {
        Route::post('products', [ProductController::class, 'store'])->name('products.store');
        Route::match(['put', 'patch'], 'products/{product}', [ProductController::class, 'update'])
            ->whereNumber('product')->name('products.update');
        Route::delete('products/{product}', [ProductController::class, 'destroy'])
            ->whereNumber('product')->name('products.destroy');

        Route::get('admin/orders', [AdminOrderController::class, 'index'])->name('admin.orders.index');
        Route::patch('admin/orders/{order}/status', [AdminOrderController::class, 'updateStatus'])
            ->whereNumber('order')->name('admin.orders.status');
    });

    Route::get('orders', [OrderController::class, 'index'])->name('orders.index');
    Route::post('orders', [OrderController::class, 'store'])->middleware('throttle:orders')->name('orders.store');
    Route::get('orders/{order}', [OrderController::class, 'show'])->whereNumber('order')->name('orders.show');
    Route::post('orders/{order}/cancel', [OrderController::class, 'cancel'])->whereNumber('order')->name('orders.cancel');

    Route::get('orders/{order}/payments', [PaymentController::class, 'index'])->whereNumber('order')->name('payments.index');
    Route::post('orders/{order}/payments', [PaymentController::class, 'store'])
        ->whereNumber('order')->middleware('throttle:payments')->name('payments.store');
});

Route::post('webhooks/stripe', StripeWebhookController::class)
    ->middleware('throttle:webhooks')
    ->withoutMiddleware('throttle:api')
    ->name('webhooks.stripe');
