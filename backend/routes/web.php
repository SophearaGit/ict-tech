<?php

use App\Http\Controllers\Admin\AdminAccountController;
use App\Http\Controllers\Admin\AnalyticsController;
use App\Http\Controllers\Admin\BrandController;
use App\Http\Controllers\Admin\CategoryController;
use App\Http\Controllers\Admin\OrderController;
use App\Http\Controllers\Admin\ProductController;
use App\Http\Controllers\Admin\SalesReportController;
use App\Http\Controllers\Auth\AdminAuthController;
use App\Http\Controllers\Auth\CustomerAuthController;
use App\Http\Controllers\Auth\GoogleAuthController;
use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\CustomerOrderController;
use App\Http\Controllers\FavoriteController;
use App\Http\Controllers\ShopController;
use Illuminate\Support\Facades\Route;

// Home page
Route::get('/', function () {
    return view('home');
})->name('home');

// Shop page
Route::get('/shop', [ShopController::class, 'index'])->name('shop');
// One category per page (/shop/phones, ...). The code is looked up in the categories table by the
// controller, which answers 404 for an unknown or switched-off code.
Route::get('/shop/{category}', [ShopController::class, 'category'])->name('shop.category');

// Contact page
Route::get('/contact', function () {
    return view('contact');
})->name('contact');
Route::post('/contact', [ContactController::class, 'store'])->name('contact.submit');

// Privacy policy page (also referenced from Google OAuth consent screen config)
Route::get('/privacy', function () {
    return view('privacy');
})->name('privacy');

// Account page (login / register / welcome — session-backed customer auth)
Route::get('/account', function () {
    return view('account');
})->name('account');

Route::post('/register', [CustomerAuthController::class, 'register'])->name('register');
Route::post('/login', [CustomerAuthController::class, 'login'])->name('login');
Route::post('/logout', [CustomerAuthController::class, 'logout'])->name('logout');

Route::post('/forgot-password', [PasswordResetController::class, 'sendCode'])->name('password.email');
Route::post('/reset-password', [PasswordResetController::class, 'reset'])->name('password.update');

Route::get('/auth/google/redirect', [GoogleAuthController::class, 'redirect'])->name('google.redirect');
Route::get('/auth/google/callback', [GoogleAuthController::class, 'callback'])->name('google.callback');

Route::get('/favorites', [FavoriteController::class, 'index'])->name('favorites');
Route::post('/favorites/toggle', [FavoriteController::class, 'toggle'])->name('favorites.toggle');

Route::post('/checkout', [CheckoutController::class, 'store'])->middleware('throttle:10,1')->name('checkout');
Route::get('/orders', [CustomerOrderController::class, 'index'])->name('orders');

Route::get('/admin/login',[AdminAuthController::class, 'showLoginForm'])->name('admin-login');
Route::post('/admin/login', [AdminAuthController::class, 'login'])->name('admin-login.attempt');
Route::post('/admin/logout', [AdminAuthController::class, 'logout'])->name('admin-logout');

Route::middleware('admin')->group(function () {
    Route::get('/admin/view', function () {
        return view('admin-view');
    })->name('admin-view');

    Route::get('/admin/messages', [ContactController::class, 'index'])->name('admin-messages');

    Route::get('/admin/products', [ProductController::class, 'index'])->name('admin-products.index');
    Route::post('/admin/products', [ProductController::class, 'store'])->name('admin-products.store');
    Route::put('/admin/products/{product}', [ProductController::class, 'update'])->name('admin-products.update');
    Route::delete('/admin/products/{product}', [ProductController::class, 'destroy'])->name('admin-products.destroy');

    Route::get('/admin/brands', [BrandController::class, 'index'])->name('admin-brands.index');
    Route::post('/admin/brands', [BrandController::class, 'store'])->name('admin-brands.store');
    Route::put('/admin/brands/{brand}', [BrandController::class, 'update'])->name('admin-brands.update');
    Route::delete('/admin/brands/{brand}', [BrandController::class, 'destroy'])->name('admin-brands.destroy');
    Route::post('/admin/brands/{brand}/logo', [BrandController::class, 'storeLogo'])->name('admin-brands.logo.store');
    Route::delete('/admin/brands/{brand}/logo', [BrandController::class, 'destroyLogo'])->name('admin-brands.logo.destroy');

    Route::get('/admin/categories', [CategoryController::class, 'index'])->name('admin-categories.index');
    Route::post('/admin/categories', [CategoryController::class, 'store'])->name('admin-categories.store');
    Route::put('/admin/categories/{category}', [CategoryController::class, 'update'])->name('admin-categories.update');
    Route::delete('/admin/categories/{category}', [CategoryController::class, 'destroy'])->name('admin-categories.destroy');

    Route::get('/admin/orders', [OrderController::class, 'index'])->name('admin-orders.index');
    Route::put('/admin/orders/{order}', [OrderController::class, 'update'])->name('admin-orders.update');

    Route::get('/admin/analytics/summary', [AnalyticsController::class, 'summary'])->name('admin-analytics.summary');

    Route::get('/admin/reports/sales', [SalesReportController::class, 'show'])->name('admin-reports.sales');
    Route::get('/admin/reports/sales/export', [SalesReportController::class, 'export'])->name('admin-reports.sales.export');

    // Owner only: staff get 403 here even when they type the address. There is deliberately no delete route.
    Route::middleware('owner')->group(function () {
        Route::get('/admin/admins', [AdminAccountController::class, 'index'])->name('admin-admins.index');
        Route::post('/admin/admins', [AdminAccountController::class, 'store'])->name('admin-admins.store');
        Route::put('/admin/admins/{admin}', [AdminAccountController::class, 'update'])->name('admin-admins.update');
    });
});
