<?php

namespace App\Http\Controllers;

use App\Services\TokenService;
use Illuminate\Http\Request;

abstract class Controller
{
    protected $tokenService;

    public function __construct(TokenService $tokenService)
    {
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
