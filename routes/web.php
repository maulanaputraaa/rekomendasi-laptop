<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use App\Http\Controllers\BrandController;
use App\Http\Controllers\LaptopController;
use App\Http\Controllers\ReviewController;
use App\Http\Controllers\UserClickController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ReviewImportController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\UserController;

Route::get('/', function () {
    return Inertia::render('welcome');
})->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', function () { return Inertia::render('dashboard');})->name('dashboard');
    Route::get('/dashboard', [DashboardController::class, 'index'])->middleware(['auth', 'verified'])->name('dashboard');
    Route::get('/laptops/{id}', [LaptopController::class, 'show'])->name('laptops.show');
    Route::get('/laptops', [LaptopController::class, 'index']);
    Route::post('/laptops', [LaptopController::class, 'store']);
    Route::get('/brands', [BrandController::class, 'index']);
    Route::post('/brands', [BrandController::class, 'store']);
    Route::post('/reviews', [ReviewController::class, 'store']);
    Route::post('/laptop/{id}/click', [UserClickController::class, 'recordClick']);
    Route::get('/laptop/{id}', [LaptopController::class, 'show'])->name('laptop.show');
    Route::get('/search', [SearchController::class, 'search'])->name('search');
});
Route::middleware(['auth', 'verified', 'admin'])->group(function () {
    Route::post('/reviews/import', [ReviewImportController::class, 'import'])->name('reviews.import');
    Route::get('/Admin/UploadData', function () { return Inertia::render('Admin/UploadData');})->name('admin.upload-data');
    Route::get('/Admin/DashboardAdmin', [AdminController::class, 'dashboard'])->name('admin.dashboard');
    Route::delete('/laptops/{laptop}', [LaptopController::class, 'destroy'])->name('laptops.destroy');
    Route::get('/Admin/UsersList', [UserController::class, 'index'])->name('admin.users.index');
    Route::post('/Admin/UsersList', [UserController::class, 'store'])->name('admin.users.store');
    Route::get('/Admin/UsersList/{user}/edit', [UserController::class, 'edit'])->name('admin.users.edit');
    Route::put('/Admin/UsersList/{user}', [UserController::class, 'update'])->name('admin.users.update');
    Route::delete('/Admin/UsersList/{user}', [UserController::class, 'destroy'])->name('admin.users.destroy');
});


require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
