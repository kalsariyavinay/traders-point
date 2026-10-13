<?php

use App\Http\Controllers\Admin\CategoryController as AdminCategoryController;
use App\Http\Controllers\Admin\CouponController as AdminCouponController;
use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\OrderController as AdminOrderController;
use App\Http\Controllers\Admin\VideoController as AdminVideoController;
use App\Http\Controllers\Api\RazorpayController;
use App\Http\Controllers\Api\RazorpayWebhookController;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\CertificateController;
use App\Http\Controllers\CertificateVerificationController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\LandingController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PublicVideoController;
use App\Http\Controllers\VideoProgressController;
use App\Http\Controllers\VideoStreamController;
use Illuminate\Support\Facades\Route;

Route::get('/', LandingController::class)->name('home');

Route::get('/videos/{video}', [PublicVideoController::class, 'show'])->name('videos.show');

// Anyone can check a certificate reference. Throttled: this is the one endpoint
// that confirms whether a reference exists.
Route::get('/verify/{reference}', CertificateVerificationController::class)
    ->middleware('throttle:verification')
    ->name('certificates.verify');
// Uploaded lessons are served only through a signed, viewer-bound, expiring
// address -- see App\Support\StreamToken. The old static /videos/{video}/stream
// path is gone on purpose: it was guessable and shareable.
Route::get('/stream/{video}/{token}', VideoStreamController::class)->name('videos.stream');

// Lets an admin form recover from a 419 in place instead of telling the admin
// to "try again" with the same expired token, which can only 419 again.
Route::get('/csrf-token', fn () => response()->json(['token' => csrf_token()]))->name('csrf-token');
// Razorpay's server-to-server callback. No session, no CSRF -- the webhook
// signature is the authentication. Excluded from CSRF in bootstrap/app.php.
Route::post('/razorpay/webhook', RazorpayWebhookController::class)->name('razorpay.webhook');

Route::middleware('auth')->group(function () {
    // Checkout: the browser asks for an order, Razorpay's modal reports back here.
    Route::post('/videos/{video}/purchase', [RazorpayController::class, 'store'])
        ->middleware('throttle:checkout')
        ->name('razorpay.order');
    Route::post('/payment-success', [CheckoutController::class, 'success'])->name('payment.success');
    Route::post('/payment-failed', [CheckoutController::class, 'failed'])->name('payment.failed');

    // Playback position beacon, called every few seconds and on page unload.
    Route::post('/videos/{video}/progress', VideoProgressController::class)
        ->middleware('throttle:progress')
        ->name('videos.progress');

    // Certificate for a category whose published lessons are all completed.
    Route::get('/certificates/{category}', CertificateController::class)->name('certificates.download');

});

Route::get('/dashboard', DashboardController::class)
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

Route::middleware(['auth', 'admin'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        Route::get('/', AdminDashboardController::class)->name('dashboard');
        Route::resource('categories', AdminCategoryController::class)->except('show');
        Route::resource('videos', AdminVideoController::class)->except('show');
        Route::resource('coupons', AdminCouponController::class)->except('show');
        Route::patch('coupons/{coupon}/toggle', [AdminCouponController::class, 'toggle'])->name('coupons.toggle');
        Route::get('orders', [AdminOrderController::class, 'index'])->name('orders.index');
    });

require __DIR__.'/auth.php';
