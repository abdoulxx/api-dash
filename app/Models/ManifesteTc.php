<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ManifesteTc extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'manifeste_tc';
    
    public $timestamps = false;
    public $incrementing = false;
    protected $primaryKey = 'instance_id';

    protected $fillable = [
        'instance_id',
        'etat_apurement',
        'code_bureau',
        'code_bureau_de_provenance',
        'nom_bureau',
        'numero_voyage',
        'numero_voyage_de_provenance',
        'date_arrive',
        'numero_bl',
        'numero_bl_provenance',
        'annee_manifeste',
        'num_man_sydam',
        'num_manifeste',
        'date_manifeste',
        'code_consignataire',
        'nom_consignataire',
        'adresse_manifeste',
        'ligne_manfeste',
        'status_manifeste',
        'nature_manifeste',
        'nombre_conteneur',
        'poids_brut',
        'poids_restant',
        'type_manifeste',
        'libelle_manifeste',
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
        'code_port_chargement',
        'nom_port_chargement',
        'code_port_dechargement',
        'nom_port_dechargement',
        'code_emballage',
        'nature_emballage',
        'nombre_colis',
        'nbre_colis_conteneur',
        'num_conteneur',
        'taille_conteneur',
        'plomb1',
        'plomb2',
    ];

    protected $casts = [
        'date_arrive' => 'datetime',
        'date_manifeste' => 'datetime',
        'poids_brut' => 'decimal:4',
        'poids_restant' => 'decimal:4',
        'nombre_colis' => 'decimal:4',
    ];

    /**
     * Relation avec le manifeste principal
     */
    public function manifeste()
    {
        return $this->belongsTo(ManifesteSg::class, 'num_manifeste', 'num_manifeste');
    }
}







