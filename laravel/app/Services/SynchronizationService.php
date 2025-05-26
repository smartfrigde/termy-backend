<?php

namespace App\Services;

use App\Events\SyncNots;
use App\Models\synchronizationVersions;
use App\Models\Teams;

class SynchronizationService
{
    public function incrementSyncVersion(array $usersIds): array
    {
        synchronizationVersions::whereIn("user_id", $usersIds)->increment('version');

        return synchronizationVersions::whereIn("user_id", $usersIds)
            ->get(['user_id', 'version'])
            ->map(fn($row) => [
                'user_id' => $row->user_id,
                'syncVersion' => $row->version,
            ])
            ->toArray();
    }


    public function decrementSyncVersion(array $usersIds): void
    {
        synchronizationVersions::whereIn('user_id', $usersIds)->decrement('version');
    }

    public function getUsersIdsFromTeam(Teams $team): array
    {
        if (!$team) {
            return [];
        }

        $usersIds = [];

        foreach ($team->members as $member) {
            $usersIds[] = $member->user_id;
        }

        return $usersIds;
    }

    public function sendNotification(array $usersData, string $categoryOfData, string $otherData = ""): void
    {
        if (empty($usersData)) {
            return;
        }

        foreach ($usersData as $userData) {
            $payload = [
                'new_synchronization_version' => $userData['syncVersion'],
                'type' => 'report_new_sync_version',
                'content' => 'User has new synchronization version',
                'recipient' => $userData['user_id'],
                "category" => $categoryOfData,
                $otherData,
            ];

            event(new SyncNots($userData['user_id'], json_encode($payload)));
        }
    }


    public function checkSyncVersion($syncVersion, $userId)
    {
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
