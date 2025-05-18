<?php

namespace App\Services;

use App\Models\synchronizationVersions;
use App\Models\Teams;

class SynchronizationService
{
    public function incrementSyncVersion(array $usersIds): void
    {
        synchronizationVersions::whereIn("user_id", $usersIds)->increment('version');

        $usersAndSyncVersions = SynchronizationVersions::whereIn('user_id', $usersIds)->get('version', 'user_id');
    }

    public function decrementSyncVersion(array $usersIds): void
    {
        synchronizationVersions::whereIn('user_id', $usersIds)->decrement('version');
    }

    public function getUsersIdsFromTeam(Teams $team): array
    {
        if (!$team){
            return [];
        }

        $usersIds = [];

        foreach ($team->user as $user){
            $usersIds[] = $user->id;
        }

        return $usersIds;
    }

    public function sendNotification(array $usersIds, array $data){

    }
}
