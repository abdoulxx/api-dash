<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class S360v02Uggroup extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 's360v02_uggroups';

    protected $fillable = [
        'groupid',
        'label',
    ];

    protected $casts = [
        'groupid' => 'decimal:4',
    ];

    /**
     * Relation avec les membres du groupe
     */
    public function members()
    {
        return $this->hasMany(S360v02Ugmember::class, 'groupid', 'groupid');
    }

    /**
     * Relation avec les droits du groupe
     */
    public function rights()
    {
        return $this->hasMany(S360v02Ugright::class, 'groupid', 'groupid');
    }
}

