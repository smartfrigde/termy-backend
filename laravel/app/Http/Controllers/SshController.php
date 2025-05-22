<?php

namespace App\Http\Controllers;

use App\Models\sshConnections;
use App\Models\Teams;
use App\Models\TeamsMembers;
use App\TeamRole;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class SshController extends Controller
{
    private function clearCache($sshId, $teamId, $userId, $perPage = 30): void
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


    private function calculatePageForShhConnection($sshConnectionId, $teamId, $perPage = 30, $getRevoked = false): ?float
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
        return Teams::where('type', 'default_user_team')
            ->whereHas('members', function ($query) use ($userId) {
                $query->where('user_id', $userId);
            })
            ->first();
    }

    public function index(Request $request)
    {
        $authUser = $this->getUserFromToken($request);

        if (!$authUser) {
            return $this->sendResponse(['error' => "User not found"], 404, userId: $authUser->id);
        }

        $page = $request->input("page", 1);
        $perPage = $request->input("per_page", 30);
        $teamId = $request->input("team_id", null);
        $offset = ($page - 1) * $perPage;
        $getRevoked = $request->input("get_revoked", false);

        if (!$teamId) {
            $defaultTeam = $this->getDefaultUserTeam($authUser->id);

            if (!$defaultTeam) {
                return $this->sendResponse(['error' => "Default team not found"], 404, userId: $authUser->id);
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

        return $this->sendResponse([
            "ssh_connections" => $sshServers,
            "total_pages" => $totalPages,
            "current_page" => $page,
        ], 200, userId: $authUser->id);
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

        $authUser = $this->getUserFromToken($request);

        $teamId = $request->input('team_id', null);
        if (!$teamId) {
            $defaultTeam = $this->getDefaultUserTeam($authUser->id);


            if (!$defaultTeam) {
                return $this->sendResponse(['error' => "Default team not found"], 404, userId: $authUser->id);
            }

            $sshData["team_id"] = $defaultTeam->id;
        }


        $userInTeam = TeamsMembers::withoutRevoked()->where('user_id', $authUser->id)
            ->where('team_id', $sshData['team_id'])
            ->first();

        if (!$userInTeam) {
            return $this->sendResponse(['error' => "User isn't a team member"], 403, userId: $authUser->id);
        }

        if (!TeamRole::hasHighestRole($userInTeam->permission_level_id, TeamRole::ADMINISTRATOR->value)) {
            return $this->sendResponse(['error' => "Permission denied"], 403, userId: $authUser->id);
        }

        $sshConnection = sshConnections::create($sshData);

        if (!$sshConnection) {
            return $this->sendResponse(['error' => "Failed to create SSH connection"], 500, userId: $authUser->id);
        }

        $this->clearCache($sshConnection->id, $sshData['team_id'], $authUser->id, 30);

        $team = Teams::find($sshData['team_id']) ?: null;
        $usersIds = $this->synchronizationService->getUsersIdsFromTeam($team) ?: [];
        $usersAndHisVersios = $this->synchronizationService->incrementSyncVersion($usersIds);
        $this->synchronizationService->sendNotification($usersAndHisVersios);


        return $this->sendResponse([
            "ssh_connection" => $sshConnection,
            "message" => "SSH connection and GPG key created successfully",
        ], 201, userId: $authUser->id);
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

        $authUser = $this->getUserFromToken($request);
        $teamId = $request->input('team_id', null);
        if (!$teamId) {
            $defaultTeam = $this->getDefaultUserTeam($authUser->id);

            if (!$defaultTeam) {
                return $this->sendResponse(['error' => "Default team not found"], 404, userId: $authUser->id);
            }

            $sshData['team_id'] = $defaultTeam->id;
        }

        if ($sshData) {
            $sshData['revoked'] = false;
        }


        $sshConnection = sshConnections::find($sshId);

        if (!$sshConnection) {
            return $this->sendResponse(['error' => "SSH connection not found"], 404, userId: $authUser->id);
        }

        $userInTeam = TeamsMembers::withoutRevoked()->where('user_id', $authUser->id)
            ->where('team_id', $sshConnection->team_id)
            ->first();

        if (!$userInTeam) {
            return $this->sendResponse(['error' => "User isn't a team member"], 403, userId: $authUser->id);
        }

        if (!TeamRole::hasHighestRole($userInTeam->permission_level_id, TeamRole::ADMINISTRATOR->value)) {
            return $this->sendResponse(['error' => "Permission denied"], 403, userId: $authUser->id);
        }

        $sshConnection->update($sshData);

        $this->clearCache($authUser->id,  isset($sshData["team_id"]) ? $sshData['team_id'] : $sshConnection->team_id, 30);

        $team = Teams::find($sshData['team_id']) ?: null;
        $usersIds = $this->synchronizationService->getUsersIdsFromTeam($team) ?: [];
        $usersAndHisVersios = $this->synchronizationService->incrementSyncVersion($usersIds);
        $this->synchronizationService->sendNotification($usersAndHisVersios);

        return $this->sendResponse([
            "ssh_connection" => $sshConnection,
            "message" => "SSH connection updated successfully",
        ], 200, userId: $authUser->id);
    }

    public function destroy($sshConnectionsId, Request $request)
    {
        $sshConnection = sshConnections::findOrFail($sshConnectionsId);


        $authUser = $this->getUserFromToken($request);

        if (!$sshConnection) {
            return $this->sendResponse(['error' => "SSH connection not found"], 404, userId: $authUser->id);
        }

        $userInTeam = TeamsMembers::withoutRevoked()->where('user_id', $authUser->id)
            ->where('team_id', $sshConnection->team_id)
            ->first();

        if (!$userInTeam) {
            return $this->sendResponse(['error' => "User isn't a team member"], 403, userId: $authUser->id);
        }

        if (!TeamRole::hasHighestRole($userInTeam->permission_level_id, TeamRole::ADMINISTRATOR->value)) {
            return $this->sendResponse(['error' => "Permission denied"], 403, userId: $authUser->id);
        }

        if ($sshConnection->revoked === true) {
            $sshConnection->delete();
        } else {
            $sshConnection->update(['revoked' => true]);
            $sshConnection->save();
        }

        $this->clearCache($authUser->id, $sshConnection->team_id, 30);

        $team = Teams::find($sshConnection->team_id) ?: null;
        $usersIds = $this->synchronizationService->getUsersIdsFromTeam($team) ?: [];
        $usersAndHisVersios = $this->synchronizationService->incrementSyncVersion($usersIds);
        $this->synchronizationService->sendNotification($usersAndHisVersios);

        return $this->sendResponse([
            "message" => "SSH connection revoked successfully",
        ], 200, userId: $authUser->id);
    }
}
