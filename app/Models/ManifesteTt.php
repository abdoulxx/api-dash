<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ManifesteTt extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'manifeste_tt';

    protected $fillable = [
        'instance_id',
        'etat_apurement',
        'code_bureau',
        'code_bureau_de_provenance',
        'nom_bureau',
        'num_voy_ds',
        'num_voy_de_provenance',
        'date_arrive',
        'num_titre_transport',
        'num_bl_provenance',
        'annee_manifeste',
        'num_man_sydam',
        'num_manifeste',
        'date_manifeste',
        'code_consignataire',
        'nom_consignataire',
        'adresse_consignataire',
        'ligne_manfeste',
        'status_cns',
        'nature_cns',
        'nombre_conteneur',
        'poids_brut',
        'poids_restant',
        'type_cns',
        'libelle_cns',
        'code_exportateur',
        'nom_exportateur',
        'code_importateur',
        'nom_importateur',
        'nom_navire',
        'code_mode_transport',
        'nom_mode_transport',
        'nationalite_navire',
        'code_nationalite_navire',
        'notifie_a',
        'adresse_notifie_a',
        'lieu_charg',
        'nom_lieu_charg',
        'lieu_decharg',
        'nom_lieu_decharg',
        'nature_marchandise',
        'description_marchandise',
        'code_emballage',
        'nature_emballage',
        'nbr_colis',
    ];

    protected $casts = [
        'date_arrive' => 'datetime',
        'date_manifeste' => 'datetime',
        'poids_brut' => 'decimal:4',
        'poids_restant' => 'decimal:4',
        'nbr_colis' => 'decimal:4',
    ];

    /**
     * Relation avec le manifeste principal
     */
    public function manifeste()
    {
        return $this->belongsTo(ManifesteSg::class, 'num_manifeste', 'num_manifeste');
    }
}

