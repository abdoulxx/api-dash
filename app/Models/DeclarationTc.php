<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class DeclarationTc extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'declaration_tc';

    protected $fillable = [
        'instanceid',
        'annee',
        'num_manifeste',
        'num_bl',
        'numenr',
        'numero_conteneur',
        'taille_conteneur',
    ];

    protected $casts = [
        'annee' => 'string',
    ];

    /**
     * Relation avec la déclaration principale
     */
    public function declaration()
    {
        return $this->belongsTo(DeclarationSg::class, 'num_manifeste', 'num_manifeste');
    }
}

