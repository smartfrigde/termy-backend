<?php

namespace App\Models;

use App\TeamsTypesEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Teams extends Model
{

    /**
     * @property mixed $user
     **/

    protected $table = "teams";
    protected $fillable = [
        "user_id",
        "name",
        "type",
        "revoked",
        "join_code"
    ];
    public function user(): \Illuminate\Database\Eloquent\Relations\BelongsTo
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
            $subQuery->where('user_id', $userId)
                ->where('revoked', false);
        });
    }

    public function members()
    {
        return $this->hasMany(TeamsMembers::class, 'team_id', 'id');
    }

    public function scopeWithoutDefaultTeam($query)
    {
        return $query->where('type', "!=", TeamsTypesEnum::PRIVATE_USER_TEAM);
    }

    public function scopeByJoinCode($query, $joinCode)
    {
        return $query->where("join_code", $joinCode);
    }

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($team) {
            do {
                $code = strtoupper(Str::random(7));
            } while (self::where('join_code', $code)->exists());

            $team->join_code = $code;
        });
    }
}
