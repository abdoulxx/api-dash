<?php

namespace App\Models;

use App\Models\Concerns\HasPublicUlid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class FcvrSg extends Model
{
    use HasFactory, SoftDeletes, HasPublicUlid;

    protected $table = 'fcvr_sg';

    protected $appends = ['numero_fcvr_complet', 'identifiant'];

    protected $fillable = [
        'instanceid',
        'annee',
        'num_tt',
        'bureau',
        'num_fdi',
        'date_fdi',
        'num_voyage',
        'nom_navire',
        'num_bl',
        'date_arrivee',
        'nombre_conteneur',
        'poids_net_total',
        'poids_brut_total',
        'nombre_total_colis',
        'nbre_total_colis',
        'lieu_chrg',
        'lieu_dechrg',
        'num_rfcv',
        'date_rfcv',
        'derniere_operation',
        'date_derniere_operation',
        'cc',
        'nom_importateur',
        'pays_importateur',
        'code_pays',
        'nom_pays_importateur',
        'nom_fournisseur',
        'pays_fournisseur',
        'code_declarant',
        'nom_declarant',
        'code_pays_origine',
        'nom_pays_origine',
        'nombre_total_article',
        'numero_facture',
        'date_facture',
        'val_fact_rfcv_devise',
        'val_fact_rfcv_cfa',
        'observation',
        'incoterm',
        'devise',
        'taux_devise',
        'fob_rfcv',
        'fob_rfcv_cfa',
        'fret_rfcv',
        'fret_rfcv_cfa',
        'assurance_rfcv',
        'assurance_rfcv_cfa',
        'autres_couts_rfcv',
        'autres_rfcv_cfa',
        'caf_rfcv',
        'caf_rfcv_cfa',
        'num_declaration',
        'date_declaration',
        'ulid',
    ];

    protected $casts = [
        'ulid' => 'string',
        'date_fdi' => 'datetime',
        'date_arrivee' => 'datetime',
        'date_rfcv' => 'datetime',
        'date_derniere_operation' => 'datetime',
        'date_facture' => 'datetime',
        'date_declaration' => 'datetime',
        'poids_net_total' => 'decimal:4',
        'poids_brut_total' => 'decimal:4',
        'val_fact_rfcv_devise' => 'decimal:4',
        'val_fact_rfcv_cfa' => 'decimal:4',
        'taux_devise' => 'decimal:4',
        'fob_rfcv' => 'decimal:4',
        'fob_rfcv_cfa' => 'decimal:4',
        'fret_rfcv' => 'decimal:4',
        'fret_rfcv_cfa' => 'decimal:4',
        'assurance_rfcv' => 'decimal:4',
        'assurance_rfcv_cfa' => 'decimal:4',
        'autres_couts_rfcv' => 'decimal:4',
        'autres_rfcv_cfa' => 'decimal:4',
        'caf_rfcv' => 'decimal:4',
        'caf_rfcv_cfa' => 'decimal:4',
    ];

    /**
     * Relation avec les articles de la FCVR
     */
    public function articles()
    {
        return $this->hasMany(FcvrArticle::class, 'instanceid', 'instanceid');
    }

    /**
     * Relation avec la première comparaison
     */
    public function comparaison1()
    {
        return $this->hasOne(FcvrSgComp1::class, 'instanceid', 'instanceid');
    }

    /**
     * Relation avec la deuxième comparaison
     */
    public function comparaison2()
    {
        return $this->hasOne(FcvrSgComp2::class, 'instanceid', 'instanceid');
    }

    /**
     * Relation avec la FDI associée
     */
    public function fdi()
    {
        return $this->belongsTo(FdiSg::class, 'num_fdi', 'numero_fdi');
    }

    /**
     * Relation avec la déclaration associée
     */
    public function declaration()
    {
        return $this->belongsTo(DeclarationSg::class, 'num_declaration', 'declaration');
    }

    /**
     * Numéro FCVR complet (ANNEE + BUREAU + 'C' + séquence)
     */
    public function getNumeroFcvrCompletAttribute(): ?string
    {
        if (!$this->annee || !$this->bureau) {
            return $this->num_rfcv;
        }

        $sequence = $this->num_rfcv ?? $this->attributes['num_rfcv'] ?? $this->id ?? $this->instanceid;
        if ($sequence === null) {
            return $this->num_rfcv;
        }

        return sprintf('%s%sC%s', $this->annee, $this->bureau, ltrim((string) $sequence));
    }

    public function getIdentifiantAttribute(): string
    {
        return $this->numero_fcvr_complet ?? $this->num_rfcv ?? ("FCVR #{$this->id}");
    }
}

