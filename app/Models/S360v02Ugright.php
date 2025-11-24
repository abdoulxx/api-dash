<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class S360v02Ugright extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 's360v02_ugrights';

    protected $fillable = [
        'tablename',
        'groupid',
        'accessmask',
        'page',
    ];

    protected $casts = [
        'groupid' => 'decimal:4',
    ];

    /**
     * Relation avec le groupe
     */
    public function group()
    {
        return $this->belongsTo(S360v02Uggroup::class, 'groupid', 'groupid');
    }
}

