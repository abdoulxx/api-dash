<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class BanqueTvf extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'banque_tvf';

    protected $fillable = [
        'old_id',
        'annee_fdi',
        'num_fdi',
        'date_fdi',
        'status_fdi',
        'date_autorisation_fdi',
        'date_expiration_fdi',
        'statut_ac',
        'base_sur_ac',
        'num_demande_ac',
        'date_dem_ac',
        'ref_ddu',
        'bank_dom',
        'date_dom',
        'num_dom',
        'banq_enreg',
        'num_enreg_banq',
        'date_aprob_bank',
        'date_aprob_bank2',
        'pays_exp',
        'autorise_par',
        'adr_exp',
        'code_cda_ac',
        'cda_ac',
        'benef_fonds',
        'adr_benef',
        'type_op',
        'dev_trans',
        'dev_paiem',
        'mont_fact_dev',
        'mont_fact_xof',
        'mont_ac_dev',
        'mont_ac_xof',
        'mont_tot_march_xof',
        'solde_dev',
    ];

    protected $casts = [
        'date_fdi' => 'datetime',
        'date_autorisation_fdi' => 'datetime',
        'date_expiration_fdi' => 'datetime',
        'date_dem_ac' => 'datetime',
        'date_dom' => 'datetime',
        'date_aprob_bank' => 'datetime',
        'date_aprob_bank2' => 'datetime',
        'num_fdi' => 'decimal:4',
        'mont_fact_dev' => 'decimal:4',
        'mont_fact_xof' => 'decimal:4',
        'mont_ac_dev' => 'decimal:4',
        'mont_ac_xof' => 'decimal:4',
        'solde_dev' => 'decimal:4',
    ];

    /**
     * Relation avec la FDI
     */
    public function fdi()
    {
        return $this->belongsTo(FdiSg::class, 'num_fdi', 'numero_fdi');
    }

    /**
     * Relation avec la première comparaison
     */
    public function comparaison1()
    {
        return $this->hasOne(BanqueTvfComp1::class, 'old_id', 'old_id');
    }

    /**
     * Relation avec la deuxième comparaison
     */
    public function comparaison2()
    {
        return $this->hasOne(BanqueTvfComp2::class, 'old_id', 'old_id');
    }
}

