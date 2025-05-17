<?php

namespace App\Http\Controllers;

use App\Models\GpgKeys;
use App\Models\sshConnections;
use App\Models\Teams;
use App\Models\TeamsMembers;
use App\TeamRole;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class SshController extends Controller
{
    private function clearCache($sshId, $teamId, $userId, $perPage = 30)
    {
        Cache::forget("ssh_connections_{$userId}_{$teamId}_total_pages_per_page{$perPage}");

        $page = $this->calculatePageForShhConnection($sshId, $teamId, $userId, $perPage);
        $pageWithRevoked = $this->calculatePageForShhConnection($sshId, $teamId, $userId, $perPage, true);


        if ($page !== null) {

            $totalPages = ceil(sshConnections::withoutRevoked()->where('team_id', $teamId)->count() / $perPage);

            for ($currentPage = $page; $currentPage <= $totalPages; $currentPage++) {
                Cache::forget("ssh_connections_{$userId}_{$currentPage}_{$perPage}");
                Cache::forget("ssh_connections_{$userId}_{$currentPage}_{$perPage}_with_revoked");
            }
        }

        if ($pageWithRevoked !== null) {
            $totalPagesWithRevoked = ceil(sshConnections::where('team_id', $teamId)->count() / $perPage);

            for ($currentPage = $pageWithRevoked; $currentPage <= $totalPagesWithRevoked; $currentPage++) {
                Cache::forget("ssh_connections_{$userId}_{$currentPage}_{$perPage}_with_revoked");
            }
        }
    }


    private function calculatePageForShhConnection($sshConnectionId, $teamId, $perPage = 30, $getRevoked = false)
    {
        $query = sshConnections::where('team_id', $teamId)
            ->orderBy('created_at', 'desc');

        if ($getRevoked) {
            $query->get();
        } else {
            $query->withoutRevoked();
        }

        $sshConnections = $query->simplePaginate($perPage);


        $sshIndex = $sshConnections->getCollection()->search(function ($item) use ($sshConnectionId) {
            return $item->id === $sshConnectionId;
        });

        if ($sshIndex === false) {
            return null;
        }

        return ceil(($sshIndex + 1) / $perPage);
    }

    private function getDefaultUserTeam($userId)
    {
        $defaultTeam = Teams::where('type', 'default_user_team')
            ->whereHas('members', function ($query) use ($userId) {
                $query->where('user_id', $userId);
            })
            ->first();

        return $defaultTeam;
    }

    public function index(Request $request)
    {
        $authUser = $this->getUserFromToken($request);

        if (!$authUser) {
            return response()->json(['error' => "User not found"], 404);
        }

        $page = $request->input("page", 1);
        $perPage = $request->input("per_page", 30);
        $teamId = $request->input("team_id", null);
        $offset = ($page - 1) * $perPage;
        $getRevoked = $request->input("get_revoked", false);

        if (!$teamId) {
            $defaultTeam = $this->getDefaultUserTeam($authUser->id);

            if (!$defaultTeam) {
                return response()->json(['error' => "Default team not found"], 404);
            }

            $teamId = $defaultTeam->id;
        }

        if ($getRevoked) {
            $sshServers = Cache::remember("ssh_connections_{$authUser->id}_{$page}_{$perPage}_with_revoked", 120, function () use ($authUser, $teamId, $perPage, $offset) {
                return sshConnections::onlyRevoked()->with('gpgKey')
                    ->where('team_id', $teamId)
                    ->orderBy('created_at', 'desc')
                    ->offset($offset)
                    ->limit($perPage)
                    ->get();
            });
        } else {
            $sshServers = Cache::remember("ssh_connections_{$authUser->id}_{$page}_{$perPage}", 120, function () use ($authUser, $teamId, $perPage, $offset) {
                return sshConnections::withoutRevoked()->with('gpgKey')
                    ->where('team_id', $teamId)
                    ->orderBy('created_at', 'desc')
                    ->offset($offset)
                    ->limit($perPage)
                    ->get();
            });
        }

        $totalPages = Cache::remember("ssh_connections_{$authUser->id}_{$teamId}_total_pages_per_page{$perPage}", 120, function () use ($authUser, $teamId, $perPage) {
            return ceil(sshConnections::withoutRevoked()->where('team_id', $teamId)->count() / $perPage);
        });

        return response()->json([
            "ssh_connections" => $sshServers,
            "total_pages" => $totalPages,
            "current_page" => $page,
        ], 200);
    }

    public function store(Request $request)
    {
        $sshData = $request->validate([
            'login' => 'required|string|max:255',
            'hostname' => 'required|string|max:512',
            'name' => 'nullable|string|max:255',
            'password' => 'nullable|string|max:255',
            'team_id' => 'nullable|numeric|exists:teams,id',
        ]);

        $gpgKeyData = $request->validate([
            'private_key' => 'nullable|string',
            'public_key' => 'nullable|string',
        ]);

        $authUser = $this->getUserFromToken($request);

        $teamId = $request->input('team_id', null);
        if (!$teamId) {
            $defaultTeam = $this->getDefaultUserTeam($authUser->id);


            if (!$defaultTeam) {
                return response()->json(['error' => "Default team not found"], 404);
            }

            $sshData["team_id"] = $defaultTeam->id;
        }


        $userInTeam = TeamsMembers::withoutRevoked()->where('user_id', $authUser->id)
            ->where('team_id', $sshData['team_id'])
            ->first();

        if (!$userInTeam) {
            return response()->json(['error' => "User isn't a team member"], 403);
        }

        if (!TeamRole::hasHighestRole($userInTeam->permission_level_id, TeamRole::ADMINISTRATOR->value)) {
            return response()->json(['error' => "Permission denied"], 403);
        }

        $sshConnection = sshConnections::create($sshData);

        if (!$sshConnection) {
            return response()->json(['error' => "Failed to create SSH connection"], 500);
        }

        $gpgKey = null;
        if (isset($gpgKeyData['public_key']) || isset($gpgKeyData['private_key'])) {
            $gpgKey = GpgKeys::create([
                'private_key' => $gpgKeyData['private_key'] ?? null,
                'public_key' => $gpgKeyData['public_key'] ?? null,
                'ssh_connection_id' => $sshConnection->id,
            ]);
        }

        $this->clearCache($sshConnection->id, $sshData['team_id'], $authUser->id, 30);

        $team = Teams::find($sshData['team_id']) ?: null;
        $usersIds = $this->synchronizationService->getUserIdsFromTeam($team) ?: [];
        $this->synchronizationService->incrementSynchVersion($usersIds);


        return response()->json([
            "ssh_connection" => $sshConnection,
            "gpg_key" => $gpgKey ?: null,
            "message" => "SSH connection and GPG key created successfully",
        ], 201);
    }

    public function update(Request $request, $sshId): \Illuminate\Http\JsonResponse
    {
        $sshData = $request->validate([
            'login' => 'nullable|string|max:255',
            'hostname' => 'nullable|string|max:512',
            'port' => 'nullable|integer',
            'name' => 'nullable|string|max:255',
            'password' => 'nullable|string|max:255',
            'team_id' => 'nullable|numeric|exists:teams,id',
        ]);

        $gpgKeyData = $request->validate([
            'private_key' => 'nullable|string',
            'public_key' => 'nullable|string',
        ]);

        $authUser = $this->getUserFromToken($request);
        $teamId = $request->input('team_id', null);
        if (!$teamId) {
            $defaultTeam = $this->getDefaultUserTeam($authUser->id);

            if (!$defaultTeam) {
                return response()->json(['error' => "Default team not found"], 404);
            }

            $sshData['team_id'] = $defaultTeam->id;
        }

        if ($sshData) {
            $sshData['revoked'] = false;
        }


        $sshConnection = sshConnections::find($sshId);

        if (!$sshConnection) {
            return response()->json(['error' => "SSH connection not found"], 404);
        }

        $userInTeam = TeamsMembers::withoutRevoked()->where('user_id', $authUser->id)
            ->where('team_id', $sshConnection->team_id)
            ->first();

        if (!$userInTeam) {
            return response()->json(['error' => "User isn't a team member"], 403);
        }

        if (!TeamRole::hasHighestRole($userInTeam->permission_level_id, TeamRole::ADMINISTRATOR->value)) {
            return response()->json(['error' => "Permission denied"], 403);
        }

        $sshConnection->update($sshData);

        if (isset($gpgKeyData['public_key']) || isset($gpgKeyData['private_key'])) {
            $gpgKey = $sshConnection->gpgKey;

            if ($gpgKey) {
                $gpgKey->update([
                    'private_key' => $gpgKeyData['private_key'] ?? $gpgKey->private_key,
                    'public_key' => $gpgKeyData['public_key'] ?? $gpgKey->public_key,
                ]);
            } else {
                GpgKeys::create([
                    'private_key' => $gpgKeyData['private_key'] ?? null,
                    'public_key' => $gpgKeyData['public_key'] ?? null,
                    'ssh_connection_id' => $sshConnection->id,
                ]);
            }
        }

        $this->clearCache($authUser->id,  isset($sshData["team_id"]) ? $sshData['team_id'] : $sshConnection->team_id, 30);

        $team = Teams::find($sshData['team_id']) ?: null;
        $usersIds = $this->synchronizationService->getUserIdsFromTeam($team) ?: [];
        $this->synchronizationService->incrementSynchVersion($usersIds);

        return response()->json([
            "ssh_connection" => $sshConnection,
            "message" => "SSH connection updated successfully",
        ], 200);
    }

    public function destroy($sshConnectionsId, Request $request)
    {
        $sshConnection = sshConnections::findOrFail($sshConnectionsId);

        if (!$sshConnection) {
            return response()->json(['error' => "SSH connection not found"], 404);
        }

        $authUser = $this->getUserFromToken($request);

        $userInTeam = TeamsMembers::withoutRevoked()->where('user_id', $authUser->id)
            ->where('team_id', $sshConnection->team_id)
            ->first();

        if (!$userInTeam) {
            return response()->json(['error' => "User isn't a team member"], 403);
        }

        if (!TeamRole::hasHighestRole($userInTeam->permission_level_id, TeamRole::ADMINISTRATOR->value)) {
            return response()->json(['error' => "Permission denied"], 403);
        }

        if ($sshConnection->revoked === true) {
            $sshConnection->delete();
        } else {
            $sshConnection->update(['revoked' => true]);
            $sshConnection->save();
        }

        $this->clearCache($authUser->id, $sshConnection->team_id, 30);

        $team = Teams::find($sshConnection->team_id) ?: null;
        $usersIds = $this->synchronizationService->getUserIdsFromTeam($team) ?: [];
        $this->synchronizationService->incrementSynchVersion($usersIds);

        return response()->json([
            "message" => "SSH connection revoked successfully",
        ], 200);
    }
}
