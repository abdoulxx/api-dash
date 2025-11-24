<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class S360v02User extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 's360v02_users';

    protected $fillable = [
        'old_id',
        'username',
        'password',
        'email',
        'fullname',
        'groupid',
        'active',
        'ext_security_id',
    ];

    protected $casts = [
        'old_id' => 'decimal:4',
        'active' => 'decimal:4',
    ];

    protected $hidden = [
        'password',
    ];

    /**
     * Relation avec les groupes (via ugmembers)
     */
    public function groups()
    {
        return $this->hasMany(S360v02Ugmember::class, 'username', 'username');
    }

    /**
     * Relation avec les droits
     */
    public function rights()
    {
        return $this->hasManyThrough(
            S360v02Ugright::class,
            S360v02Ugmember::class,
            'username',
            'groupid',
            'username',
            'groupid'
        );
    }
}

