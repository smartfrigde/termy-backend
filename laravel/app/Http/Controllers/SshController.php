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
    private function clearCache($userId, $teamId, $perPage = 30)
    {
        Cache::forget("ssh_connections_{$userId}_{$teamId}_total_pages_per_page{$perPage}");

        $page = $this->calculatePageForShhConnection($teamId, $userId, $perPage);
        $pageWithRevoked = $this->calculatePageForShhConnection($teamId, $userId, $perPage, true);

        if ($page !== null) {
            Cache::forget("ssh_connections_{$userId}_{$page}_{$perPage}");
        }

        if ($pageWithRevoked !== null) {
            Cache::forget("ssh_connections_{$userId}_{$pageWithRevoked}_{$perPage}_with_revoked");
        }
    }

    private function calculatePageForShhConnection($sshConnectionId, $teamId, $authUserId, $perPage = 30, $getRevoked = false)
    {
        $teams = Teams::byMemberId($authUserId)
            ->withoutRevoked()
            ->orderBy('created_at', 'desc')
            ->pluck("id")
            ->toArray();

        if ($getRevoked) {
            $sshConnectionsIds = sshConnections::onlyRevoked()
                ->where('team_id', $teamId)
                ->orderBy('created_at', 'desc')
                ->pluck("id")
                ->toArray();
        } else {
            $sshConnectionsIds = sshConnections::withoutRevoked()
                ->where('team_id', $teamId)
                ->orderBy('created_at', 'desc')
                ->pluck("id")
                ->toArray();
        }

        $sshIndex = array_search($sshConnectionId, $sshConnectionsIds);

        if ($sshIndex  === false) {
            return null;
        }

        $page = (int) ceil(($sshIndex + 1) / $perPage);

        return $page;
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


        // Sprawdzenie, czy użytkownik należy do zespołu
        $userInTeam = TeamsMembers::withoutRevoked()->where('user_id', $authUser->id)
            ->where('team_id', $sshData['team_id'])
            ->first();

        if (!$userInTeam) {
            return response()->json(['error' => "User isn't a team member"], 403);
        }

        // Sprawdzenie, czy użytkownik ma odpowiednie uprawnienia
        if (!TeamRole::hasHighestRole($userInTeam->permission_level_id, TeamRole::ADMINISTRATOR->value)) {
            return response()->json(['error' => "Permission denied"], 403);
        }

        // Utworzenie połączenia SSH
        $sshConnection = sshConnections::create($sshData);

        if (!$sshConnection) {
            return response()->json(['error' => "Failed to create SSH connection"], 500);
        }

        // Utworzenie kluczy GPG, jeśli zostały dostarczone
        if (isset($gpgKeyData['public_key']) || isset($gpgKeyData['private_key'])) {
            GpgKeys::create([
                'private_key' => $gpgKeyData['private_key'] ?? null,
                'public_key' => $gpgKeyData['public_key'] ?? null,
                'ssh_connection_id' => $sshConnection->id,
            ]);
        }

        // Czyszczenie pamięci podręcznej
        $this->clearCache($authUser->id, $sshData['team_id'], 30);


        return response()->json([
            // "ssh_connection" => $sshConnection,
            // "gpg_key" => $gpgKey ?: null,
            "message" => "SSH connection and GPG key created successfully",
        ], 201);
    }

    public function update(Request $request, $sshId)
    {
        $sshData = $request->validate([
            'login' => 'nullable|string|max:255',
            'hostname' => 'nullable|string|max:512',
            'port' => 'nullable|integer',
            'name' => 'nullable|string|max:255',
            'password' => 'nullable|string|max:255',
            'team_id' => 'nullable|numeric',
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

        return response()->json([
            "message" => "SSH connection revoked successfully",
        ], 200);
    }
}
