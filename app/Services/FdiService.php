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
     * Conforme à EXPLICATION_FDI_NUMEROTATION.md et s360_analyse
     */
    public function compareFdi(FdiSg $primary, FdiSg $secondary): array
    {
        // Champs financiers à comparer (selon s360_analyse)
        $keysToCompare = [
            'valeur_facture_cfa',
            'valeur_fob_cfa',
            'valeur_caf',
            'valeur_fret_cfa',
            'valeur_assurance_cfa',
            'montant_domicilie_cfa',
            'devise',
        ];

        // Champs contextuels à comparer
        $contextKeysToCompare = [
            'banque',
            'ref_domiciliation',
            'incoterm',
            'nom_devise',
            'importateur',
            'fournisseur',
            'cc',
        ];

        $diffs = [];
        $contextDiffs = [];

        // Comparer les valeurs financières
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

        // Comparer les champs contextuels
        foreach ($contextKeysToCompare as $key) {
            $primaryValue = $primary->{$key} ?? null;
            $secondaryValue = $secondary->{$key} ?? null;

            if ($primaryValue != $secondaryValue) {
                $contextDiffs[$key] = [
                    'primary' => $primaryValue,
                    'secondary' => $secondaryValue,
                ];
            }
        }

        Log::channel('fdi')->info('Comparaison FDI réalisée', [
            'primary' => $primary->numero_fdi,
            'secondary' => $secondary->numero_fdi,
            'diffs' => $diffs,
            'context_diffs' => $contextDiffs,
        ]);

        return [
            'primary' => [
                'id' => $primary->id,
                'ulid' => $primary->ulid,
                'numero_fdi' => $primary->numero_fdi,
                'numero_fdi_complet' => $primary->numero_fdi_complet,
                'identifiant' => $primary->identifiant,
                'annee' => $primary->annee,
                'bureau' => $primary->bureau,
                'serie_fdi' => $primary->serie_fdi,
                'numero_serie' => $primary->numero_serie,
                'date_fdi' => $primary->date_fdi?->toIso8601String(),
                'derniere_operation' => $primary->derniere_operation,
                'date_derniere_operation' => $primary->date_derniere_operation?->toIso8601String(),
                'importateur' => $primary->importateur,
                'fournisseur' => $primary->fournisseur,
                'banque' => $primary->banque,
            ],
            'secondary' => [
                'id' => $secondary->id,
                'ulid' => $secondary->ulid,
                'numero_fdi' => $secondary->numero_fdi,
                'numero_fdi_complet' => $secondary->numero_fdi_complet,
                'identifiant' => $secondary->identifiant,
                'annee' => $secondary->annee,
                'bureau' => $secondary->bureau,
                'serie_fdi' => $secondary->serie_fdi,
                'numero_serie' => $secondary->numero_serie,
                'date_fdi' => $secondary->date_fdi?->toIso8601String(),
                'derniere_operation' => $secondary->derniere_operation,
                'date_derniere_operation' => $secondary->date_derniere_operation?->toIso8601String(),
                'importateur' => $secondary->importateur,
                'fournisseur' => $secondary->fournisseur,
                'banque' => $secondary->banque,
            ],
            'diffs' => $diffs,
            'context_diffs' => $contextDiffs,
            'summary' => [
                'total_financial_diffs' => count($diffs),
                'total_context_diffs' => count($contextDiffs),
                'has_differences' => count($diffs) > 0 || count($contextDiffs) > 0,
            ],
        ];
    }

    /**
     * Valide les règles métier d'une FDI.
     * Conforme à EXPLICATION_FDI_NUMEROTATION.md et s360_analyse
     */
    public function validateFdi(FdiSg $fdi): array
    {
        // Champs obligatoires selon s360_analyse
        $required = [
            'numero_fdi' => 'Numéro FDI',
            'date_fdi' => 'Date FDI',
            'importateur' => 'Importateur',
            'fournisseur' => 'Fournisseur',
            'banque' => 'Banque',
            'montant_domicilie_cfa' => 'Montant domicilié',
        ];

        // Champs recommandés (pour validation complète)
        $recommended = [
            'valeur_facture_cfa' => 'Valeur facture',
            'valeur_fob_cfa' => 'Valeur FOB',
            'valeur_caf' => 'Valeur CAF',
            'ref_facture' => 'Référence facture',
            'date_facture' => 'Date facture',
            'incoterm' => 'Incoterm',
            'nom_devise' => 'Devise',
            'devise' => 'Taux de change',
            'cc' => 'Code client',
            'ref_domiciliation' => 'Référence domiciliation',
            'date_domiciliation' => 'Date domiciliation',
        ];

        $missingRequired = [];
        $missingRecommended = [];

        // Vérifier les champs obligatoires
        foreach ($required as $field => $label) {
            $value = $fdi->{$field};
            if (empty($value) && $value !== 0 && $value !== '0') {
                $missingRequired[$field] = [
                    'field' => $field,
                    'label' => $label,
                    'status' => 'missing',
                ];
            } else {
                $missingRequired[$field] = [
                    'field' => $field,
                    'label' => $label,
                    'status' => 'present',
                    'value' => $value,
                ];
            }
        }

        // Vérifier les champs recommandés
        foreach ($recommended as $field => $label) {
            $value = $fdi->{$field};
            if (empty($value) && $value !== 0 && $value !== '0') {
                $missingRecommended[$field] = [
                    'field' => $field,
                    'label' => $label,
                    'status' => 'missing',
                ];
            } else {
                $missingRecommended[$field] = [
                    'field' => $field,
                    'label' => $label,
                    'status' => 'present',
                    'value' => $value,
                ];
            }
        }

        $requiredMissing = collect($missingRequired)->where('status', 'missing')->keys()->values()->toArray();
        $recommendedMissing = collect($missingRecommended)->where('status', 'missing')->keys()->values()->toArray();

        $isValid = empty($requiredMissing);
        $isComplete = empty($requiredMissing) && empty($recommendedMissing);

        // Informations sur la FDI
        $fdiInfo = [
            'numero_fdi' => $fdi->numero_fdi,
            'numero_fdi_complet' => $fdi->numero_fdi_complet,
            'identifiant' => $fdi->identifiant,
            'annee' => $fdi->annee,
            'bureau' => $fdi->bureau,
            'serie_fdi' => $fdi->serie_fdi,
            'numero_serie' => $fdi->numero_serie,
            'date_fdi' => $fdi->date_fdi?->toIso8601String(),
            'derniere_operation' => $fdi->derniere_operation,
            'importateur' => $fdi->importateur,
            'fournisseur' => $fdi->fournisseur,
            'banque' => $fdi->banque,
            'articles_count' => $fdi->articles()->count(),
            'declarations_count' => $fdi->declarations()->count(),
        ];

        // Résumé
        $summary = [
            'is_valid' => $isValid,
            'is_complete' => $isComplete,
            'required_fields_present' => count($required) - count($requiredMissing),
            'required_fields_total' => count($required),
            'recommended_fields_present' => count($recommended) - count($recommendedMissing),
            'recommended_fields_total' => count($recommended),
            'completion_percentage' => $isValid 
                ? round((count($required) + count($recommended) - count($requiredMissing) - count($recommendedMissing)) / (count($required) + count($recommended)) * 100, 2)
                : 0,
        ];

        Log::channel('fdi')->info('Validation FDI effectuée', [
            'numero_fdi' => $fdi->numero_fdi,
            'is_valid' => $isValid,
            'is_complete' => $isComplete,
            'missing_required' => $requiredMissing,
            'missing_recommended' => $recommendedMissing,
        ]);

        return [
            'valid' => $isValid,
            'complete' => $isComplete,
            'summary' => $summary,
            'required_fields' => $missingRequired,
            'recommended_fields' => $missingRecommended,
            'missing_required' => $requiredMissing,
            'missing_recommended' => $recommendedMissing,
            'fdi_info' => $fdiInfo,
        ];
    }

    /**
     * Calcule un estimatif des droits et taxes pour une FDI.
     * Conforme à EXPLICATION_FDI_NUMEROTATION.md et s360_analyse
     * 
     * Base de calcul : Valeur CAF (CIF) = valeur_fob + fret + assurance
     * Si valeur_caf est disponible, elle est utilisée directement (déjà inclut fret et assurance)
     * Sinon, on calcule : valeur_fob + fret + assurance
     */
    public function calculateDroits(FdiSg $fdi): array
    {
        // Valeurs disponibles selon EXPLICATION_FDI_NUMEROTATION.md
        $valeurFob = (float) ($fdi->valeur_fob_cfa ?? 0);
        $valeurCaf = (float) ($fdi->valeur_caf ?? 0);
        $valeurFret = (float) ($fdi->valeur_fret_cfa ?? 0);
        $valeurAssurance = (float) ($fdi->valeur_assurance_cfa ?? 0);
        $valeurFacture = (float) ($fdi->valeur_facture_cfa ?? 0);
        $montantDomicilie = (float) ($fdi->montant_domicilie_cfa ?? 0);

        // Base taxable : utiliser valeur_caf si disponible (CIF complet)
        // Sinon, calculer : FOB + fret + assurance
        if ($valeurCaf > 0) {
            $baseTaxable = $valeurCaf;
            $baseCalculation = 'valeur_caf';
        } else {
            $baseTaxable = $valeurFob + $valeurFret + $valeurAssurance;
            $baseCalculation = 'valeur_fob + valeur_fret + valeur_assurance';
        }

        // Si aucune base n'est disponible, utiliser valeur_facture ou montant_domicilie
        if ($baseTaxable <= 0) {
            if ($valeurFacture > 0) {
                $baseTaxable = $valeurFacture;
                $baseCalculation = 'valeur_facture_cfa';
            } elseif ($montantDomicilie > 0) {
                $baseTaxable = $montantDomicilie;
                $baseCalculation = 'montant_domicilie_cfa';
            }
        }

        // Taux de droits de douane standard (18% - peut être configuré selon le type de marchandise)
        $tauxDroits = 0.18; // 18% - taux standard en Côte d'Ivoire
        $tauxTVA = 0.18; // 18% - TVA standard
        
        // Calcul des droits de douane
        $droitsDouane = round($baseTaxable * $tauxDroits, 2);
        
        // Base TVA = base taxable + droits de douane
        $baseTVA = $baseTaxable + $droitsDouane;
        $tva = round($baseTVA * $tauxTVA, 2);
        
        // Total droits et taxes
        $totalDroitsTaxes = $droitsDouane + $tva;

        // Détails du calcul
        $calculationDetails = [
            'base_taxable' => $baseTaxable,
            'base_calculation' => $baseCalculation,
            'valeur_fob_cfa' => $valeurFob,
            'valeur_caf_cfa' => $valeurCaf,
            'valeur_fret_cfa' => $valeurFret,
            'valeur_assurance_cfa' => $valeurAssurance,
            'taux_droits' => $tauxDroits,
            'taux_tva' => $tauxTVA,
        ];

        Log::channel('fdi')->info('Calcul des droits effectué', [
            'numero_fdi' => $fdi->numero_fdi,
            'base_taxable' => $baseTaxable,
            'droits_douane' => $droitsDouane,
            'tva' => $tva,
            'total' => $totalDroitsTaxes,
        ]);

        return [
            'numero_fdi' => $fdi->numero_fdi,
            'numero_fdi_complet' => $fdi->numero_fdi_complet,
            'identifiant' => $fdi->identifiant,
            'base_taxable' => round($baseTaxable, 2),
            'droits_douane' => $droitsDouane,
            'tva' => $tva,
            'total_droits_taxes' => $totalDroitsTaxes,
            'currency' => $fdi->nom_devise ?? 'Franc CFA - BCEAO',
            'devise_taux' => $fdi->devise ?? 1.0,
            'calculation_details' => $calculationDetails,
        ];
    }
}

