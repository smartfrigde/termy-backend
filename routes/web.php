<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\UserController;

Route::get('/user', function () {
    return response()->json([
        'message' => 'Welcome to the API',
    ]);
});

Route::post('/users', [UserController::class, 'store'])->name('users.store');
Route::post('/login', [UserController::class, 'login'])->name('login');
