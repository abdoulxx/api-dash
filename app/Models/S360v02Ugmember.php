<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class S360v02Ugmember extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 's360v02_ugmembers';

    protected $fillable = [
        'username',
        'groupid',
    ];

    protected $casts = [
        'groupid' => 'decimal:4',
    ];

    /**
     * Relation avec l'utilisateur
     */
    public function user()
    {
        return $this->belongsTo(S360v02User::class, 'username', 'username');
    }

    /**
     * Relation avec le groupe
     */
    public function group()
    {
        return $this->belongsTo(S360v02Uggroup::class, 'groupid', 'groupid');
    }
}

