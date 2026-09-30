<?php

use Illuminate\Support\Facades\Route;
use LaraSlice\Slices\Auth\Controllers\AuthWebController;

Route::middleware(['web'])->group(function () {
    Route::get('/login', [AuthWebController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthWebController::class, 'login'])->name('login.post');
    Route::post('/logout', [AuthWebController::class, 'logout'])->name('logout');
});
