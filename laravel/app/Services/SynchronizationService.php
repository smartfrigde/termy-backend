<?php

namespace App\Services;

use App\Events\SyncNots;
use App\Models\synchronizationVersions;
use App\Models\Teams;

class SynchronizationService
{
    public function incrementSyncVersion(array $usersIds): void
    {
        synchronizationVersions::whereIn("user_id", $usersIds)->increment('version');
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

        foreach ($team->members as $member){
            $usersIds[] = $member->user_id;
        }

        return $usersIds;
    }

    public function sendNotification(array $usersIds): void{

        
        if (empty($usersIds)) {
            return;
        }

        foreach ($usersIds as $userId){
            event(new SyncNots($userId, "{ 'type': 'report_new_sync_version', 'content': 'user has new synchronization version', 'recipient': '{$userId}' }"));
        }
    }

    public function checkSyncVersion($syncVersion, $userId){
        if (empty($syncVersion)) {
            return false;
        }

        if (empty($userId)) {
            return false;
        }

        $syncVersionInDb = synchronizationVersions::where('user_id', $userId)->get("version");

        return $syncVersionInDb->version === $syncVersion;
    }
}