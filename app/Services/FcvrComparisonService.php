<?php

namespace App\Services;

use App\Models\DeclarationSg;
use App\Models\FcvrSg;

class FcvrComparisonService
{
    /**
     * Retourne les résultats de comparaison détaillés entre un FCVR et une déclaration.
     */
    public function compare(FcvrSg $fcvr, DeclarationSg $declaration): array
    {
        $comparisons = [
            $this->compareString('Diff_NumBl_RD', 'Numéro BL', $fcvr->num_bl, $declaration->num_bl),
            $this->compareString('Diff_NumDeclaration_RD', 'Numéro de déclaration', $fcvr->num_declaration, $declaration->declaration),
            $this->compareNumeric('Diff_NbreColis_RD', 'Nombre de colis', $fcvr->nombre_total_colis, $declaration->nbre_colis, 0, 0),
            $this->compareNumeric('Diff_PoidsBrut_RD', 'Poids brut (kg)', $fcvr->poids_brut_total, $declaration->poids_brut_declaration, 0, 0, 'kg'),
            $this->compareNumeric('Diff_ValFob_RD', 'Valeur FOB (CFA)', $fcvr->fob_rfcv_cfa, $declaration->valeur_fob_declaration),
            $this->compareNumeric('Diff_ValCaf_RD', 'Valeur CAF (CFA)', $fcvr->caf_rfcv, $declaration->valeur_caf_declaration),
            $this->compareString('Diff_CodeAgreer_RD', 'Code agréé', $fcvr->code_declarant, $declaration->codagr),
            $this->compareString('Diff_Importateur_RD', 'Importateur', $fcvr->nom_importateur, $declaration->importateur),
            $this->compareString('Diff_Fournisseur_RD', 'Exportateur/Fournisseur', $fcvr->nom_fournisseur, $declaration->exportateur),
        ];

        $summary = $this->summarize($comparisons);

        return [
            'fcvr' => $this->extractFcvrData($fcvr),
            'declaration' => $this->extractDeclarationData($declaration),
            'comparisons' => $comparisons,
            'summary' => $summary,
        ];
    }

    private function compareString(string $key, string $label, $fcvrValue, $declarationValue): array
    {
        if ($fcvrValue === null && $declarationValue === null) {
            $status = 'unknown';
            $details = 'Valeurs indisponibles pour les deux documents';
        } elseif ($fcvrValue === $declarationValue) {
            $status = 'ok';
            $details = 'Valeurs identiques';
        } else {
            $status = 'danger';
            $fcvrDisplay = $fcvrValue ?? '(vide)';
            $declDisplay = $declarationValue ?? '(vide)';
            $details = sprintf('Valeurs différentes : FCVR = "%s", Déclaration = "%s"', $fcvrDisplay, $declDisplay);
        }

        return [
            'key' => $key,
            'label' => $label,
            'type' => 'string',
            'fcvr_value' => $fcvrValue,
            'declaration_value' => $declarationValue,
            'difference' => $fcvrValue === $declarationValue ? null : [
                'fcvr' => $fcvrValue,
                'declaration' => $declarationValue,
            ],
            'status' => $status,
            'details' => $details,
        ];
    }

    private function compareNumeric(
        string $key,
        string $label,
        $fcvrValue,
        $declarationValue,
        float $warningAbsolute = 100000,
        float $warningPercentage = 0.05,
        ?string $unit = null
    ): array {
        if ($fcvrValue === null && $declarationValue === null) {
            $status = 'unknown';
            $details = 'Valeurs indisponibles pour les deux documents';
            $difference = null;
        } elseif ($fcvrValue === null || $declarationValue === null) {
            $status = 'danger';
            $details = 'Valeur manquante sur un document';
            $difference = null;
        } else {
            $difference = (float) $fcvrValue - (float) $declarationValue;
            if (abs($difference) < 0.0001) {
                $status = 'ok';
                $details = 'Valeurs identiques';
                $difference = 0.0;
            } else {
                $baseline = $declarationValue != 0 ? (float) $declarationValue : ((float) $fcvrValue ?: 1);
                $percentage = abs($difference) / abs($baseline) * 100;

                $isWarning = ($warningAbsolute > 0 && abs($difference) <= $warningAbsolute)
                    || ($warningPercentage > 0 && $percentage <= ($warningPercentage * 100));

                $status = $isWarning ? 'warning' : 'danger';
                $details = $status === 'warning'
                    ? sprintf('Écart faible détecté (%.2f%%)', $percentage)
                    : sprintf('Écart important détecté (%.2f%%)', $percentage);
            }
        }

        $result = [
            'key' => $key,
            'label' => $label,
            'type' => 'numeric',
            'fcvr_value' => $fcvrValue,
            'declaration_value' => $declarationValue,
            'difference' => $difference,
            'unit' => $unit,
            'status' => $status,
            'details' => $details,
        ];

        if ($difference !== null && $status !== 'ok') {
            $baseline = $declarationValue != 0 ? (float) $declarationValue : ((float) $fcvrValue ?: 1);
            $percentage = abs($difference) / abs($baseline) * 100;
            $result['difference_percentage'] = round($percentage, 2);
        }

        return $result;
    }

    private function summarize(array $comparisons): array
    {
        $summary = [
            'total' => count($comparisons),
            'ok' => 0,
            'warning' => 0,
            'danger' => 0,
            'unknown' => 0,
        ];

        foreach ($comparisons as $comparison) {
            $status = $comparison['status'] ?? 'unknown';
            if (isset($summary[$status])) {
                $summary[$status]++;
            }
        }

        $totalValid = $summary['total'] - $summary['unknown'];
        $summary['completion_rate'] = $totalValid > 0
            ? round(($summary['ok'] / $totalValid) * 100, 2)
            : 0;

        $summary['has_blocking_issues'] = $summary['danger'] > 0;
        $summary['has_warnings'] = $summary['warning'] > 0;

        return $summary;
    }

    private function extractFcvrData(FcvrSg $fcvr): array
    {
        return [
            'id' => $fcvr->id,
            'ulid' => $fcvr->ulid,
            'identifiant' => $fcvr->identifiant,
            'numero_fcvr_complet' => $fcvr->numero_fcvr_complet,
            'annee' => $fcvr->annee,
            'bureau' => $fcvr->bureau,
            'sequence' => $fcvr->num_rfcv,
            'instanceid' => $fcvr->instanceid,
            'num_tt' => $fcvr->num_tt,
            'num_rfcv' => $fcvr->num_rfcv,
            'num_bl' => $fcvr->num_bl,
            'num_declaration' => $fcvr->num_declaration,
            'num_fdi' => $fcvr->num_fdi,
            'date_rfcv' => optional($fcvr->date_rfcv)->toIso8601String(),
            'nombre_conteneur' => $fcvr->nombre_conteneur,
            'nombre_total_colis' => $fcvr->nombre_total_colis,
            'poids_brut_total' => $fcvr->poids_brut_total,
            'fob_rfcv_cfa' => $fcvr->fob_rfcv_cfa,
            'caf_rfcv' => $fcvr->caf_rfcv,
            'caf_rfcv_cfa' => $fcvr->caf_rfcv_cfa,
            'code_declarant' => $fcvr->code_declarant,
            'nom_importateur' => $fcvr->nom_importateur,
            'nom_fournisseur' => $fcvr->nom_fournisseur,
            'devise' => $fcvr->devise,
            'taux_devise' => $fcvr->taux_devise,
        ];
    }

    private function extractDeclarationData(DeclarationSg $declaration): array
    {
        return [
            'id' => $declaration->id,
            'ulid' => $declaration->ulid,
            'identifiant' => $declaration->identifiant,
            'instanceid' => $declaration->instanceid,
            'declaration' => $declaration->declaration,
            'annee' => $declaration->annee,
            'bureau' => $declaration->bureau,
            'nom_bureau' => $declaration->nom_bureau,
            'num_bl' => $declaration->num_bl,
            'num_fdi' => $declaration->num_fdi,
            'num_manifeste' => $declaration->num_manifeste,
            'date_declaration' => optional($declaration->date_declaration)->toIso8601String(),
            'nbre_colis' => $declaration->nbre_colis,
            'poids_brut_declaration' => $declaration->poids_brut_declaration,
            'valeur_fob_declaration' => $declaration->valeur_fob_declaration,
            'valeur_caf_declaration' => $declaration->valeur_caf_declaration,
            'codagr' => $declaration->codagr,
            'importateur' => $declaration->importateur,
            'exportateur' => $declaration->exportateur,
            'devise' => $declaration->devise,
            'taux_conversion' => $declaration->taux_conversion,
        ];
    }
}










