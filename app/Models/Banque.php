<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\SoftDeletes;

class Banque extends Model
{
    use HasFactory, HasUlids, SoftDeletes;

    protected $table = 'BANQUE';

    protected $primaryKey = 'ID';
    public $incrementing = false;
    protected $keyType = 'string';
    
    public $timestamps = false;
    
    const DELETED_AT = 'deleted_at';

    protected $fillable = [
        'ID',
        'ANNEE_DVT',
        'NUM_DVT',
        'DATE_DVT',
        'MONT_AC_XOF',
        'MONT_FACT_XOF',
        'REF_DDU',
        'CDA_AC',
    ];

    protected function casts(): array
    {
        return [
            'DATE_DVT' => 'datetime',
            'ANNEE_DVT' => 'integer',
            'MONT_AC_XOF' => 'decimal:4',
            'MONT_FACT_XOF' => 'decimal:4',
        ];
    }
}









