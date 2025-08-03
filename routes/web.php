<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ApprovalRequestController; // IMPORTANT: Changed to ApprovalRequestController
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::get('/', function () {
    return view('welcome');
});

Route::get('/dashboard', DashboardController::class)
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::middleware('auth')->group(function () {
    // Profile routes
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // Request management routes (IMPORTANT: Updated Controller Class and explicit show route)
    // Exclude 'show', 'approve', 'downloadAttachment', 'edit', 'update', 'destroy' from resource so we can define them with $id
    Route::resource('requests', ApprovalRequestController::class)->except(['show', 'approve', 'downloadAttachment', 'edit', 'update', 'destroy']);

    // Explicit routes for methods that now accept an ID instead of a model
    Route::get('/requests/{id}', [ApprovalRequestController::class, 'show'])->name('requests.show'); // CHANGED
    Route::post('/requests/{id}/approve', [ApprovalRequestController::class, 'approve'])->name('requests.approve'); // CHANGED
    Route::get('/requests/{id}/download-attachment', [ApprovalRequestController::class, 'downloadAttachment'])->name('requests.download-attachment'); // CHANGED
    Route::get('/requests/{id}/edit', [ApprovalRequestController::class, 'edit'])->name('requests.edit'); // CHANGED
    Route::patch('/requests/{id}', [ApprovalRequestController::class, 'update'])->name('requests.update'); // CHANGED
    Route::delete('/requests/{id}', [ApprovalRequestController::class, 'destroy'])->name('requests.destroy'); // CHANGED
});

require __DIR__.'/auth.php';
