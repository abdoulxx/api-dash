<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class DeclarationArticle extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'declaration_article';

    protected $fillable = [
        'instanceid',
        'entrepot',
        'annee',
        'num_manifeste',
        'num_bl',
        'typdec',
        'sens',
        'num_dossier',
        'declaration',
        'date_declaration',
        'provenance',
        'origine',
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
        'postar',
        'libelle_postar',
        'marque1',
        'marque2',
        'emballage',
        'unite_apurement',
        'nbre_colis',
        'nbre_total_article',
        'sous_regime',
        'numero_article',
        'quittance',
        'date_quittance',
        'valcaf',
        'valfob',
        'valdou',
        'valtax',
        'valfret_ext',
        'valfret_int',
        'valass',
        'autre_cout',
        'droits_taxes',
        'taxe_susp',
        'poids_net',
        'poids_brut',
        'credit_paiement',
        'mode_paiement',
        'date_annulation',
    ];

    protected $casts = [
        'date_declaration' => 'datetime',
        'date_quittance' => 'datetime',
        'date_annulation' => 'datetime',
        'taux_conversion' => 'decimal:4',
        'nbre_colis' => 'decimal:4',
        'valcaf' => 'decimal:4',
        'valfob' => 'decimal:4',
        'valdou' => 'decimal:4',
        'valtax' => 'decimal:4',
        'valfret_ext' => 'decimal:4',
        'valfret_int' => 'decimal:4',
        'valass' => 'decimal:4',
        'autre_cout' => 'decimal:4',
        'droits_taxes' => 'decimal:4',
        'taxe_susp' => 'decimal:4',
        'poids_net' => 'decimal:4',
        'poids_brut' => 'decimal:4',
    ];

    /**
     * Relation avec la déclaration principale
     */
    public function declaration()
    {
        return $this->belongsTo(DeclarationSg::class, 'declaration', 'declaration');
    }
}

