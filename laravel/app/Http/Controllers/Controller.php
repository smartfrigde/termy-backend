<?php

namespace App\Http\Controllers;

use App\Services\SynchronizationService;
use App\Services\TokenService;
use Illuminate\Http\Request;

abstract class Controller
{
    protected $tokenService;
    protected $synchronizationService;

    public function __construct(TokenService $tokenService, SynchronizationService $synchronizationService)
    {
        $this->synchronizationService = $synchronizationService;
        $this->tokenService = $tokenService;
    }

    protected function generateApiToken($user)
    {
        return $this->tokenService->generateApiToken($user);
    }

    protected function getUserFromToken(Request $request)
    {
        $token = $request->bearerToken();

        if ($token) {
            return $this->tokenService->getTokensOwner($token);
        }

        return null;
    }
}
