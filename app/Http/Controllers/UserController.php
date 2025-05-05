<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Hash;

use App\Services\TokenService;

class UserController extends Controller
{
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'surname' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|',
            'password' => 'required|string|min:8',
        ]);


        if (User::where('email', $request->email)->exists()) {
            return response()->json(['error' => 'Email is in use'], 422);
        }

        $user = User::create([
            'name' => $request->name,
            'surname' => $request->surname,
            'email' => $request->email,
            'password' => bcrypt($request->password),
        ]);

        return response()->json(['user' => $user], 201);
    }

    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|string|email|max:255|',
            'password' => 'required|string|min:8',
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json(['error' => 'Invalid credentials'], 401);
        }

        $apiToken = $this->tokenService->generateApiToken($user);
        $refreshToken = $this->tokenService->generateRefreshToken($user);

        return response()->json([
            'user' => $user,
            'api_token' => $apiToken,
            'refresh_token' => $refreshToken,
        ]);
    }

    // public function refresh(Request $request)
    // {
    //     $request->validate([
    //         'refresh_token' => 'required|string',
    //     ]);

    //     $refreshToken = $request->input('refresh_token');

    //     if (!$user) {
    //         return response()->json(['error' => 'Invalid refresh token'], 401);
    //     }

    //     $newApiToken = $this->tokenService->generateApiToken($user);

    //     return response()->json([
    //         'api_token' => $newApiToken,
    //     ]);
    // }
}
