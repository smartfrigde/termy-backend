<?php

namespace App\Http\Controllers;

use App\Services\TokenService;

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
}
