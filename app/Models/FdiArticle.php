<?php

namespace App\Models;

use App\Models\Concerns\HasPublicUlid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class FdiArticle extends Model
{
    use HasFactory, SoftDeletes, HasPublicUlid;

    protected $table = 'fdi_article';

    protected $fillable = [
        'instance_id',
        'serie_fdi',
        'bureau',
        'annee',
        'numero_serie',
        'numero_fdi',
        'date_fdi',
        'numart',
        'postar',
        'nature_marchandise',
        'description_marchandise',
        'quantite',
        'poids_net',
        'poids_brut',
        'ulid',
    ];

    protected $casts = [
        'ulid' => 'string',
        'date_fdi' => 'datetime',
        'quantite' => 'decimal:4',
        'poids_net' => 'decimal:4',
        'poids_brut' => 'decimal:4',
    ];

    /**
     * Relation avec la FDI principale
     */
    public function fdi()
    {
        return $this->belongsTo(FdiSg::class, 'numero_fdi', 'numero_fdi');
    }
}

