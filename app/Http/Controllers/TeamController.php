<?php

namespace App\Http\Controllers;

use App\Models\Teams;
use App\Models\TeamsMembers;
use App\TeamRole;
use App\TeamsTypesEnum;
use Illuminate\Support\Facades\Cache;
use Illuminate\Http\Request;

class TeamController extends Controller
{

    private function clearCache($userId, $team)
    {
        Cache::forget("teams_{$userId}_count");

        $page = $this->calculatePageForTeam($team->id, $userId);
        $cacheKey = "teams_{$userId}_page_{$page}_perPage_30";

        $teams = Cache::get($cacheKey) ?? collect();
        $teams->push($team);

        Cache::put($cacheKey, $teams, 60);
    }


    private function calculatePageForTeam($teamId, $authUserId, $perPage = 30)
    {
        $teams = Teams::byMemberId($authUserId)->withoutRevoked()->orderBy('created_at', 'desc')->pluck("id")->toArray();

        $teamIndex = array_search($teamId, $teams);

        if ($teamIndex === false) {
            return null;
        }

        $page = (int) ceil(($teamIndex + 1) / $perPage);

        return $page;
    }


    public function index(Request $request)
    {
        $page = $request->input('page', 1);
        $perPage = $request->input('per_page', 30);
        $offset = ($page - 1) * $perPage;
        $authUser = $this->getUserFromToken($request);

        if (!$authUser) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        if ($perPage > 100) {
            return response()->json(['error' => 'Maximum per_page limit is 100'], 400);
        }

        $teams = Cache::remember("teams_{$authUser->id}_page_{$page}_perPage_{$perPage}", 60, function () use ($authUser, $offset, $perPage) {
            return Teams::byMemberId($authUser->id)->withoutRevoked()
            ->orderBy('created_at', 'desc')
            ->offset($offset)
            ->limit($perPage)
            ->get();
        });

        $totalItems = Teams::withoutRevoked()->byMemberId($authUser->id)->count();
        $totalPages = (int) ceil($totalItems / $perPage);

        return response()->json([
            'teams' => $teams,
            'total_pages' => $totalPages,
            'current_page' => $page,
        ], 200);
    }


    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
        ]);


        $authUser = $this->getUserFromToken($request);

        if (!$authUser) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $team = Teams::create([
            'name' => $request->name,
        ]);

        if (!$team) {
            return response()->json(['error' => 'Failed to create team'], 500);
        }

        $teamOwner = TeamsMembers::create([
            'user_id' => $authUser->id,
            'team_id' => $team->id,
            'permission_level_id' => TeamRole::OWNER,
        ]);

        if (!$teamOwner) {
            $team->delete();
            return response()->json(['error' => 'Failed to assign team owner'], 500);
        }

        $this->clearCache($authUser->id, $team);

        return response()->json([
            'team' => $team,
        ], 201);
    }


    public function show(Teams $teams, Request $request)
    {
        $authUser = $this->getUserFromToken($request);

        if (!$authUser) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $teamMember = TeamsMembers::WithoutRevoked()->where('user_id', $authUser->id)
        ->where('team_id', $teams->id)
        ->first() ?? null;

        if (!$teamMember) {
            return response()->json(['error' => 'You are not a member of this team'], 403);
        }

        $team = Cache::remember("team_{$teams->id}_{$authUser->id}", 30, function () use ($teams) {
            return Teams::withoutRevoked()->find($teams->id)->load(['members.user']);
        });

        if (!$team) {
            return response()->json(['error' => 'Team not found'], 404);
        }

        return response()->json([
            'team' => $team,
        ], 200);
    }


    public function update(Request $request, $teamId)
    {

        $teams = Teams::withoutRevoked()->find($teamId);

        if (!$teams){
            return response()->json(['error'=> ''], 404);
        }

        if ($teams->revoked) {
            return response()->json(['error' => 'This team is revoked'], 403);
        }

        if ($teams->type === TeamsTypesEnum::PRIVATE_USER_TEAM->value) {
            return response()->json(['error' => 'This team cannot be edited'], 403);
        }

        $request->validate([
            'name' => 'required|string|max:255',
        ]);

        $authUser = $this->getUserFromToken($request);

        if (!$authUser) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $teamMember = TeamsMembers::where('user_id', $authUser->id)
        ->where('team_id', $teams->id)
        ->first() ?: null;

        if (!$teamMember) {
            return response()->json(['error' => 'You are not a member of this team'], 403);
        }

        if ($teamMember->permission_level_id !== TeamRole::OWNER->value && $teamMember->permission_level_id !== TeamRole::ADMINISTRATOR->value) {
            return response()->json(['error' => 'Only team owners and administrators can update the team'], 403);
        }

        $teams->update($request->only('name'));

        $this->clearCache($authUser->id, $teams);

        return response()->json([
            'team' => $teams,
        ], 200);
    }


    public function destroy($teamId, Request $request)
    {
        $teams = Teams::withoutRevoked()->find($teamId);

        if (!$teams){
            return response()->json(['error'=> ''], 404);
        }

        if ($teams->revoked) {
            return response()->json(['error' => 'This team is revoked'], 403);
        }

        if ($teams->type === TeamsTypesEnum::PRIVATE_USER_TEAM->value) {
            return response()->json(['error' => 'This team cannot be edited'], 403);
        }

        $authUser = $this->getUserFromToken($request);

        if (!$authUser) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $teamMember = TeamsMembers::where('user_id', $authUser->id)
        ->where('team_id', $teams->id)
        ->first() ?? null;

        if (!$teamMember) {
            return response()->json(['error' => 'You are not a member of this team'], 403);
        }

        if ($teamMember->permission_level_id !== TeamRole::OWNER->value) {
            return response()->json(['error' => 'Only team owners can delete the team'], 403);
        }

        $teams->update(['revoked' => true]);

        return response()->json(['message' => 'Team deleted successfully'], 200);
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

    public function addMember(Request $request, $teamId)
    {
        $teams = Teams::find($teamId);

        if (!$teams){
            return response()->json(['error'=> ''], 404);
        }

        if ($teams->revoked) {
            return response()->json(['error' => 'This team is revoked'], 404);
        }

        if ($teams->type === TeamsTypesEnum::PRIVATE_USER_TEAM->value) {
            return response()->json(['error' => 'This team cannot be edited'], 403);
        }

        $request->validate([
            'user_id' => 'required|exists:users,id',
        ]);

        $authUser = $this->getUserFromToken($request);

        if (!$authUser) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $teamMember = TeamsMembers::where('user_id', $authUser->id)
            ->where('team_id', $teams->id)
            ->first() ?? null;

        if (!$teamMember) {
            return response()->json(['error' => 'You are not a member of this team'], 403);
        }

        if ($teamMember->permission_level_id !== TeamRole::OWNER->value && $teamMember->permission_level_id !== TeamRole::ADMINISTRATOR->value ) {
            return response()->json(['error' => 'Only team owners can add members to the team'], 403);
        }

        $newMember = TeamsMembers::updateOrCreate([
            'user_id' => $request->user_id,
            'team_id' => $teams->id,
        ],
        [
            'permission_level_id' => TeamRole::MEMBER,
            'revoked' => false,
        ]);

        $this->clearTeamMemberCache($teams, $newMember->id);

        if (!$newMember) {
            return response()->json(['error' => 'Failed to add new member'], 500);
        }

        return response()->json([
            'member' => $newMember,
        ], 201);
    }

    public function updateMember(Request $request, $teamId, $memberId)
    {

        $teams = Teams::find($teamId);

        if (!$teams){
            return response()->json(['error'=> ''], 404);
        }

        if ($teams->revoked) {
            return response()->json(['error' => 'This team is revoked'], 404);
        }

        if ($teams->type === TeamsTypesEnum::PRIVATE_USER_TEAM->value) {
            return response()->json(['error' => 'This team cannot be edited'], 403);
        }

        $member = TeamsMembers::withoutRevoked()->where('user_id', $memberId)->where('team_id', $teamId)->firstOrFail();

        if ($member->team_id !== $teams->id) {
            return response()->json(['error' => 'This member does not belong to this team'], 404);
        }

        $request->validate([
            'permission_level_id' => 'required|integer|in:' . implode(',', TeamRole::getAllRolesAsStrings()),
        ]);

        $authUser = $this->getUserFromToken($request);

        if (!$authUser) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $teamMember = TeamsMembers::where('user_id', $authUser->id)
            ->where('team_id', $teams->id)
            ->first() ?? null;

        if (!$teamMember) {
            return response()->json(['error' => 'You are not a member of this team'], 403);
        }

        if (TeamRole::hasHighestRole($teamMember->permission_level_id, $member->permission_level_id)) {
            return response()->json(['error' => 'You cannot change the role of this member'], 403);
        }

        if (TeamRole::hasHighestRole((int) $teamMember->permission_level_id, (int) $request->input('permission_level_id'))) {
            return response()->json(['error' => 'You cannot change the role of this member'], 403);
        }

        $member->update($request->only('permission_level_id'));

        $this->clearTeamMemberCache($teams, $member);

        return response()->json([
            'member' => $member,
        ], 200);
    }

    public function removeMember($teamId, $memberId, Request $request)
    {

        $teams = Teams::find($teamId);

        if (!$teams){
            return response()->json(['error'=> ''], 404);
        }

        if ($teams->revoked) {
            return response()->json(['error' => 'This team is revoked'], 404);
        }

        if ($teams->type === TeamsTypesEnum::PRIVATE_USER_TEAM->value) {
            return response()->json(['error' => 'This team cannot be edited'], 403);
        }

        $member = TeamsMembers::withoutRevoked()->where('user_id', $memberId)->where('team_id', $teamId)->firstOrFail();

        if ($member->team_id !== $teams->id) {
            return response()->json(['error' => 'This member does not belong to this team'], 404);
        }

        $authUser = $this->getUserFromToken($request);

        if (!$authUser) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $teamMember = TeamsMembers::where('user_id', $authUser->id)
            ->where('team_id', $teams->id)
            ->first() ?? null;

        if (!$teamMember) {
            return response()->json(['error' => 'You are not a member of this team'], 403);
        }

        if ($teamMember->permission_level_id !== TeamRole::OWNER->value && $teamMember->permission_level_id !== TeamRole::ADMINISTRATOR->value ) {
            return response()->json(['error' => 'Only team owners and administrators can remove members'], 403);
        }

        if (TeamRole::hasHighestRole($teamMember->permission_level_id, $member->permission_level_id)) {
            return response()->json(['error' => 'You cannot remove this member'], 403);
        }

        $member->update(['revoked' => true]);
        $this->clearTeamMemberCache($teams, $member);

        return response()->json(['message' => 'Member removed successfully'], 200);
    }

    public function getMembers(Request $request)
    {
        $page = $request->input('page', 1);
        $perPage = $request->input('per_page', 30);
        $offset = ($page - 1) * $perPage;
        $teamId = $request->input('team_id', null);

        if (!$teamId) {
            return response()->json(['error' => 'Team ID is required'], 400);
        }

        $authUser = $this->getUserFromToken($request);

        if (!$authUser) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        if ($perPage > 100) {
            return response()->json(['error' => 'Maximum per_page limit is 100'], 400);
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
                ->get();
        });

        $totalItems = TeamsMembers::withoutRevoked()->where('team_id', $teamId)->count();
        $totalPages = (int) ceil($totalItems / $perPage);

        return response()->json([
            'members' => $members,
            'total_pages' => $totalPages,
            'current_page' => $page,
        ], 200);
    }
}
