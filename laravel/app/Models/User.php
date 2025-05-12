<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;

use App\TeamRole;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'surname',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    protected static function booted(): void
    {
        parent::booted();

        static::created(function (self $user) {
            $team = Teams::create([
                "name" => "default_team_of{$user->id}",
                "type" => "default_user_team",
            ]);

            TeamsMembers::create([
                "user_id" => $user->id,
                "team_id" => $team->id,
                "permission_level_id" => TeamRole::ADMINISTRATOR->value,
            ]);

            synchronizationVersions::create([
                "user_id" => $user->id,
                "version" => 0,
            ]);
        });
    }

    public function team()
    {
        return $this->hasMany(Teams::class, "user_id", "id");
    }

    public function teamRole()
    {
        return $this->hasMany(TeamsMembers::class, "user_id", "id");
    }

    public function syncVersion()
    {
        return $this->hasMany(synchronizationVersions::class, "user_id", "id");
    }

    public function getDefaultTeamAttribute()
    {
        return $this->team()->where("type", "default_user_team")->first();
    }

    public function isTeamMember($teamId)
    {
        return $this->teamRole()->where("team_id", $teamId)->exists();
    }
}
