<?php

namespace App\Models;

use App\Models\Concerns\HasPublicUlid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ManifesteSg extends Model
{
    use HasFactory, SoftDeletes, HasPublicUlid;

    protected $table = 'manifeste_sg';

    public $timestamps = true;

    protected $primaryKey = 'instance_id';

    public $incrementing = false;

    protected $fillable = [
        'instance_id',
        'ulid',
        'code_bureau',
        'libelle_bureau',
        'num_voyage',
        'date_voyage',
        'nbre_total_bl',
        'nbre_total_colis',
        'nbre_total_conteneur',
        'total_poids_brut',
        'date_arrivee_navire',
        'annee_manifeste',
        'num_man_sydam',
        'num_manifeste',
        'date_manifeste',
        'code_port_charg',
        'nom_port_charg',
        'code_port_decharg',
        'nom_port_decharg',
        'code_consignataire',
        'nom_consignataire',
        'adresse_consignataire',
        'nom_moyen_transport',
        'code_transport',
        'nom_transport',
        'code_nationalite_navire',
        'nom_nationalite_navire',
    ];

    protected $casts = [
        'date_voyage' => 'datetime',
        'date_arrivee_navire' => 'datetime',
        'date_manifeste' => 'datetime',
        'nbre_total_colis' => 'decimal:4',
        'total_poids_brut' => 'decimal:4',
        'ulid' => 'string',
    ];

    /**
     * Relation avec les titres de transport
     */
    public function titresTransport()
    {
        return $this->hasMany(ManifesteTt::class, 'num_manifeste', 'num_manifeste');
    }

    /**
     * Relation avec les conteneurs
     */
    public function conteneurs()
    {
        return $this->hasMany(ManifesteTc::class, 'num_manifeste', 'num_manifeste');
    }

    /**
     * Relation avec les déclarations
     */
    public function declarations()
    {
        return $this->hasMany(DeclarationSg::class, 'num_manifeste', 'num_manifeste');
    }
}

