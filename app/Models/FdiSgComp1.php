<?php

namespace App\Models;

use App\Models\Concerns\HasPublicUlid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class FdiSgComp1 extends Model
{
    use HasFactory, SoftDeletes, HasPublicUlid;

    protected $table = 'fdi_sg_comp_1';

    protected $fillable = [
        'instance_id',
        'serie_fdi',
        'bureau',
        'annee',
        'numero_serie',
        'numero_fdi',
        'date_fdi',
        'derniere_operation',
        'date_derniere_operation',
        'reglement',
        'banque',
        'ref_domiciliation',
        'date_domiciliation',
        'montant_domicilie_cfa',
        'cc',
        'importateur',
        'adresse_importateur',
        'telephone_importateur',
        'fournisseur',
        'adresse_fournisseur',
        'pays_fournisseur',
        'tel_fournisseur',
        'fax_fournisseur',
        'incoterm',
        'libelle_incoterm',
        'ref_facture',
        'date_facture',
        'valeur_facture_cfa',
        'valeur_fob_cfa',
        'valeur_caf',
        'valeur_fret_cfa',
        'valeur_assurance_cfa',
        'nom_devise',
        'devise',
        'declarant',
        'ulid',
    ];

    protected $casts = [
        'ulid' => 'string',
        'date_fdi' => 'datetime',
        'date_derniere_operation' => 'datetime',
        'date_domiciliation' => 'datetime',
        'date_facture' => 'datetime',
        'montant_domicilie_cfa' => 'decimal:4',
        'valeur_facture_cfa' => 'decimal:4',
        'valeur_fob_cfa' => 'decimal:4',
        'valeur_caf' => 'decimal:4',
        'valeur_fret_cfa' => 'decimal:4',
        'valeur_assurance_cfa' => 'decimal:4',
        'devise' => 'decimal:4',
    ];

    /**
     * Relation avec la FDI principale
     */
    public function fdi()
    {
        return $this->belongsTo(FdiSg::class, 'instance_id', 'instance_id');
    }
}

