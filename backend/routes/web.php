<?php

use App\Http\Controllers\Admin\ProductController;
use App\Http\Controllers\Auth\AdminAuthController;
use App\Http\Controllers\Auth\CustomerAuthController;
use App\Http\Controllers\Auth\GoogleAuthController;
use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\FavoriteController;
use App\Http\Controllers\ShopController;
use Illuminate\Support\Facades\Route;

// Home page
Route::get('/', function () {
    return view('home');
})->name('home');

// Shop page
Route::get('/shop', [ShopController::class, 'index'])->name('shop');
// One category per page (phones, earphones, powerbanks, laptops — see config/shop.php)
Route::get('/shop/{category}', [ShopController::class, 'category'])
    ->whereIn('category', array_keys(config('shop.categories')))
    ->name('shop.category');

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

Route::get('/admin/login', [AdminAuthController::class, 'showLoginForm'])->name('admin-login');
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
});
