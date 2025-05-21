<?php

namespace App\Http\Controllers\WebSocket;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Broadcast;

class WsController extends Controller
{
    public function syncConnect(Request $request, $id): bool
    {
        $authUser = $this->getUserFromToken($request);

        $authUserId = $authUser->id;

        return $authUserId === $id;
    }

    public function auth(Request $request)
    {
        $user = $this->getUserFromToken($request);

        $request->setUserResolver(function () use ($user) {
            return $user;
        });

        return Broadcast::auth($request);
    }
}
