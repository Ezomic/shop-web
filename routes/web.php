<?php

use App\Http\Controllers\Admin\CouponController;
use App\Http\Controllers\Admin\LoginController as AdminLoginController;
use App\Http\Controllers\Admin\OrderController as AdminOrderController;
use App\Http\Controllers\Admin\ProductController as AdminProductController;
use App\Http\Controllers\Admin\SettingsController;
use App\Http\Controllers\Auth\ForgotPasswordController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Auth\ResetPasswordController;
use App\Http\Controllers\CartController;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\CustomerOrderController;
use App\Http\Controllers\DownloadController;
use App\Http\Controllers\MollieWebhookController;
use App\Http\Controllers\ShopController;
use App\Http\Controllers\StripeWebhookController;
use Illuminate\Support\Facades\Route;

// Webhooks (excluded from CSRF in bootstrap/app.php)
Route::post('/webhooks/stripe', [StripeWebhookController::class, 'handle'])->name('webhooks.stripe');
Route::post('/webhooks/mollie', [MollieWebhookController::class, 'handle'])->name('webhooks.mollie');

// Locale switch
Route::post('/locale/{locale}', function (string $locale) {
    if (in_array($locale, ['en', 'nl'], strict: true)) {
        session(['locale' => $locale]);
    }

    return back();
})->name('locale.switch');

// Public storefront
Route::get('/', [ShopController::class, 'index'])->name('shop.index');
Route::get('/products/{product:slug}', [ShopController::class, 'show'])->name('shop.show');

// Cart
Route::post('/cart/add', [CartController::class, 'add'])->name('cart.add');
Route::post('/cart/remove', [CartController::class, 'remove'])->name('cart.remove');
Route::post('/cart/apply-coupon', [CartController::class, 'applyCoupon'])->name('cart.coupon');

// Downloads (signed URL, no customer auth required — link is the credential)
Route::get('/downloads/{token}', [DownloadController::class, 'get'])->name('downloads.get');

// Guest auth
Route::middleware('guest:customer')->group(function (): void {
    Route::get('/register', [RegisterController::class, 'create'])->name('register');
    Route::post('/register', [RegisterController::class, 'store']);
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store']);
    Route::get('/forgot-password', [ForgotPasswordController::class, 'create'])->name('password.request');
    Route::post('/forgot-password', [ForgotPasswordController::class, 'store'])->name('password.email');
    Route::get('/reset-password/{token}', [ResetPasswordController::class, 'create'])->name('password.reset');
    Route::post('/reset-password', [ResetPasswordController::class, 'store'])->name('password.update');
});

Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');

// Customer area
Route::middleware('auth:customer')->group(function (): void {
    Route::get('/checkout', [CheckoutController::class, 'index'])->name('checkout.index');
    Route::post('/checkout', [CheckoutController::class, 'store'])->name('checkout.store');
    Route::get('/checkout/success', [CheckoutController::class, 'success'])->name('checkout.success');
    Route::get('/checkout/cancel', [CheckoutController::class, 'cancel'])->name('checkout.cancel');

    Route::get('/orders', [CustomerOrderController::class, 'index'])->name('orders.index');
    Route::get('/orders/{order}', [CustomerOrderController::class, 'show'])->name('orders.show');
});

// Mollie redirect (no auth — Mollie redirects here after payment)
Route::get('/mollie/redirect', function () {
    return redirect()->route('checkout.success');
})->name('mollie.redirect');

// Admin auth
Route::middleware('guest')->prefix('admin')->name('admin.')->group(function (): void {
    Route::get('login', [AdminLoginController::class, 'create'])->name('login');
    Route::post('login', [AdminLoginController::class, 'store']);
});

Route::post('admin/logout', [AdminLoginController::class, 'destroy'])->name('admin.logout');

// Admin
Route::middleware(['auth', 'admin'])->prefix('admin')->name('admin.')->group(function (): void {
    Route::resource('products', AdminProductController::class)->except('show');
    Route::post('products/reorder', [AdminProductController::class, 'reorder'])->name('products.reorder');

    Route::resource('orders', AdminOrderController::class)->only(['index', 'show']);
    Route::post('orders/{order}/refund', [AdminOrderController::class, 'refund'])->name('orders.refund');

    Route::resource('coupons', CouponController::class)->except('show');

    Route::get('settings', [SettingsController::class, 'edit'])->name('settings.edit');
    Route::put('settings', [SettingsController::class, 'update'])->name('settings.update');
});
