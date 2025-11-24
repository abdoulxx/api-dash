<?php

namespace App\Services;

use App\Models\FdiSg;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class FdiService
{
    /**
     * Compare deux FDI et renvoie les écarts détectés.
     */
    public function compareFdi(FdiSg $primary, FdiSg $secondary): array
    {
        $keysToCompare = [
            'valeur_facture_cfa',
            'valeur_fob_cfa',
            'valeur_caf',
            'valeur_fret_cfa',
            'valeur_assurance_cfa',
            'montant_domicilie_cfa',
        ];

        $diffs = [];

        foreach ($keysToCompare as $key) {
            $primaryValue = $primary->{$key} ?? null;
            $secondaryValue = $secondary->{$key} ?? null;

            if ($primaryValue != $secondaryValue) {
                $diffs[$key] = [
                    'primary' => $primaryValue,
                    'secondary' => $secondaryValue,
                ];
            }
        }

        Log::channel('fdi')->info('Comparaison FDI réalisée', [
            'primary' => $primary->numero_fdi,
            'secondary' => $secondary->numero_fdi,
            'diffs' => $diffs,
        ]);

        return [
            'primary' => $primary->only(['numero_fdi', 'derniere_operation', 'date_derniere_operation']),
            'secondary' => $secondary->only(['numero_fdi', 'derniere_operation', 'date_derniere_operation']),
            'diffs' => $diffs,
        ];
    }

    /**
     * Valide les règles métier d'une FDI.
     */
    public function validateFdi(FdiSg $fdi): array
    {
        $required = [
            'numero_fdi',
            'banque',
            'montant_domicilie_cfa',
            'date_fdi',
            'importateur',
            'fournisseur',
        ];

        $missing = collect($required)
            ->filter(fn (string $field) => empty($fdi->{$field}))
            ->values();

        $isValid = $missing->isEmpty();

        Log::channel('fdi')->info('Validation FDI effectuée', [
            'numero_fdi' => $fdi->numero_fdi,
            'is_valid' => $isValid,
            'missing' => $missing,
        ]);

        return [
            'is_valid' => $isValid,
            'missing_fields' => $missing,
        ];
    }

    /**
     * Calcule un estimatif des droits et taxes pour une FDI.
     */
    public function calculateDroits(FdiSg $fdi): array
    {
        $base = (float) ($fdi->valeur_caf ?? 0);
        $fret = (float) ($fdi->valeur_fret_cfa ?? 0);
        $assurance = (float) ($fdi->valeur_assurance_cfa ?? 0);
        $taux = 0.18; // taux fictif 18%

        $taxable = $base + $fret + $assurance;
        $droits = round($taxable * $taux, 2);

        Log::channel('fdi')->info('Calcul des droits effectué', [
            'numero_fdi' => $fdi->numero_fdi,
            'taxable' => $taxable,
            'droits' => $droits,
        ]);

        return [
            'numero_fdi' => $fdi->numero_fdi,
            'taxable_amount' => $taxable,
            'estimated_duties' => $droits,
            'currency' => $fdi->nom_devise ?? 'XOF',
        ];
    }
}

