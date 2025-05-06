<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TeamsMembers extends Model
{
    protected $table = "teams_members";
    protected $fillable = [
        "user_id",
        "team_id",
        "permission_level_id",
        "revoked",
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function team()
    {
        return $this->belongsTo(Teams::class);
    }
    public function scopeWithoutRevoked()
    {
        return $this->where('revoked', false);
    }
    public function scopeOfTeam($query, $teamId)
    {
        return $query->where('team_id', $teamId);
    }
    public function scopeOfUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }
    public function scopeOfRole($query, $role)
    {
        return $query->where('permission_level_id', $role);
    }
}
