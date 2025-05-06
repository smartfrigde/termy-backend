<?php

namespace App\Models;

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

    public function scopeWithoutRevoked()
    {
        return $this->where('revoked', false);
    }

    public function scopeByMemberId($userId)
    {
        return $this->whereHas('members', function ($query) use ($userId) {
            $query->where('user_id', $userId);
        });
    }

    public function members()
    {
        return $this->hasMany(TeamsMembers::class, 'team_id', 'id');
    }
}
