<?php

namespace App\Services;

use App\Models\BanqueSad;
use App\Models\DeclarationSg;
use App\Models\FcvrSg;
use App\Models\FdiSg;
use App\Models\ManifesteSg;
use Illuminate\Support\Arr;

class ControleService
{
    public function controleFdiCompare(FdiSg $primary, FdiSg $secondary): array
    {
        $primaryData = Arr::only($primary->toArray(), [
            'numero_fdi',
            'numero_serie',
            'banque',
            'montant_domicilie_cfa',
            'valeur_fob_cfa',
            'valeur_caf',
            'valeur_assurance_cfa',
        ]);

        $secondaryData = Arr::only($secondary->toArray(), array_keys($primaryData));

        return [
            'type' => 'fdi_compare',
            'primary_id' => $primary->id,
            'primary_ulid' => $primary->ulid,
            'secondary_id' => $secondary->id,
            'secondary_ulid' => $secondary->ulid,
            'differences' => $this->detectEcarts($primaryData, $secondaryData),
        ];
    }

    public function controleFcvrDeclaration(FcvrSg $fcvr, DeclarationSg $declaration): array
    {
        $fcvrData = Arr::only($fcvr->toArray(), [
            'num_rfcv',
            'num_declaration',
            'numero_facture',
            'val_fact_rfcv_cfa',
            'fob_rfcv_cfa',
            'fret_rfcv_cfa',
        ]);

        $declarationData = [
            'num_rfcv' => $declaration->declaration,
            'num_declaration' => $declaration->declaration,
            'numero_facture' => $declaration->num_dossier,
            'val_fact_rfcv_cfa' => $declaration->valeur_caf_declaration,
            'fob_rfcv_cfa' => $declaration->valeur_fob_declaration,
            'fret_rfcv_cfa' => $declaration->droits_taxes_declaration,
        ];

        return [
            'type' => 'fcvr_declaration',
            'fcvr_id' => $fcvr->id,
            'declaration_id' => $declaration->id,
            'differences' => $this->detectEcarts($fcvrData, $declarationData),
        ];
    }

    public function controleManifesteDeclaration(ManifesteSg $manifeste, DeclarationSg $declaration): array
    {
        $manifesteData = Arr::only($manifeste->toArray(), [
            'num_manifeste',
            'nbre_total_colis',
            'total_poids_brut',
        ]);

        $declarationData = [
            'num_manifeste' => $declaration->num_manifeste,
            'nbre_total_colis' => $declaration->nbre_colis,
            'total_poids_brut' => $declaration->poids_brut_declaration,
        ];

        return [
            'type' => 'manifeste_declaration',
            'manifeste_id' => $manifeste->id,
            'declaration_id' => $declaration->id,
            'differences' => $this->detectEcarts($manifesteData, $declarationData),
        ];
    }

    public function controleBanqueAc(BanqueSad $banque, DeclarationSg $declaration): array
    {
        $banqueData = Arr::only($banque->toArray(), [
            'num_ddu',
            'num_dom',
            'date_dom',
            'bank_dom',
            'valeur_caf_ddu',
        ]);

        $declarationData = [
            'num_ddu' => $declaration->declaration,
            'num_dom' => $declaration->num_dossier,
            'date_dom' => $declaration->date_quittance,
            'bank_dom' => $declaration->banq_code,
            'valeur_caf_ddu' => $declaration->valeur_caf_declaration,
        ];

        return [
            'type' => 'banque_declaration',
            'banque_id' => $banque->id,
            'declaration_id' => $declaration->id,
            'differences' => $this->detectEcarts($banqueData, $declarationData),
        ];
    }

    public function detectEcarts(array $primary, array $secondary, array $keys = []): array
    {
        $keys = $keys ?: array_unique(array_merge(array_keys($primary), array_keys($secondary)));

        $differences = [];

        foreach ($keys as $key) {
            $p = $primary[$key] ?? null;
            $s = $secondary[$key] ?? null;

            if ($p != $s) {
                $differences[$key] = [
                    'primary' => $p,
                    'secondary' => $s,
                ];
            }
        }

        return $differences;
    }
}



