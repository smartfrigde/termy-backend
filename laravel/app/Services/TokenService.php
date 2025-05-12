<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Laravel\Sanctum\PersonalAccessToken;

class TokenService
{

    private $accessTokenLiveTime;
    private $refreshTokenLiveTime;

    public function __construct()
    {
        $this->accessTokenLiveTime = config('sanctum.expiration');
        $this->refreshTokenLiveTime = config('sanctum.refresh_token_expiration');
    }

    public function generateApiToken($user)
    {
        return $user->createToken('api_token', ['*'], Carbon::now()->addMinutes(15))->plainTextToken;
    }


    public function generateRefreshToken($user)
    {
        return $user->createToken('refresh_token', ['*'], Carbon::now()->addDays(7))->plainTextToken;
    }


    public function decodeToken($token)
    {
        $personalAccessToken = PersonalAccessToken::findToken($token);

        if (!$personalAccessToken || $this->isTokenExpired($personalAccessToken)) {
            return null;
        }

        return $personalAccessToken;
    }

    public function isTokenExpired($personalAccessToken)
    {
        if (!$personalAccessToken) {
            return true;
        }

        $isExp = Carbon::now() >= $personalAccessToken->expires_at;

        return $isExp;
    }

    public function decodeAndRevokeToken($token)
    {
        $personalAccessToken = PersonalAccessToken::findToken($token);

        if ($personalAccessToken) {
            $personalAccessToken->delete();
        }
    }

    public function revokeToken($personalAccessToken)
    {
        if ($personalAccessToken && $personalAccessToken instanceof PersonalAccessToken) {
            $personalAccessToken->delete();
        }
    }

    public function getTokensOwner($token)
    {
        $personalAccessToken = PersonalAccessToken::findToken($token);

        if (!$personalAccessToken || $this->isTokenExpired($personalAccessToken)) {
            return null;
        }

        return $personalAccessToken->tokenable;
    }

    public function isAPIToken($token)
    {
        $personalAccessToken = PersonalAccessToken::findToken($token);

        if ($personalAccessToken && !$this->isTokenExpired($personalAccessToken)) {
            return $personalAccessToken->name === 'api_token';
        }
        return null;

    }
    public function isRefreshToken($token)
    {
        $personalAccessToken = PersonalAccessToken::findToken($token);

        if ($personalAccessToken && !$this->isTokenExpired($personalAccessToken)) {
            return $personalAccessToken->name === 'refresh_token';
        }

        return null;
    }
}
