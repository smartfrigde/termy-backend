<?php

namespace App\Http\Controllers;

use App\Models\Teams;
use App\Models\TeamsMembers;
use App\TeamRole;
use App\TeamsTypesEnum;
use Illuminate\Support\Facades\Cache;
use Illuminate\Http\Request;
// userId: $authUser->id);
class TeamController extends Controller
{

    private function clearCache($userId, $team, $perPage = 30)
    {
        Cache::forget("teams_{$userId}_count");

        $page = $this->calculatePageForTeam($team->id, $userId);
        $pageWithRevoked = $this->calculatePageForTeam($team->id, $userId, true);
        $cacheKey = "teams_{$userId}_page_{$page}_perPage_30";



        if ($page !== null) {
            $totalPages = ceil(Teams::byMemberId($userId)->withoutRevoked()->count() / $perPage);

            for ($currentPage = $page; $currentPage <= $totalPages; $currentPage++) {
                if (Cache::has("teams_{$userId}_page_{$page}_perPage_30")) {
                    Cache::forget("teams_{$userId}_page_{$page}_perPage_30");
                }
            }
        }

        if ($pageWithRevoked !== null) {
            $totalPagesWithRevoked = ceil(Teams::byMemberId($userId)->count() / $perPage);

            for ($currentPage = $page; $currentPage <= $totalPagesWithRevoked; $currentPage++) {
                if (Cache::has("teams_{$userId}_page_{$page}_perPage_30_with_revoked")) {
                    Cache::forget("teams_{$userId}_page_{$page}_perPage_30_with_revoked");
                }
            }
        }
    }


    private function calculatePageForTeam($id, $authUserId, $perPage = 30, $getRevoked = false)
    {

        $query = Teams::byMemberId($authUserId)
            ->orderBy('created_at', 'desc');

        if ($getRevoked) {
            $query->onlyRevoked();
        } else {
            $query->withoutRevoked();
        }

        $data = $query->simplePaginate($perPage);

        $sshIndex = $data->getCollection()->search(function ($item) use ($id) {
            return $item->id === $id;
        });

        if ($sshIndex === false) {
            return null;
        }

        return ceil(($sshIndex + 1) / $perPage);
    }


    public function index(Request $request)
    {
        $page = $request->input('page', 1);
        $perPage = $request->input('per_page', 30);
        $offset = ($page - 1) * $perPage;
        $authUser = $this->getUserFromToken($request);

        if (!$authUser) {
            return $this->sendResponse(['error' => 'Unauthorized'], 401, userId: $authUser->id);
        }

        if ($perPage > 100) {
            return $this->sendResponse(['error' => 'Maximum per_page limit is 100'], 400, userId: $authUser->id);
        }

        $teams = Cache::remember("teams_{$authUser->id}_page_{$page}_perPage_{$perPage}", 1, function () use ($authUser, $offset, $perPage) {
            return Teams::byMemberId($authUser->id)
                ->withoutRevoked()
                ->withoutDefaultTeam()
                ->orderBy('created_at', 'desc')
                ->with(['members' => function ($query) {
                    $query->select('id', 'team_id', 'user_id', 'permission_level_id');
                }])
                ->offset($offset)
                ->limit($perPage)
                ->get()
                ->map(function ($team) use ($authUser) {
                    $team->permission_in_team = optional(
                        $team->members->where("user_id", $authUser->id)->first()
                    )->permission_level_id;

                    unset($team->members);

                    if (!TeamRole::hasHighestRole($team->permission_in_team, TeamRole::ADMINISTRATOR->value)) {
                        unset($team->join_code);
                    }

                    return $team;
                });
        });

        $totalItems = Teams::withoutRevoked()->byMemberId($authUser->id)->count();
        $totalPages = (int) ceil($totalItems / $perPage);

        return $this->sendResponse([
            'teams' => $teams,
            'total_pages' => $totalPages,
            'current_page' => (int) $page,
            'total_user_teams' => $totalItems
        ], 200, userId: $authUser->id);
    }


    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
        ]);

        $authUser = $this->getUserFromToken($request);

        if (!$authUser) {
            return $this->sendResponse(['error' => 'Unauthorized'], 401, userId: $authUser->id);
        }

        $team = Teams::create([
            'name' => $request->name,
        ]);

        if (!$team) {
            return $this->sendResponse(['error' => 'Failed to create team'], 500, userId: $authUser->id);
        }

        $teamOwner = TeamsMembers::create([
            'user_id' => $authUser->id,
            'team_id' => $team->id,
            'permission_level_id' => TeamRole::OWNER,
        ]);

        if (!$teamOwner) {
            $team->delete();
            return $this->sendResponse(['error' => 'Failed to assign team owner'], 500, userId: $authUser->id);
        }


        $this->clearCache($authUser->id, $team);

        $usersIds = $this->synchronizationService->getUsersIdsFromTeam($team);
        $this->synchronizationService->incrementSyncVersion($usersIds);
        $this->synchronizationService->sendNotification($usersIds);

        $createdTeam = Teams::where("id", $team->id)
            ->with(['members' => function ($query) {
                $query->select('id', 'team_id', 'user_id', 'permission_level_id');
            }])
            ->get()
            ->map(function ($team) use ($authUser) {
                $team->permission_in_team = optional(
                    $team->members->where("user_id", $authUser->id)->first()
                )->permission_level_id ?: 1;

                unset($team->members);

                if (!TeamRole::hasHighestRole($team->permission_in_team, TeamRole::ADMINISTRATOR->value)) {
                    unset($team->join_code);
                }

                return $team;
            });

        return $this->sendResponse([
            'team' => $createdTeam[0],
        ], 201, userId: $authUser->id);
    }


    public function show(Teams $teams, Request $request)
    {
        $authUser = $this->getUserFromToken($request);

        if (!$authUser) {
            return $this->sendResponse(['error' => 'Unauthorized'], 401, userId: $authUser->id);
        }

        $teamMember = TeamsMembers::WithoutRevoked()->where('user_id', $authUser->id)
            ->where('team_id', $teams->id)
            ->first() ?? null;

        if (!$teamMember) {
            return $this->sendResponse(['error' => 'You are not a member of this team'], 403, userId: $authUser->id);
        }

        $team = Cache::remember("team_{$teams->id}_{$authUser->id}", 30, function () use ($teams) {
            return Teams::withoutRevoked()->find($teams->id)->load(['members.user']);
        });

        if (!$team) {
            return $this->sendResponse(['error' => 'Team not found'], 404, userId: $authUser->id);
        }

        $totalItems = Teams::withoutRevoked()->byMemberId($authUser->id)->count();
        $totalPages = (int) ceil($totalItems / 30);
        $teamPage = $this->calculatePageForTeam($team->id, $authUser->id, 30);

        return $this->sendResponse([
            'team' => $team,
            "total_pages" => $totalPages,
            "current_page" => $teamPage,
        ], 200, userId: $authUser->id);
    }


    public function update(Request $request, $teamId)
    {

        $teams = Teams::withoutRevoked()->find($teamId);
        $authUser = $this->getUserFromToken($request);

        if (!$teams) {
            return $this->sendResponse(['error' => ''], 404, userId: $authUser->id);
        }

        if ($teams->revoked) {
            return $this->sendResponse(['error' => 'This team is revoked'], 403, userId: $authUser->id);
        }

        if ($teams->type === TeamsTypesEnum::PRIVATE_USER_TEAM->value) {
            return $this->sendResponse(['error' => 'This team cannot be edited'], 403, userId: $authUser->id);
        }

        $request->validate([
            'name' => 'required|string|max:255',
        ]);


        if (!$authUser) {
            return $this->sendResponse(['error' => 'Unauthorized'], 401, userId: $authUser->id);
        }

        $teamMember = TeamsMembers::where('user_id', $authUser->id)
            ->where('team_id', $teams->id)
            ->first() ?: null;

        if (!$teamMember) {
            return $this->sendResponse(['error' => 'You are not a member of this team'], 403, userId: $authUser->id);
        }

        if ($teamMember->permission_level_id !== TeamRole::OWNER->value && $teamMember->permission_level_id !== TeamRole::ADMINISTRATOR->value) {
            return $this->sendResponse(['error' => 'Only team owners and administrators can update the team'], 403, userId: $authUser->id);
        }

        $teams->update($request->only('name'));

        $this->clearCache($authUser->id, $teams);

        $usersIds = $this->synchronizationService->getUsersIdsFromTeam($teams);
        $this->synchronizationService->incrementSyncVersion($usersIds);
        $this->synchronizationService->sendNotification($usersIds);

        return $this->sendResponse([
            'team' => $teams,
        ], 200, userId: $authUser->id);
    }


    public function destroy($teamId, Request $request)
    {
        $teams = Teams::withoutRevoked()->find($teamId);
        $authUser = $this->getUserFromToken($request);

        if (!$teams) {
            return $this->sendResponse(['error' => ''], 404, userId: $authUser->id);
        }

        if ($teams->revoked) {
            return $this->sendResponse(['error' => 'This team is revoked'], 403, userId: $authUser->id);
        }

        if ($teams->type === TeamsTypesEnum::PRIVATE_USER_TEAM->value) {
            return $this->sendResponse(['error' => 'This team cannot be edited'], 403, userId: $authUser->id);
        }


        if (!$authUser) {
            return $this->sendResponse(['error' => 'Unauthorized'], 401, userId: $authUser->id);
        }

        $teamMember = TeamsMembers::where('user_id', $authUser->id)
            ->where('team_id', $teams->id)
            ->first() ?? null;


        if ($teamMember->permission_level_id !== TeamRole::OWNER->value) {
            return $this->sendResponse(['error' => 'You cannot delete this team'], 403, userId: $authUser->id);
        }

        $teams->update(['revoked' => true]);

        $usersIds = $this->synchronizationService->getUsersIdsFromTeam($teams);
        $this->synchronizationService->incrementSyncVersion($usersIds);
        $this->synchronizationService->sendNotification($usersIds);

        return $this->sendResponse(['message' => 'Team deleted successfully'], 200, userId: $authUser->id);
    }


    // -------------------
    // Member Management
    // -------------------
    private function clearTeamMemberCache($team, $memberId)
    {
        Cache::forget("teams_members_{$team->id}_count");

        $page = $this->calculatePageForTeamMember($memberId, $team->id);
        $cacheKey = "teams_members_{$team->id}_page_{$page}_perPage_30";

        $teams = Cache::get($cacheKey) ?? collect();
        $teams->push($team);

        Cache::put($cacheKey, $teams, 60);
    }

    private function calculatePageForTeamMember($memberId, $teamId, $perPage = 30)
    {
        $members = TeamsMembers::where('team_id', $teamId)->withoutRevoked()
            ->orderBy('created_at', 'desc')
            ->pluck('user_id')
            ->toArray();

        $memberIndex = false;

        foreach ($members as $index => $member) {
            if ($member === $memberId) {
                $memberIndex = $index;
                break;
            }
        }

        if ($memberIndex === false) {
            return null;
        }

        $page = (int) ceil(($memberIndex + 1) / $perPage);

        return $page;
    }

    public function addMember(Request $request)
    {
        $request->validate([
            'join_code' => 'required|string',
        ]);

        $authUser = $this->getUserFromToken($request);
        $teams = Teams::byJoinCode($request->input("join_code"))->first();

        if (!$teams) {
            return $this->sendResponse(['error' => ''], 404, userId: $authUser->id);
        }

        if ($teams->revoked) {
            return $this->sendResponse(['error' => 'This team is revoked'], 404, userId: $authUser->id);
        }

        if ($teams->type === TeamsTypesEnum::PRIVATE_USER_TEAM->value) {
            return $this->sendResponse(['error' => 'This team cannot be edited'], 403, userId: $authUser->id);
        }


        if (!$authUser) {
            return $this->sendResponse(['error' => 'Failed to add new member'], 500, userId: $authUser->id);
        }

        $newMember = TeamsMembers::firstOrCreate(
            [
                'user_id' => $authUser->id,
                'team_id' => $teams->id,
            ],
            [
                'permission_level_id' => TeamRole::MEMBER,
                'revoked' => false,
            ]
        );

        $this->clearTeamMemberCache($teams, $newMember->id);

        if (!$newMember) {
            return $this->sendResponse(['error' => 'Failed to add new member'], 500, userId: $authUser->id);
        }

        $usersIds = $this->synchronizationService->getUsersIdsFromTeam($teams);
        $this->synchronizationService->incrementSyncVersion($usersIds);
        $this->synchronizationService->sendNotification($usersIds);
        return $this->sendResponse([
            'member' => $newMember,
            'team' => $teams
        ], 201, userId: $authUser->id);
    }

    public function updateMember(Request $request, $teamId, $memberId)
    {
        $teams = Teams::find($teamId);
        $authUser = $this->getUserFromToken($request);

        if (!$teams) {
            return $this->sendResponse(['error' => ''], 404, userId: $authUser->id);
        }

        if ($teams->revoked) {
            return $this->sendResponse(['error' => 'This team is revoked'], 404, userId: $authUser->id);
        }

        if ($teams->type === TeamsTypesEnum::PRIVATE_USER_TEAM->value) {
            return $this->sendResponse(['error' => 'This team cannot be edited'], 403, userId: $authUser->id);
        }

        $request->validate([
            'permission_level_id' => 'required|integer|in:' . implode(',', TeamRole::getAllRolesAsStrings()),
        ]);

        $member = TeamsMembers::withoutRevoked()
            ->where('user_id', $memberId)
            ->where('team_id', $teamId)
            ->firstOrFail();

        if ($member->team_id !== $teams->id) {
            return $this->sendResponse(['error' => 'This member does not belong to this team'], 404, userId: $authUser->id);
        }


        if (!$authUser) {
            return $this->sendResponse(['error' => 'Unauthorized'], 401, userId: $authUser->id);
        }

        $teamMember = TeamsMembers::where('user_id', $authUser->id)
            ->where('team_id', $teams->id)
            ->first();

        if (!$teamMember) {
            return $this->sendResponse(['error' => 'You are not a member of this team'], 403, userId: $authUser->id);
        }

        if (!TeamRole::hasHighestRole($teamMember->permission_level_id, $member->permission_level_id)) {
            return $this->sendResponse(['error' => 'You cannot change the role of this member'], 403, userId: $authUser->id);
        }

        $newRole = (int) $request->get('permission_level_id');

        if (
            $member->permission_level_id === TeamRole::OWNER->value &&
            $newRole !== TeamRole::OWNER->value
        ) {
            $otherOwnersCount = TeamsMembers::withoutRevoked()
                ->where('team_id', $teamId)
                ->where('permission_level_id', TeamRole::OWNER->value)
                ->where('user_id', '!=', $memberId)
                ->count();

            if ($otherOwnersCount === 0) {
                return $this->sendResponse(['error' => 'Cannot change the role of the only team owner'], 403, userId: $authUser->id);
            }
        }

        $member->update(['permission_level_id' => $newRole]);

        $this->clearTeamMemberCache($teams, $member);

        $usersIds = $this->synchronizationService->getUsersIdsFromTeam($teams);
        $this->synchronizationService->incrementSyncVersion($usersIds);
        $this->synchronizationService->sendNotification($usersIds);
        return $this->sendResponse([
            'member' => $member,
        ], 200, userId: $authUser->id);
    }


    public function removeMember($teamId, $memberId, Request $request)
    {
        $teams = Teams::find($teamId);
        $authUser = $this->getUserFromToken($request);

        if (!$teams) {
            return $this->sendResponse(['error' => ''], 404, userId: $authUser->id);
        }

        if ($teams->revoked) {
            return $this->sendResponse(['error' => 'This team is revoked'], 404, userId: $authUser->id);
        }

        if ($teams->type === TeamsTypesEnum::PRIVATE_USER_TEAM->value) {
            return $this->sendResponse(['error' => 'This team cannot be edited'], 403, userId: $authUser->id);
        }

        $member = TeamsMembers::withoutRevoked()
            ->where('user_id', $memberId)
            ->where('team_id', $teamId)
            ->firstOrFail();

        if ($member->team_id !== $teams->id) {
            return $this->sendResponse(['error' => 'This member does not belong to this team'], 404, userId: $authUser->id);
        }


        if (!$authUser) {
            return $this->sendResponse(['error' => 'Unauthorized'], 401, userId: $authUser->id);
        }

        $teamMember = TeamsMembers::where('user_id', $authUser->id)
            ->where('team_id', $teams->id)
            ->first() ?? null;

        if (!$teamMember) {
            return $this->sendResponse(['error' => 'You are not a member of this team'], 403, userId: $authUser->id);
        }

        if (
            $teamMember->permission_level_id !== TeamRole::OWNER->value &&
            $teamMember->permission_level_id !== TeamRole::ADMINISTRATOR->value
        ) {
            return $this->sendResponse(['error' => 'Only team owners and administrators can remove members'], 403, userId: $authUser->id);
        }

        if (!TeamRole::hasHighestRole($teamMember->permission_level_id, $member->permission_level_id)) {
            return $this->sendResponse(['error' => 'You cannot remove this member'], 403, userId: $authUser->id);
        }

        $totalItems = TeamsMembers::withoutRevoked()->where('team_id', $teamId)->count();

        if ($totalItems === 1) {
            return $this->sendResponse(["message" => "In team have to be minimal 1 member"], 403, userId: $authUser->id);
        }

        $member->update(['revoked' => true]);
        $this->clearTeamMemberCache($teams, $member);

        $hasOwnerOrAdmin = TeamsMembers::withoutRevoked()
            ->where('team_id', $teamId)
            ->whereIn('permission_level_id', [
                TeamRole::OWNER->value,
                TeamRole::ADMINISTRATOR->value
            ])
            ->exists();

        if (!$hasOwnerOrAdmin) {
            $randomMember = TeamsMembers::withoutRevoked()
                ->where('team_id', $teamId)
                ->inRandomOrder()
                ->first();

            if ($randomMember) {
                $randomMember->update(['permission_level_id' => TeamRole::OWNER->value]);
            }
        }

        $usersIds = $this->synchronizationService->getUsersIdsFromTeam($teams);
        $this->synchronizationService->incrementSyncVersion($usersIds);
        $this->synchronizationService->sendNotification($usersIds);
        return $this->sendResponse(['message' => 'Member removed successfully'], 200, userId: $authUser->id);
    }


    public function getMembers(Request $request, $teamId)
    {
        $page = $request->input('page', 1);
        $perPage = $request->input('per_page', 30);
        $offset = ($page - 1) * $perPage;

        $authUser = $this->getUserFromToken($request);
        if (!$teamId) {
            return $this->sendResponse(['error' => 'Team ID is required'], 400, userId: $authUser->id);
        }

        if (!$authUser) {
            return $this->sendResponse(['error' => 'Unauthorized'], 401, userId: $authUser->id);
        }

        if ($perPage > 100) {
            return $this->sendResponse(['error' => 'Maximum per_page limit is 100'], 400, userId: $authUser->id);
        }

        $members = Cache::remember("teams_members_{$teamId}_page_{$page}_perPage_{$perPage}", 60, function () use ($teamId, $offset, $perPage) {
            return TeamsMembers::withoutRevoked()
                ->where('team_id', $teamId)
                ->orderBy('created_at', 'desc')
                ->offset($offset)
                ->limit($perPage)
                ->with(['user' => function ($query) {
                    $query->select('id', 'name', 'email', 'surname', 'profile_image');
                }])
                ->select('team_id', 'revoked', 'user_id', 'permission_level_id')
                ->get()
                ->map(function ($member) {
                    return [
                        'team_id' => $member->team_id,
                        'permission_level_id' => $member->permission_level_id,
                        'id' => $member->user->id,
                        'name' => $member->user->name,
                        'email' => $member->user->email,
                        'surname' => $member->user->surname,
                        'profile_image' => $member->user->profile_image,
                    ];
                });
        });

        $totalItems = TeamsMembers::withoutRevoked()->where('team_id', $teamId)->count();
        $totalPages = (int) ceil($totalItems / $perPage);

        return $this->sendResponse([
            'members' => $members,
            'total_pages' => $totalPages,
            'current_page' => (int) $page,
            'total_members' => $totalItems,
            'team_id' => (int) $teamId,
        ], 200, userId: $authUser->id);
    }

    public function getMember(Request $request, $teamId)
    {
        $authUser = $this->getUserFromToken($request);
        if (!is_numeric($teamId)) {
            return $this->sendResponse(['error' => 'Invalid ID format'], 400, userId: $authUser->id);
        }

        if (!$teamId) {
            return $this->sendResponse(['error' => 'Team ID is required'], 400, userId: $authUser->id);
        }


        if (!$authUser) {
            return $this->sendResponse(['error' => 'Unauthorized'], 401, userId: $authUser->id);
        }

        $members = TeamsMembers::withoutRevoked()
            ->where('user_id', $authUser->id)
            ->where('team_id', $teamId)
            ->orderBy('created_at', 'desc')
            ->with(['user' => function ($query) {
                $query->select('id', 'name', 'email', 'surname', 'profile_image');
            }])
            ->select('team_id', 'revoked', 'user_id', 'permission_level_id')
            ->get()
            ->map(function ($member) {
                // Sprawdzamy, czy użytkownik istnieje
                $user = $member->user;
                if ($user) {
                    return [
                        'team_id' => $member->team_id,
                        'permission_level_id' => $member->permission_level_id,
                        'id' => $user->id,
                        'name' => $user->name,
                        'email' => $user->email,
                        'surname' => $user->surname,
                        'profile_image' => $user->profile_image,
                    ];
                }
                return null;
            })->filter();

        return $this->sendResponse([
            'member' => $members,
            'team_id' => (int) $teamId,
        ], 200, userId: $authUser->id);
    }
}
