<?php

namespace App\Http\Controllers;

use App\DataCategories;
use App\Models\GpgKeys;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class KeysController extends Controller
{
    public function clearCache($userId, $gpgKeysId, $perPage = 30): void
    {

        $page = $this->calculatePage($userId,$gpgKeysId,$perPage);


        if ($page !== null) {

            $totalPages = ceil(GpgKeys::notRevoked()->where('user_id', $userId)->count() / $perPage);

            for ($currentPage = $page; $currentPage <= $totalPages; $currentPage++) {
                Cache::forget("keys_user_{$userId}_page_{$page}_pre_{$perPage}");
                Cache::forget("keys_user_{$userId}_page_{$page}_pre_{$perPage}");
            }
        }
    }

    public function calculatePage($userId, $gpgKeysId, $perPage): ?float
    {
        $query = GpgKeys::notRevoked()->where('user_id', $userId)
            ->orderBy('created_at', 'desc');

        $gpgKeys = $query->simplePaginate($perPage);


        $sshIndex = $gpgKeys->getCollection()->search(function ($item) use ($gpgKeysId) {
            return $item->id === $gpgKeysId;
        });

        if ($sshIndex === false) {
            return null;
        }

        return ceil(($sshIndex + 1) / $perPage);
    }

    public function index(Request $request)
    {
        $perPage = $request->query('perPage', 30);
        $page = $request->query('page', 1);
        $offset = ($page * $perPage) - $perPage;

        $authUser = $this->getUserFromToken($request);

        if (!$authUser) {
            return response()->json([], 401);
        }

        $gpgKeys = Cache::remember("keys_user_{$authUser->id}_page_{$page}_pre_{$perPage}", 120, function () use ($page, $perPage, $authUser, $offset) {
            return GpgKeys::notRevoked()
                ->where('user_id', $authUser->id)
                ->offset($offset)
                ->limit($perPage)
                ->get();
        }) ?: [];

        $keysCount = GpgKeys::notRevoked()->where("user_id", $authUser->id)->count() ?: 1;
        $totalPages = ceil($keysCount / $perPage);

        return $this->sendResponse([
            'keys' => $gpgKeys,
            'totalPages' => $totalPages,
            'current_page' => $page,
        ], 200, $authUser->id);
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

        $gpgKey = GpgKeys::create([
            "name" => key_exists("name", $keyData) ? $keyData["name"] : null,
            "public_key" => key_exists("public_key", $keyData) ? $keyData["public_key"] : null,
            "private_key" => key_exists("private_key", $keyData) ? $keyData["private_key"] : null,
            "password" => key_exists("password", $keyData) ? $keyData["password"] : null,
            "user_id" => $authUser->id,
        ]);

        $this->clearCache($authUser->id, $gpgKey->id);

        $usersAndHisVersions = $this->synchronizationService->incrementSyncVersion([ $authUser->id ]);
        $this->synchronizationService->sendNotification($usersAndHisVersions, DataCategories::keys->value);

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

        $this->clearCache($authUser->id, $gpgKey->id);

        $usersAndHisVersions = $this->synchronizationService->incrementSyncVersion([ $authUser->id ]);
        $this->synchronizationService->sendNotification($usersAndHisVersions, DataCategories::keys->value);

        return $this->sendResponse(["gpg key deleted successful"], 200, $authUser->id);
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
            return $this->sendResponse(["Gpg Key not found"], 404, $authUser->id);
        }

        $keyData = $request->validate([
            "name" => "required|string",
            "public_key" => "required|string",
            "private_key" => "nullable|string",
            "password" => "nullable|string",
        ]);

        $gpgKey->update($keyData);
        $this->clearCache($authUser->id, $gpgKey->id);

        $usersAndHisVersions = $this->synchronizationService->incrementSyncVersion([ $authUser->id ]);
        $this->synchronizationService->sendNotification($usersAndHisVersions, DataCategories::keys->value);

        return $this->sendResponse($gpgKey, 200, $authUser->id);
    }
}
