<?php

namespace App\Http\Controllers\WebSocket;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class WsController extends Controller
{
    public function syncConnect(Request $request, $id): bool
    {
        $authUser = $this->getUserFromToken($request);

        $authUserId = $authUser->id;

        return $authUserId === $id;
    }
}
