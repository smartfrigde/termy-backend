<?php

namespace App\Http\Controllers;

use App\Models\synchronizationVersions;
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

    protected function sendResponse($data, $code, $userId){
        $syncV = synchronizationVersions::where("user_id", $userId)->first();
        $data["sync_version"] = $syncV->version ?: 0;
        return response()->json($data, $code);
    }
}
