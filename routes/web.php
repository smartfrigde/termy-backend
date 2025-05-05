<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\UserController;
use App\Http\Middleware\AuthenticatedWithToken;
use Illuminate\Auth\Middleware\Authenticate;

Route::post('/users', [UserController::class, 'store'])->name('users.store');
Route::post('/login', [UserController::class, 'login'])->name('login');
Route::post('/refresh-token', [UserController::class,'refresh']);


Route::middleware(AuthenticatedWithToken::class)->group(function () {
    Route::patch('/users/{id}', [UserController::class, 'update'])->name('users.update');
    Route::delete('/users/{id}', [UserController::class, 'destroy'])->name('users.destroy');
});

