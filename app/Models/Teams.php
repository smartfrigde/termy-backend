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

    public function withoutRevoked()
    {
        return $this->where('revoked', false);
    }
}
