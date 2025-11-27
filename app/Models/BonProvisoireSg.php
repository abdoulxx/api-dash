<?php

namespace App\Models;

use App\Models\Concerns\HasPublicUlid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class BonProvisoireSg extends Model
{
    use HasFactory, SoftDeletes, HasPublicUlid;

    protected $table = 'bon_provisoire_sg';

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'instance_id',
        'annee',
        'bureau',
        'serie_bp',
        'num_serie_bp',
        'numero_bon_provisoire',
        'date_bp',
        'num_vol_lta',
        'num_lta',
        'date_lta',
        'date_expiration',
        'delai_jours',
        'ncc',
        'nom_importateur',
        'nom_fournisseur',
        'pays_origine',
        'code_declarant',
        'nom_declarant',
        'type_bon_provisoire',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            // Générer automatiquement l'ID (ULID) si non fourni
            if (empty($model->id)) {
                $model->id = (string) \Illuminate\Support\Str::ulid();
            }
        });
    }

    protected $casts = [
        'date_bp' => 'datetime',
        'date_lta' => 'datetime',
        'date_expiration' => 'datetime',
    ];

    protected $appends = ['numero_bon_provisoire_complet', 'identifiant'];

    /**
     * Relation avec les articles du bon provisoire
     */
    public function articles()
    {
        return $this->hasMany(BonProvisoireArticle::class, 'instance_id', 'instance_id');
    }

    /**
     * Numéro Bon Provisoire complet (ANNEE + BUREAU + SERIE_BP + NUM_SERIE_BP)
     */
    public function getNumeroBonProvisoireCompletAttribute(): ?string
    {
        // Si le numéro complet existe déjà, le retourner
        if ($this->numero_bon_provisoire) {
            return $this->numero_bon_provisoire;
        }

        // Sinon, construire à partir des composants
        if (!$this->annee || !$this->bureau || !$this->serie_bp || !$this->num_serie_bp) {
            return null;
        }

        return sprintf('%s%s%s%s', $this->annee, $this->bureau, $this->serie_bp, $this->num_serie_bp);
    }

    /**
     * Identifiant du bon provisoire (numéro complet ou fallback)
     */
    public function getIdentifiantAttribute(): string
    {
        return $this->numero_bon_provisoire_complet ?? $this->numero_bon_provisoire ?? "BP #{$this->id}";
    }
}







