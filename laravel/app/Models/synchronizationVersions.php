<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class synchronizationVersions extends Model
{
    protected $table = "synchronization_versions";
    protected $fillable = [
        'version',
        'user_id',
        'created_at',
        'updated_at',
    ];
}
