<?php

namespace App\Models;

use App\Models\Concerns\HasPublicUlid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class BanqueSad extends Model
{
    use HasFactory, SoftDeletes, HasPublicUlid;

    protected $table = 'banque_sad';

    protected $fillable = [
        'id_sad',
        'ulid',
        'ref_ddu',
        'num_man',
        'bl_ddu',
        'num_ddu',
        'date_ddu',
        'type_dec',
        'sous_regime',
        'code_add',
        'num_cc',
        'dt_ddu',
        'code_declarant',
        'valeur_fob_ddu',
        'valeur_caf_ddu',
        'valeur_fret_ddu',
        'val_ass_ddu',
        'num_demande_ac',
        'date_dem_ac',
        'statut_ac',
        'base_sur',
        'num_dom',
        'date_dom',
        'bank_dom',
        'banq_enreg',
        'num_enreg_banq',
        'date_aprob_bank',
        'date_aprob_bank2',
        'pays_exp',
        'adr_exp',
        'autorise_par',
        'cda',
        'code_cda',
        'benef_fonds',
        'type_op',
        'dev_trans',
        'dev_paiem',
        'mont_ac_dev',
        'mont_ac_xof',
        'mont_fact_xof',
        'solde_dev',
        'mont_fact_dev',
        'mont_tot_march_xof',
    ];

    protected $casts = [
        'ulid' => 'string',
        'date_ddu' => 'datetime',
        'date_dem_ac' => 'datetime',
        'date_dom' => 'datetime',
        'date_aprob_bank' => 'datetime',
        'date_aprob_bank2' => 'datetime',
        'dt_ddu' => 'decimal:4',
        'valeur_fob_ddu' => 'decimal:4',
        'valeur_caf_ddu' => 'decimal:4',
        'valeur_fret_ddu' => 'decimal:4',
        'val_ass_ddu' => 'decimal:4',
        'mont_ac_dev' => 'decimal:4',
        'mont_ac_xof' => 'decimal:4',
        'solde_dev' => 'decimal:4',
    ];

    /**
     * Relation avec la déclaration via ref_ddu
     */
    public function declaration()
    {
        return $this->belongsTo(DeclarationSg::class, 'ref_ddu', 'declaration');
    }

    /**
     * Relation avec le manifeste via num_man
     */
    public function manifeste()
    {
        return $this->belongsTo(ManifesteSg::class, 'num_man', 'num_manifeste');
    }
}

