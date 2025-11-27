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

    protected $primaryKey = 'id';

    public $incrementing = true;

    protected $keyType = 'int';

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
    ];

    protected $casts = [
        'date_declaration' => 'datetime',
        'date_quittance' => 'datetime',
        'annee' => 'integer',
        'taux_conversion' => 'decimal:4',
        'valeur_caf_declaration' => 'decimal:4',
        'nbre_colis' => 'decimal:4',
        'valeur_fob_declaration' => 'decimal:4',
        'droits_taxes_declaration' => 'decimal:4',
        'poids_brut_declaration' => 'decimal:4',
        'nombre_conteneur' => 'decimal:4',
        'nbre_total_article' => 'integer',
    ];

    protected $appends = ['identifiant', 'numero_declaration_complet'];

    /**
     * Accessor pour l'identifiant lisible
     */
    public function getIdentifiantAttribute(): ?string
    {
        return $this->declaration ?? "DEC-{$this->annee}-{$this->id}";
    }

    /**
     * Accessor pour le numéro de déclaration complet
     */
    public function getNumeroDeclarationCompletAttribute(): ?string
    {
        // Check if the 'declaration' field already looks like a complete number (e.g., 2020CIAB1C1)
        // This is a heuristic, assuming complete numbers start with a 4-digit year.
        if ($this->declaration && preg_match('/^\d{4}[A-Z]{2,5}[A-Z]\d+$/', $this->declaration)) {
            return $this->declaration;
        }

        // Otherwise, construct it from components
        if ($this->annee && $this->bureau && $this->typdec) {
            // Extract sequential part from 'declaration' if it's in 'DEC-YYYY-NNN' format
            if (preg_match('/DEC-\d{4}-(\d+)/', $this->declaration, $matches)) {
                $sequentialPart = str_pad($matches[1], 3, '0', STR_PAD_LEFT); // Pad to 3 digits
            } else {
                // Fallback to ID or a default if no sequential part can be extracted
                $sequentialPart = str_pad($this->id, 3, '0', STR_PAD_LEFT);
            }
            
            // Determine the type prefix. 'C' is common for import, but 'typdec' can be used.
            // For simplicity, using 'C' as per EXPLICATION_DECLARATION_NUMEROTATION.md examples.
            $typePrefix = 'C'; 
            if ($this->typdec && in_array(strtoupper($this->typdec), ['I', 'E'])) {
                $typePrefix = strtoupper($this->typdec) === 'I' ? 'C' : 'E'; // 'C' for Import, 'E' for Export
            }

            return sprintf('%s%s%s%s', $this->annee, $this->bureau, $typePrefix, $sequentialPart);
        }

        return null; // Or a default value if components are missing
    }

    /**
     * Relation avec les articles de déclaration
     */
    public function articles()
    {
        return $this->hasMany(DeclarationArticle::class, 'declaration', 'declaration')
            ->where('annee', $this->annee)
            ->where('bureau', $this->bureau);
    }

    /**
     * Relation avec le manifeste
     */
    public function manifeste()
    {
        return $this->belongsTo(ManifesteSg::class, 'num_manifeste', 'num_manifeste');
    }

    /**
     * Relation avec la FDI
     */
    public function fdi()
    {
        return $this->belongsTo(FdiSg::class, 'num_fdi', 'numero_fdi');
    }

    /**
     * Relation avec la FCVR
     */
    public function fcvr()
    {
        return $this->belongsTo(FcvrSg::class, 'num_bl', 'num_rfcv');
    }
}

