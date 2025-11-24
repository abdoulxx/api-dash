<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class BonProvisoireArticle extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'bon_provisoire_article';

    protected $fillable = [
        'instance_id',
        'annee',
        'bureau',
        'serie_bp',
        'num_serie_bp',
        'numero_bon_provisoire',
        'date_bp',
        'date_expiration',
        'delai_jours',
        'num_vol_lta',
        'num_lta',
        'date_lta',
        'ncc',
        'nom_importateur',
        'nom_fournisseur',
        'pays_origine',
        'code_declarant',
        'nom_declarant',
        'type_bon_provisoire',
        'postar',
        'libelle_marchandise',
        'poids_net_kgs',
        'code_devise',
        'montant_devise',
        'valeur_fob_article',
        'valeur_fob_cfa',
        'valeur_fret_article',
        'valeur_fret_article_cfa',
        'num_declaration',
        'datenr',
        'nbre_colis',
    ];

    protected $casts = [
        'date_bp' => 'datetime',
        'date_expiration' => 'datetime',
        'date_lta' => 'datetime',
        'datenr' => 'datetime',
        'poids_net_kgs' => 'decimal:4',
        'montant_devise' => 'decimal:4',
        'valeur_fob_article' => 'decimal:4',
        'valeur_fob_cfa' => 'decimal:4',
        'valeur_fret_article' => 'decimal:4',
        'valeur_fret_article_cfa' => 'decimal:4',
        'nbre_colis' => 'decimal:4',
    ];

    /**
     * Relation avec le bon provisoire principal
     */
    public function bonProvisoire()
    {
        return $this->belongsTo(BonProvisoireSg::class, 'instance_id', 'instance_id');
    }
}

