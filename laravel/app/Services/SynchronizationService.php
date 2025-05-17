<?php

namespace App\Services;

use App\Models\synchronizationVersions;
use App\Models\Teams;

class SynchronizationService
{
    public function incrementSynchVersion(array $usersIds){
        synchronizationVersions::whereIn("user_id", $usersIds)->increment('version');
    }

    public function decrementSynchVersion(array $usersIds){
        synchronizationVersions::whereIn('user_id', $usersIds)->decrement('version');
    }

    public function getUserIdsFromTeam(Teams $team){
        if ($team == null){
            return [];
        }
        
        $usersIds = [];

        foreach ($team->user as $user){
            $usersIds[] = $user->id;
        }

        return $usersIds;
    }
}
