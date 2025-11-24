<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class FcvrArticle extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'fcvr_article';

    protected $fillable = [
        'instanceid',
        'annee',
        'num_rfcv',
        'date_rfcv',
        'fob_article',
        'nombre_total_article',
        'num_article',
        'caf_article',
        'quantite_article',
        'unite_quantite',
        'sh_rfcv',
        'libelle_sh_rfcv',
        'fob_declaree',
        'caf_declaree',
        'quantite_declaree',
        'sh_declaration',
        'libelle_sh_declaration',
    ];

    protected $casts = [
        'date_rfcv' => 'datetime',
        'fob_article' => 'decimal:4',
        'caf_article' => 'decimal:4',
        'quantite_article' => 'decimal:4',
        'fob_declaree' => 'decimal:4',
        'caf_declaree' => 'decimal:4',
        'quantite_declaree' => 'decimal:4',
    ];

    /**
     * Relation avec la FCVR principale
     */
    public function fcvr()
    {
        return $this->belongsTo(FcvrSg::class, 'instanceid', 'instanceid');
    }
}

