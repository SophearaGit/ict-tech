<?php

use App\Http\Controllers\Admin\ProductController;
use App\Http\Controllers\Auth\AdminAuthController;
use App\Http\Controllers\ContactController;
use App\Models\Product;
use Illuminate\Support\Facades\Route;

// Home page
Route::get('/', function () {
    return view('home');
})->name('home');

// Shop page
Route::get('/shop', function () {
    $products = Product::orderBy('id')->get()->map(fn (Product $p) => [
        'id' => $p->id,
        'name' => $p->name,
        'brand' => $p->brand,
        'type' => $p->type,
        'category' => $p->category,
        'price' => (float) $p->price,
        'desc' => $p->description,
        'specs' => $p->specs,
        'image' => $p->image,
        'imageAlt' => $p->image_alt,
        'trending' => (bool) $p->trending,
        'badge' => $p->badge,
    ]);

    return view('shop', compact('products'));
})->name('shop');

// Contact page
Route::get('/contact', function () {
    return view('contact');
})->name('contact');
Route::post('/contact', [ContactController::class, 'store'])->name('contact.submit');

// Account page (login / register / welcome — client-side auth)
Route::get('/account', function () {
    return view('account');
})->name('account');

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
