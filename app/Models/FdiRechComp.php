<?php

namespace App\Models;

use App\Models\Concerns\HasPublicUlid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class FdiRechComp extends Model
{
    use HasFactory, SoftDeletes, HasPublicUlid;

    protected $table = 'fdi_rech_comp';

    protected $fillable = [
        'id_fdi_comp',
        'fdi_primaire',
        'date_fdi_primaire',
        'fdi_secondaire',
        'date_fdi_secondaire',
        'observation',
        'ulid',
    ];

    protected $casts = [
        'ulid' => 'string',
        'date_fdi_primaire' => 'datetime',
        'date_fdi_secondaire' => 'datetime',
        'id_fdi_comp' => 'decimal:4',
    ];

    /**
     * Relation avec la FDI primaire
     */
    public function fdiPrimaire()
    {
        return $this->belongsTo(FdiSg::class, 'fdi_primaire', 'numero_fdi');
    }

    /**
     * Relation avec la FDI secondaire
     */
    public function fdiSecondaire()
    {
        return $this->belongsTo(FdiSg::class, 'fdi_secondaire', 'numero_fdi');
    }
}

