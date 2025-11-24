<?php

namespace App\Models;

use App\Models\Concerns\HasPublicUlid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class FdiSg extends Model
{
    use HasFactory, SoftDeletes, HasPublicUlid;

    protected $table = 'fdi_sg';
    
    protected $primaryKey = 'id';
    
    public $incrementing = true;
    
    protected $keyType = 'int';

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
     * Relation avec les articles de la FDI
     */
    public function articles()
    {
        return $this->hasMany(FdiArticle::class, 'numero_fdi', 'numero_fdi');
    }

    /**
     * Relation avec la FCVR associée
     */
    public function fcvr()
    {
        return $this->hasOne(FcvrSg::class, 'num_fdi', 'numero_fdi');
    }

    /**
     * Relation avec les déclarations douanières
     */
    public function declarations()
    {
        return $this->hasMany(DeclarationSg::class, 'num_fdi', 'numero_fdi');
    }

    /**
     * Relation avec la première comparaison
     */
    public function comparaison1()
    {
        return $this->hasOne(FdiSgComp1::class, 'instance_id', 'instance_id');
    }

    /**
     * Relation avec la deuxième comparaison
     */
    public function comparaison2()
    {
        return $this->hasOne(FdiSgComp2::class, 'instance_id', 'instance_id');
    }

    /**
     * Relation avec les recherches/comparaisons
     */
    public function recherches()
    {
        return $this->hasMany(FdiRechComp::class, 'fdi_primaire', 'numero_fdi');
    }
}

