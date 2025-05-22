<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GpgKeys extends Model
{
    protected $table = 'gpg_keys';

    protected $fillable = [
        'user_id',
        'private_key',
        'public_key',
        'name',
        'password',
        'revoked',
    ];

    public function user(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function sshConnection(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(SshConnections::class);
    }
    public function scopeRevoked($query)
    {
        return $query->where('revoked', true);
    }
    public function scopeNotRevoked($query)
    {
        return $query->where('revoked', false);
    }
}
