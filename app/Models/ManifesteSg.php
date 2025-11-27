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

    protected $appends = ['numero_manifeste_complet', 'identifiant'];

    /**
     * Accessor pour le numéro de manifeste complet
     * Format: CODE_BUREAU || ' ' || ANNEE_MANIFESTE || ' ' || NUMERO_SEQUENTIEL
     */
    public function getNumeroManifesteCompletAttribute(): ?string
    {
        // Si num_manifeste existe déjà et correspond au format attendu, le retourner
        if ($this->num_manifeste && preg_match('/^[A-Z0-9]+\s+\d{4}\s+\d+$/', $this->num_manifeste)) {
            return $this->num_manifeste;
        }

        // Sinon, construire à partir des composants
        if ($this->code_bureau && $this->annee_manifeste) {
            $numeroSequential = $this->num_man_sydam ?? $this->instance_id;
            return sprintf('%s %d %s', $this->code_bureau, $this->annee_manifeste, $numeroSequential);
        }

        return $this->num_manifeste;
    }

    /**
     * Accessor pour l'identifiant lisible (fallback)
     * Format: NUM_MANIFESTE (si présent) OU CODE_BUREAU || ' ' || ANNEE_MANIFESTE || ' ' || NUM_MAN_SYDAM OU CODE_BUREAU || ' ' || ANNEE_MANIFESTE || ' ' || INSTANCE_ID
     */
    public function getIdentifiantAttribute(): ?string
    {
        // Priorité 1: num_manifeste si présent
        if ($this->num_manifeste) {
            return $this->num_manifeste;
        }

        // Priorité 2: Construction avec NUM_MAN_SYDAM
        if ($this->code_bureau && $this->annee_manifeste && $this->num_man_sydam) {
            return sprintf('%s %d %s', $this->code_bureau, $this->annee_manifeste, $this->num_man_sydam);
        }

        // Priorité 3: Construction avec INSTANCE_ID
        if ($this->code_bureau && $this->annee_manifeste && $this->instance_id) {
            return sprintf('%s %d %s', $this->code_bureau, $this->annee_manifeste, $this->instance_id);
        }

        return null;
    }

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





