<?php

use App\Http\Controllers\KeysController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\UserController;
use App\Http\Controllers\TeamController;
use App\Http\Middleware\AuthenticatedWithToken;
use App\Http\Controllers\SshController;
use App\Http\Middleware\BypassCsrfMiddleware;

require __DIR__ ."/channels.php";

Route::middleware(BypassCsrfMiddleware::class)->group(function () {
    Route::post('/users', [UserController::class, 'store'])->name('users.store');
    Route::post('/login', [UserController::class, 'login'])->name('login');
    Route::post('/refresh-token', [UserController::class, 'refresh'])->name('refresh-token');


    Route::middleware(AuthenticatedWithToken::class)->group(function () {
        Route::patch('/users/{id}', [UserController::class, 'update'])->name('users.update');
        Route::delete('/users/{id}', [UserController::class, 'destroy'])->name('users.destroy');

        // ----------------
        // teams endpoints
        // ----------------
        Route::prefix("teams")->group(function () {
            Route::post('/', [TeamController::class, 'store'])->name('teams.store');
            Route::get('/', [TeamController::class, 'index'])->name('teams.index');
            Route::get('/{id}', [TeamController::class, 'show'])->name('teams.show');
            Route::patch('/{id}', [TeamController::class, 'update'])->name('teams.update');
            Route::delete('/{id}', [TeamController::class, 'destroy'])->name('teams.destroy');
            Route::post("members", [TeamController::class, 'addMember'])->name('teams.members.store');
        });

        // -----------------------
        // team members endpoints
        // -----------------------
        Route::prefix("teams/{teamId}/members")->group(function () {
            Route::get('/', [TeamController::class, 'getMembers'])->name('teams.members.index');
            Route::get('/me', [TeamController::class, 'getMember'])->name('teams.members.show');
            Route::patch('/{id}', [TeamController::class, 'updateMember'])->name('teams.members.update');
            Route::delete('/{id}', [TeamController::class, 'removeMember'])->name('teams.members.destroy');
        });

        // ------------------------
        // ssh endpoints
        // ------------------------
        Route::prefix("ssh")->group(function () {
            Route::post('/', [SshController::class, 'store'])->name('ssh.store');
            Route::get('/', [SshController::class, 'index'])->name('ssh.index');
            Route::patch('/{id}', [SshController::class, 'update'])->name('ssh.update');
            Route::delete('/{id}', [SshController::class, 'destroy'])->name('ssh.destroy');
        });

        // -----------------------
        // gpg keys endpoints
        // -----------------------
        Route::prefix("keys")->group(function () {
            Route::post("/", [KeysController::class, 'store'])->name('keys.store');
            Route::delete("/", [KeysController::class, 'destroy'])->name('keys.destroy');
            Route::patch("/", [KeysController::class, 'update'])->name('keys.update');
        });
    });
});
