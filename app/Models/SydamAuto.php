<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUlids;

class SydamAuto extends Model
{
    use HasFactory, HasUlids;

    protected $table = 'SYDAMAUTO';

    protected $primaryKey = 'NUM_SYDAMAUTO';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'NUMERO_BL',
        'NUM_MANIFESTE',
        'MANIFESTE',
        'NUM_SYDAMAUTO',
        'DATE_CIVIO',
        'IMPORTATEUR',
        'DECLARATION',
        'DATE_DECLARATION',
        'MARQUE',
        'MODELE',
        'TRANSMISSION',
        'CHASSIS',
        'IMMATRICULATION',
        'ANNEE',
        'BUREAU_MANIFESTE',
        'ANNEE_MANIFESTE',
        'NCC',
        'CODAGR',
        'DECLARANT',
    ];

    protected function casts(): array
    {
        return [
            'DATE_CIVIO' => 'date',
            'DATE_DECLARATION' => 'date',
            'NUM_MANIFESTE' => 'integer',
            'ANNEE' => 'integer',
            'ANNEE_MANIFESTE' => 'integer',
        ];
    }
}

