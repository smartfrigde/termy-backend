<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\UserController;

Route::post('/users', [UserController::class, 'store'])->name('users.store');
Route::post('/login', [UserController::class, 'login'])->name('login');
Route::post('/refresh-token', [UserController::class,'refresh']);
