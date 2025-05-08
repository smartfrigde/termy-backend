<?php

namespace App\Models;

use App\TeamsTypesEnum;
use Illuminate\Database\Eloquent\Model;

class Teams extends Model
{
    protected $table = "teams";
    protected $fillable = [
        "user_id",
        "name",
        "type",
        "revoked",
    ];
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function scopeWithoutRevoked($query)
    {
        return $query->where('revoked', false);
    }

    public function scopeByMemberId($query, $userId)
    {
        return $query->whereHas('members', function ($subQuery) use ($userId) {
            $subQuery->where('user_id', $userId);
        });
    }

    public function members()
    {
        return $this->hasMany(TeamsMembers::class, 'team_id', 'id');
    }

    public function scopeWithoutDefaultTeam($query){
        return $query->where('type', "!=", TeamsTypesEnum::PRIVATE_USER_TEAM);
    }
}
