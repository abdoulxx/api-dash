<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class BonProvisoireSg extends Model
{
    use HasFactory, SoftDeletes, HasUlids;

    protected $table = 'bon_provisoire_sg';

    protected $fillable = [
        'instance_id',
        'annee',
        'bureau',
        'serie_bp',
        'num_serie_bp',
        'numero_bon_provisoire',
        'date_bp',
        'num_vol_lta',
        'num_lta',
        'date_lta',
        'date_expiration',
        'delai_jours',
        'ncc',
        'nom_importateur',
        'nom_fournisseur',
        'pays_origine',
        'code_declarant',
        'nom_declarant',
        'type_bon_provisoire',
    ];

    protected $casts = [
        'date_bp' => 'datetime',
        'date_lta' => 'datetime',
        'date_expiration' => 'datetime',
    ];

    /**
     * Relation avec les articles du bon provisoire
     */
    public function articles()
    {
        return $this->hasMany(BonProvisoireArticle::class, 'instance_id', 'instance_id');
    }
}

