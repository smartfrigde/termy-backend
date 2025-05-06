<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GpgKeys extends Model
{
    protected $table = 'gpg_keys';

    protected $fillable = [
        'private_key',
        'public_key',
        'ssh_connection_id',
        'revoked',
    ];

    public function sshConnection()
    {
        return $this->belongsTo(SshConnections::class, 'ssh_connection_id');
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
