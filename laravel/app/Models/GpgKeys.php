<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GpgKeys extends Model
{
    protected $table = 'gpg_keys';

    protected $fillable = [
        'private_key',
        'public_key',
        'name',
        'password',
        'revoked',
    ];

    public function sshConnection()
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
