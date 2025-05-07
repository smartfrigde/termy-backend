<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class sshConnections extends Model
{
    protected $table = 'ssh_connections';

    protected $fillable = [
        'login',
        'hostname',
        'port',
        'name',
        'password',
        'team_id',
        'revoked',
    ];

    public function team()
    {
        return $this->belongsTo(Teams::class, 'team_id');
    }

    public function gpgKey()
    {
        return $this->hasOne(GpgKeys::class, 'ssh_connection_id');
    }
    public function scopeRevoked($query)
    {
        return $query->where('revoked', true);
    }
    public function scopeWithoutRevoked($query)
    {
        return $query->where('revoked', false);
    }

    public function onlyRevoked()
    {
        return $this->where('revoked', true);
    }
}
