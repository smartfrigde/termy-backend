<?php

namespace App\Http\Middleware;
use Closure;
use Illuminate\Http\Request;
use App\Services\TokenService;

class AuthenticatedWithToken{
    protected $tokenService;

    public function __construct(TokenService $tokenService)
    {
        $this->tokenService = $tokenService;
    }

    public function handle(Request $request, Closure $next)
    {
        $token = $request->bearerToken();

        if (!$token) {
            return response()->json(['error' => 'Token not provided'], 401);
        }

        $personalAccessToken = $this->tokenService->decodeToken($token);

        if (!$personalAccessToken || $personalAccessToken === null) {
            return response()->json(['error' => 'Invalid or expired token'], 401);
        }

        return $next($request);
    }
}
