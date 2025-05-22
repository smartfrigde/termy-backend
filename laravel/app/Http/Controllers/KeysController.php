<?php

namespace App\Http\Controllers;

use App\Models\GpgKeys;
use Illuminate\Http\Request;

class KeysController extends Controller
{
    public function clearCache($userId, $keyId, $perPage = 30){

    }

    public function index()
    {
        //
    }


    public function store(Request $request)
    {
        $authUser = $this->getUserFromToken($request);


        if (!$authUser) {
            return response()->json([], 401);
        }

        $keyData = $request->validate([
            "name" => "required|string",
            "public_key" => "required|string",
            "private_key" => "nullable|string",
            "password" => "nullable|string",
        ]);

        $gpgKey = new GpgKeys([
            "name" => $keyData["name"],
            "public_key" => $keyData["public_key"],
            "private_key" => $keyData["private_key"],
            "password" => $keyData["password"],
            "user_id" => $authUser->id,
        ]);

        $this->clearCache($authUser->id, $gpgKey->id);

        return $this->sendResponse($gpgKey, 200, $authUser->id);

    }

    public function update(Request $request, $keyId)
    {
        if (!$keyId) {
            return response()->json([], 400);
        }

        $authUser = $this->getUserFromToken($request);

        if (!$authUser) {
            return response()->json([], 401);
        }

        $gpgKey = GpgKeys::where("id", $keyId)->where("user_id", $authUser->id)->first();

        if (!$gpgKey) {
            return $this->sendResponse("Gpg Key not found", 404, $authUser->id);
        }

        $keyData = $request->validate([
            "name" => "required|string",
            "public_key" => "required|string",
            "private_key" => "nullable|string",
            "password" => "nullable|string",
        ]);

        $gpgKey->update($keyData);

        return $this->sendResponse($gpgKey, 200, $authUser->id);
    }

    public function destroy($keyId, Request $request)
    {
        if (!$keyId) {
            return response()->json([], 400);
        }

        $authUser = $this->getUserFromToken($request);

        if (!$authUser) {
            return response()->json([], 401);
        }

        $gpgKey = GpgKeys::where("id", $keyId)->where("user_id", $authUser->id)->first();

        if (!$gpgKey) {
            return $this->sendResponse("Gpg Key not found", 404, $authUser->id);
        }

        $gpgKey->update(["revoked" => true]);

        return $this->sendResponse("gpg key deleted successful", 200, $authUser->id);
    }
}
