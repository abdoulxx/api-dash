<?php

namespace App\Models;

use App\Models\Concerns\HasPublicUlid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class DeclarationSg extends Model
{
    use HasFactory, SoftDeletes, HasPublicUlid;

    protected $table = 'declaration_sg';

    protected $fillable = [
        'instanceid',
        'entrepot',
        'annee',
        'num_manifeste',
        'num_fdi',
        'num_bl',
        'typdec',
        'sens',
        'num_dossier',
        'declaration',
        'date_declaration',
        'provenance',
        'destination',
        'code_port_chargement',
        'nom_port_chargement',
        'code_mode_transport',
        'nom_mode_transport',
        'nom_navire',
        'condition_liv',
        'devise',
        'taux_conversion',
        'banq_code',
        'bureau',
        'nom_bureau',
        'cc_exp',
        'exportateur',
        'cc_imp',
        'importateur',
        'code_destinataire_reel',
        'nom_destinataire_reel',
        'codagr',
        'declarant',
        'sous_regime',
        'nbre_total_article',
        'quittance',
        'date_quittance',
        'valeur_caf_declaration',
        'nbre_colis',
        'valeur_fob_declaration',
        'droits_taxes_declaration',
        'poids_brut_declaration',
        'nombre_conteneur',
        'ulid',
    ];

    protected $casts = [
        'date_declaration' => 'datetime',
        'date_quittance' => 'datetime',
        'taux_conversion' => 'decimal:4',
        'valeur_caf_declaration' => 'decimal:4',
        'nbre_colis' => 'decimal:4',
        'valeur_fob_declaration' => 'decimal:4',
        'droits_taxes_declaration' => 'decimal:4',
        'poids_brut_declaration' => 'decimal:4',
        'nombre_conteneur' => 'decimal:4',
        'ulid' => 'string',
    ];

    /**
     * Relation avec le manifeste
     */
    public function manifeste()
    {
        return $this->belongsTo(ManifesteSg::class, 'num_manifeste', 'num_manifeste');
    }

    /**
     * Relation avec les articles de la déclaration
     */
    public function articles()
    {
        return $this->hasMany(DeclarationArticle::class, 'declaration', 'declaration');
    }

    /**
     * Relation avec la FDI associée
     */
    public function fdi()
    {
        return $this->belongsTo(FdiSg::class, 'num_fdi', 'numero_fdi');
    }

    /**
     * Relation avec la FCVR associée
     */
    public function fcvr()
    {
        return $this->belongsTo(FcvrSg::class, 'declaration', 'num_declaration');
    }
}

