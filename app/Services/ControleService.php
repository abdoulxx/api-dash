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
    private const FINANCIAL_WARNING_THRESHOLD = 0.05; // 5%
    private const FINANCIAL_DANGER_THRESHOLD = 0.15;  // 15%

    public function controleFdiCompare(FdiSg $primary, FdiSg $secondary): array
    {
        $primaryDetails = $this->formatFdiDetails($primary);
        $secondaryDetails = $this->formatFdiDetails($secondary);

        $financialDiffs = $this->buildFinancialDiffs($primary, $secondary);
        $contextDiffs = $this->buildContextDiffs($primary, $secondary);

        $summary = [
            'total_financial_diffs' => count($financialDiffs),
            'total_context_diffs' => count($contextDiffs),
            'has_diffs' => count($financialDiffs) + count($contextDiffs) > 0,
        ];

        $message = sprintf(
            'Comparaison entre FDI %s (primaire) et %s (secondaire) effectuée.',
            $primaryDetails['numero_fdi_complet'] ?? $primary->identifiant,
            $secondaryDetails['numero_fdi_complet'] ?? $secondary->identifiant
        );

        return [
            'message' => $message,
            'data' => [
                'type' => 'fdi_compare',
                'primaire' => $primaryDetails,
                'secondaire' => $secondaryDetails,
                'diffs' => $financialDiffs,
                'context_diffs' => $contextDiffs,
                'summary' => $summary,
            ],
        ];
    }

    public function controleFcvrDeclaration(FcvrSg $fcvr, DeclarationSg $declaration): array
    {
        $fcvrDetails = $this->formatFcvrDetails($fcvr);
        $declarationDetails = $this->formatDeclarationDetails($declaration);

        $financialDiffs = $this->buildFcvrDeclarationFinancialDiffs($fcvr, $declaration);
        $contextDiffs = $this->buildFcvrDeclarationContextDiffs($fcvr, $declaration);

        $summary = [
            'total_financial_diffs' => count($financialDiffs),
            'total_context_diffs' => count($contextDiffs),
            'has_diffs' => count($financialDiffs) + count($contextDiffs) > 0,
        ];

        $message = sprintf(
            'Comparaison entre FCVR %s et Déclaration %s effectuée.',
            $fcvrDetails['numero_fcvr_complet'] ?? $fcvr->identifiant,
            $declarationDetails['numero_declaration_complet'] ?? $declaration->identifiant
        );

        return [
            'message' => $message,
            'data' => [
                'type' => 'fcvr_declaration',
                'fcvr' => $fcvrDetails,
                'declaration' => $declarationDetails,
                'diffs' => $financialDiffs,
                'context_diffs' => $contextDiffs,
                'summary' => $summary,
            ],
        ];
    }

    public function controleManifesteDeclaration(ManifesteSg $manifeste, DeclarationSg $declaration): array
    {
        $manifesteDetails = $this->formatManifesteDetails($manifeste);
        $declarationDetails = $this->formatDeclarationDetails($declaration);

        $financialDiffs = $this->buildManifesteDeclarationFinancialDiffs($manifeste, $declaration);
        $contextDiffs = $this->buildManifesteDeclarationContextDiffs($manifeste, $declaration);

        $summary = [
            'total_financial_diffs' => count($financialDiffs),
            'total_context_diffs' => count($contextDiffs),
            'has_diffs' => count($financialDiffs) + count($contextDiffs) > 0,
        ];

        $message = sprintf(
            'Comparaison entre Manifeste %s et Déclaration %s effectuée.',
            $manifesteDetails['numero_manifeste_complet'] ?? $manifeste->identifiant,
            $declarationDetails['numero_declaration_complet'] ?? $declaration->identifiant
        );

        return [
            'message' => $message,
            'data' => [
                'type' => 'manifeste_declaration',
                'manifeste' => $manifesteDetails,
                'declaration' => $declarationDetails,
                'diffs' => $financialDiffs,
                'context_diffs' => $contextDiffs,
                'summary' => $summary,
            ],
        ];
    }

    public function controleBanqueAc(BanqueSad $banque, DeclarationSg $declaration): array
    {
        $banqueDetails = $this->formatBanqueSadDetails($banque);
        $declarationDetails = $this->formatDeclarationDetails($declaration);

        $financialDiffs = $this->buildBanqueDeclarationFinancialDiffs($banque, $declaration);
        $contextDiffs = $this->buildBanqueDeclarationContextDiffs($banque, $declaration);

        $summary = [
            'total_financial_diffs' => count($financialDiffs),
            'total_context_diffs' => count($contextDiffs),
            'has_diffs' => count($financialDiffs) + count($contextDiffs) > 0,
        ];

        $message = sprintf(
            'Comparaison entre Banque SAD %s et Déclaration %s effectuée.',
            $banqueDetails['identifiant'] ?? "SAD #{$banque->id}",
            $declarationDetails['numero_declaration_complet'] ?? $declaration->identifiant
        );

        return [
            'message' => $message,
            'data' => [
                'type' => 'banque_declaration',
                'banque' => $banqueDetails,
                'declaration' => $declarationDetails,
                'diffs' => $financialDiffs,
                'context_diffs' => $contextDiffs,
                'summary' => $summary,
            ],
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

    private function formatFdiDetails(FdiSg $fdi): array
    {
        return [
            'id' => $fdi->id,
            'ulid' => $fdi->ulid,
            'numero_fdi' => $fdi->numero_fdi,
            'numero_fdi_complet' => $fdi->numero_fdi_complet,
            'identifiant' => $fdi->identifiant,
            'instance_id' => $fdi->instance_id,
            'annee' => $fdi->annee,
            'bureau' => $fdi->bureau,
            'serie_fdi' => $fdi->serie_fdi,
            'numero_serie' => $fdi->numero_serie,
            'date_fdi' => $fdi->date_fdi?->toIso8601String(),
            'derniere_operation' => $fdi->derniere_operation,
            'date_derniere_operation' => $fdi->date_derniere_operation?->toIso8601String(),
            'reglement' => $fdi->reglement,
            'banque' => $fdi->banque,
            'ref_domiciliation' => $fdi->ref_domiciliation,
            'date_domiciliation' => $fdi->date_domiciliation?->toIso8601String(),
            'montant_domicilie_cfa' => $fdi->montant_domicilie_cfa,
            'cc' => $fdi->cc,
            'importateur' => $fdi->importateur,
            'fournisseur' => $fdi->fournisseur,
            'pays_fournisseur' => $fdi->pays_fournisseur,
            'incoterm' => $fdi->incoterm,
            'libelle_incoterm' => $fdi->libelle_incoterm,
            'ref_facture' => $fdi->ref_facture,
            'date_facture' => $fdi->date_facture?->toIso8601String(),
            'valeur_facture_cfa' => $fdi->valeur_facture_cfa,
            'valeur_fob_cfa' => $fdi->valeur_fob_cfa,
            'valeur_caf' => $fdi->valeur_caf,
            'valeur_fret_cfa' => $fdi->valeur_fret_cfa,
            'valeur_assurance_cfa' => $fdi->valeur_assurance_cfa,
            'nom_devise' => $fdi->nom_devise,
            'devise' => $fdi->devise,
            'declarant' => $fdi->declarant,
        ];
    }

    private function buildFinancialDiffs(FdiSg $primary, FdiSg $secondary): array
    {
        $metrics = [
            'valeur_caf' => 'Valeur CAF',
            'valeur_fob_cfa' => 'Valeur FOB',
            'valeur_fret_cfa' => 'Valeur FRET',
            'valeur_assurance_cfa' => 'Valeur Assurance',
            'montant_domicilie_cfa' => 'Montant domicilié',
        ];

        $diffs = [];

        foreach ($metrics as $key => $label) {
            $primaryValue = $primary->{$key};
            $secondaryValue = $secondary->{$key};

            if ($primaryValue == $secondaryValue) {
                continue;
            }

            $primaryFloat = (float) $primaryValue;
            $secondaryFloat = (float) $secondaryValue;
            $delta = $primaryFloat - $secondaryFloat;
            $base = max(abs($primaryFloat), abs($secondaryFloat), 1);
            $percent = $base !== 0 ? $delta / $base : 0;

            $state = 'ok';
            $absPercent = abs($percent);
            if ($absPercent >= self::FINANCIAL_DANGER_THRESHOLD) {
                $state = 'danger';
            } elseif ($absPercent >= self::FINANCIAL_WARNING_THRESHOLD) {
                $state = 'warn';
            }

            $diffs[$key] = [
                'label' => $label,
                'primaire' => $primaryValue,
                'secondaire' => $secondaryValue,
                'ecart' => number_format($delta, 4, '.', ''),
                'ecart_percent' => number_format($percent * 100, 2, '.', ''),
                'etat' => $state,
            ];
        }

        return $diffs;
    }

    private function buildContextDiffs(FdiSg $primary, FdiSg $secondary): array
    {
        $fields = [
            'banque' => 'Banque',
            'importateur' => 'Importateur',
            'fournisseur' => 'Fournisseur',
            'incoterm' => 'Incoterm',
            'nom_devise' => 'Devise',
            'ref_domiciliation' => 'Référence domiciliation',
            'reglement' => 'Mode de règlement',
            'pays_fournisseur' => 'Pays fournisseur',
        ];

        $diffs = [];

        foreach ($fields as $key => $label) {
            $primaryValue = $primary->{$key};
            $secondaryValue = $secondary->{$key};

            if ($primaryValue == $secondaryValue) {
                continue;
            }

            $diffs[$key] = [
                'label' => $label,
                'primaire' => $primaryValue,
                'secondaire' => $secondaryValue,
                'etat' => 'warn',
            ];
        }

        return $diffs;
    }

    private function formatFcvrDetails(FcvrSg $fcvr): array
    {
        return [
            'id' => $fcvr->id,
            'ulid' => $fcvr->ulid,
            'instanceid' => $fcvr->instanceid,
            'num_rfcv' => $fcvr->num_rfcv,
            'numero_fcvr_complet' => $fcvr->numero_fcvr_complet,
            'identifiant' => $fcvr->identifiant,
            'annee' => $fcvr->annee,
            'bureau' => $fcvr->bureau,
            'num_fdi' => $fcvr->num_fdi,
            'date_fdi' => $fcvr->date_fdi?->toIso8601String(),
            'date_rfcv' => $fcvr->date_rfcv?->toIso8601String(),
            'derniere_operation' => $fcvr->derniere_operation,
            'date_derniere_operation' => $fcvr->date_derniere_operation?->toIso8601String(),
            'cc' => $fcvr->cc,
            'nom_importateur' => $fcvr->nom_importateur,
            'pays_importateur' => $fcvr->pays_importateur,
            'nom_fournisseur' => $fcvr->nom_fournisseur,
            'pays_fournisseur' => $fcvr->pays_fournisseur,
            'nom_declarant' => $fcvr->nom_declarant,
            'code_declarant' => $fcvr->code_declarant,
            'numero_facture' => $fcvr->numero_facture,
            'date_facture' => $fcvr->date_facture?->toIso8601String(),
            'incoterm' => $fcvr->incoterm,
            'devise' => $fcvr->devise,
            'taux_devise' => $fcvr->taux_devise,
            'val_fact_rfcv_devise' => $fcvr->val_fact_rfcv_devise,
            'val_fact_rfcv_cfa' => $fcvr->val_fact_rfcv_cfa,
            'fob_rfcv' => $fcvr->fob_rfcv,
            'fob_rfcv_cfa' => $fcvr->fob_rfcv_cfa,
            'fret_rfcv' => $fcvr->fret_rfcv,
            'fret_rfcv_cfa' => $fcvr->fret_rfcv_cfa,
            'assurance_rfcv' => $fcvr->assurance_rfcv,
            'assurance_rfcv_cfa' => $fcvr->assurance_rfcv_cfa,
            'autres_couts_rfcv' => $fcvr->autres_couts_rfcv,
            'autres_rfcv_cfa' => $fcvr->autres_rfcv_cfa,
            'caf_rfcv' => $fcvr->caf_rfcv,
            'caf_rfcv_cfa' => $fcvr->caf_rfcv_cfa,
            'num_declaration' => $fcvr->num_declaration,
            'date_declaration' => $fcvr->date_declaration?->toIso8601String(),
            'nombre_total_article' => $fcvr->nombre_total_article,
            'poids_net_total' => $fcvr->poids_net_total,
            'poids_brut_total' => $fcvr->poids_brut_total,
            'nombre_total_colis' => $fcvr->nombre_total_colis ?? $fcvr->nbre_total_colis,
        ];
    }

    private function formatDeclarationDetails(DeclarationSg $declaration): array
    {
        return [
            'id' => $declaration->id,
            'ulid' => $declaration->ulid,
            'instanceid' => $declaration->instanceid,
            'declaration' => $declaration->declaration,
            'numero_declaration_complet' => $declaration->numero_declaration_complet,
            'identifiant' => $declaration->identifiant,
            'annee' => $declaration->annee,
            'bureau' => $declaration->bureau,
            'nom_bureau' => $declaration->nom_bureau,
            'typdec' => $declaration->typdec,
            'sens' => $declaration->sens,
            'date_declaration' => $declaration->date_declaration?->toIso8601String(),
            'date_quittance' => $declaration->date_quittance?->toIso8601String(),
            'quittance' => $declaration->quittance,
            'num_manifeste' => $declaration->num_manifeste,
            'num_fdi' => $declaration->num_fdi,
            'num_bl' => $declaration->num_bl,
            'cc_imp' => $declaration->cc_imp,
            'importateur' => $declaration->importateur,
            'cc_exp' => $declaration->cc_exp,
            'exportateur' => $declaration->exportateur,
            'code_destinataire_reel' => $declaration->code_destinataire_reel,
            'nom_destinataire_reel' => $declaration->nom_destinataire_reel,
            'codagr' => $declaration->codagr,
            'declarant' => $declaration->declarant,
            'num_dossier' => $declaration->num_dossier,
            'devise' => $declaration->devise,
            'taux_conversion' => $declaration->taux_conversion,
            'valeur_caf_declaration' => $declaration->valeur_caf_declaration,
            'valeur_fob_declaration' => $declaration->valeur_fob_declaration,
            'droits_taxes_declaration' => $declaration->droits_taxes_declaration,
            'nbre_total_article' => $declaration->nbre_total_article,
            'nbre_colis' => $declaration->nbre_colis,
            'poids_brut_declaration' => $declaration->poids_brut_declaration,
            'nombre_conteneur' => $declaration->nombre_conteneur,
            'banq_code' => $declaration->banq_code,
            'incoterm' => $declaration->condition_liv,
        ];
    }

    private function buildFcvrDeclarationFinancialDiffs(FcvrSg $fcvr, DeclarationSg $declaration): array
    {
        $metrics = [
            'valeur_caf' => [
                'fcvr' => 'caf_rfcv_cfa',
                'declaration' => 'valeur_caf_declaration',
                'label' => 'Valeur CAF',
            ],
            'valeur_fob' => [
                'fcvr' => 'fob_rfcv_cfa',
                'declaration' => 'valeur_fob_declaration',
                'label' => 'Valeur FOB',
            ],
            'fret' => [
                'fcvr' => 'fret_rfcv_cfa',
                'declaration' => 'droits_taxes_declaration',
                'label' => 'Fret / Droits et taxes',
            ],
            'valeur_facture' => [
                'fcvr' => 'val_fact_rfcv_cfa',
                'declaration' => 'valeur_caf_declaration',
                'label' => 'Valeur facture',
            ],
        ];

        $diffs = [];

        foreach ($metrics as $key => $config) {
            $fcvrValue = $fcvr->{$config['fcvr']};
            $declarationValue = $declaration->{$config['declaration']};

            if ($fcvrValue == $declarationValue) {
                continue;
            }

            $fcvrFloat = (float) $fcvrValue;
            $declarationFloat = (float) $declarationValue;
            $delta = $fcvrFloat - $declarationFloat;
            $base = max(abs($fcvrFloat), abs($declarationFloat), 1);
            $percent = $base !== 0 ? $delta / $base : 0;

            $state = 'ok';
            $absPercent = abs($percent);
            if ($absPercent >= self::FINANCIAL_DANGER_THRESHOLD) {
                $state = 'danger';
            } elseif ($absPercent >= self::FINANCIAL_WARNING_THRESHOLD) {
                $state = 'warn';
            }

            $diffs[$key] = [
                'label' => $config['label'],
                'fcvr' => $fcvrValue,
                'declaration' => $declarationValue,
                'ecart' => number_format($delta, 4, '.', ''),
                'ecart_percent' => number_format($percent * 100, 2, '.', ''),
                'etat' => $state,
            ];
        }

        return $diffs;
    }

    private function buildFcvrDeclarationContextDiffs(FcvrSg $fcvr, DeclarationSg $declaration): array
    {
        $fields = [
            'importateur' => [
                'fcvr' => 'nom_importateur',
                'declaration' => 'importateur',
                'label' => 'Importateur',
            ],
            'fournisseur' => [
                'fcvr' => 'nom_fournisseur',
                'declaration' => 'exportateur',
                'label' => 'Fournisseur / Exportateur',
            ],
            'incoterm' => [
                'fcvr' => 'incoterm',
                'declaration' => 'condition_liv',
                'label' => 'Incoterm / Condition livraison',
            ],
            'devise' => [
                'fcvr' => 'devise',
                'declaration' => 'devise',
                'label' => 'Devise',
            ],
            'num_declaration' => [
                'fcvr' => 'num_declaration',
                'declaration' => 'declaration',
                'label' => 'Numéro déclaration',
            ],
            'num_fdi' => [
                'fcvr' => 'num_fdi',
                'declaration' => 'num_fdi',
                'label' => 'Numéro FDI',
            ],
        ];

        $diffs = [];

        foreach ($fields as $key => $config) {
            $fcvrValue = $fcvr->{$config['fcvr']};
            $declarationValue = $declaration->{$config['declaration']};

            if ($fcvrValue == $declarationValue) {
                continue;
            }

            $diffs[$key] = [
                'label' => $config['label'],
                'fcvr' => $fcvrValue,
                'declaration' => $declarationValue,
                'etat' => 'warn',
            ];
        }

        return $diffs;
    }

    private function formatManifesteDetails(ManifesteSg $manifeste): array
    {
        return [
            'instance_id' => $manifeste->instance_id,
            'ulid' => $manifeste->ulid,
            'num_manifeste' => $manifeste->num_manifeste,
            'numero_manifeste_complet' => $manifeste->numero_manifeste_complet,
            'identifiant' => $manifeste->identifiant,
            'code_bureau' => $manifeste->code_bureau,
            'libelle_bureau' => $manifeste->libelle_bureau,
            'annee_manifeste' => $manifeste->annee_manifeste,
            'num_man_sydam' => $manifeste->num_man_sydam,
            'num_voyage' => $manifeste->num_voyage,
            'date_voyage' => $manifeste->date_voyage?->toIso8601String(),
            'date_manifeste' => $manifeste->date_manifeste?->toIso8601String(),
            'date_arrivee_navire' => $manifeste->date_arrivee_navire?->toIso8601String(),
            'code_port_charg' => $manifeste->code_port_charg,
            'nom_port_charg' => $manifeste->nom_port_charg,
            'code_port_decharg' => $manifeste->code_port_decharg,
            'nom_port_decharg' => $manifeste->nom_port_decharg,
            'code_consignataire' => $manifeste->code_consignataire,
            'nom_consignataire' => $manifeste->nom_consignataire,
            'adresse_consignataire' => $manifeste->adresse_consignataire,
            'nom_moyen_transport' => $manifeste->nom_moyen_transport,
            'code_transport' => $manifeste->code_transport,
            'nom_transport' => $manifeste->nom_transport,
            'code_nationalite_navire' => $manifeste->code_nationalite_navire,
            'nom_nationalite_navire' => $manifeste->nom_nationalite_navire,
            'nbre_total_bl' => $manifeste->nbre_total_bl,
            'nbre_total_colis' => $manifeste->nbre_total_colis,
            'nbre_total_conteneur' => $manifeste->nbre_total_conteneur,
            'total_poids_brut' => $manifeste->total_poids_brut,
        ];
    }

    private function buildManifesteDeclarationFinancialDiffs(ManifesteSg $manifeste, DeclarationSg $declaration): array
    {
        $metrics = [
            'nbre_colis' => [
                'manifeste' => 'nbre_total_colis',
                'declaration' => 'nbre_colis',
                'label' => 'Nombre de colis',
            ],
            'poids_brut' => [
                'manifeste' => 'total_poids_brut',
                'declaration' => 'poids_brut_declaration',
                'label' => 'Poids brut',
            ],
            'nombre_conteneur' => [
                'manifeste' => 'nbre_total_conteneur',
                'declaration' => 'nombre_conteneur',
                'label' => 'Nombre de conteneurs',
            ],
        ];

        $diffs = [];

        foreach ($metrics as $key => $config) {
            $manifesteValue = $manifeste->{$config['manifeste']};
            $declarationValue = $declaration->{$config['declaration']};

            if ($manifesteValue == $declarationValue) {
                continue;
            }

            $manifesteFloat = (float) $manifesteValue;
            $declarationFloat = (float) $declarationValue;
            $delta = $manifesteFloat - $declarationFloat;
            $base = max(abs($manifesteFloat), abs($declarationFloat), 1);
            $percent = $base !== 0 ? $delta / $base : 0;

            $state = 'ok';
            $absPercent = abs($percent);
            if ($absPercent >= self::FINANCIAL_DANGER_THRESHOLD) {
                $state = 'danger';
            } elseif ($absPercent >= self::FINANCIAL_WARNING_THRESHOLD) {
                $state = 'warn';
            }

            $diffs[$key] = [
                'label' => $config['label'],
                'manifeste' => $manifesteValue,
                'declaration' => $declarationValue,
                'ecart' => number_format($delta, 4, '.', ''),
                'ecart_percent' => number_format($percent * 100, 2, '.', ''),
                'etat' => $state,
            ];
        }

        return $diffs;
    }

    private function buildManifesteDeclarationContextDiffs(ManifesteSg $manifeste, DeclarationSg $declaration): array
    {
        $fields = [
            'num_manifeste' => [
                'manifeste' => 'num_manifeste',
                'declaration' => 'num_manifeste',
                'label' => 'Numéro manifeste',
            ],
            'num_bl' => [
                'manifeste' => null, // Pas de num_bl direct dans manifeste, mais dans titres de transport
                'declaration' => 'num_bl',
                'label' => 'Numéro BL',
            ],
            'port_chargement' => [
                'manifeste' => 'nom_port_charg',
                'declaration' => 'nom_port_chargement',
                'label' => 'Port de chargement',
            ],
            'port_dechargement' => [
                'manifeste' => 'nom_port_decharg',
                'declaration' => 'nom_port_chargement', // Note: Declaration n'a qu'un port_chargement
                'label' => 'Port de déchargement',
            ],
            'navire' => [
                'manifeste' => null, // Pas de navire direct dans manifeste_sg
                'declaration' => 'nom_navire',
                'label' => 'Nom du navire',
            ],
            'consignataire' => [
                'manifeste' => 'nom_consignataire',
                'declaration' => null, // Pas de consignataire dans declaration
                'label' => 'Consignataire',
            ],
            'mode_transport' => [
                'manifeste' => 'nom_transport',
                'declaration' => 'nom_mode_transport',
                'label' => 'Mode de transport',
            ],
            'bureau' => [
                'manifeste' => 'code_bureau',
                'declaration' => 'bureau',
                'label' => 'Bureau douanier',
            ],
        ];

        $diffs = [];

        foreach ($fields as $key => $config) {
            $manifesteValue = $config['manifeste'] ? $manifeste->{$config['manifeste']} : null;
            $declarationValue = $config['declaration'] ? $declaration->{$config['declaration']} : null;

            // Skip si les deux sont null ou si les valeurs sont identiques
            if ($manifesteValue === null && $declarationValue === null) {
                continue;
            }

            if ($manifesteValue == $declarationValue) {
                continue;
            }

            $diffs[$key] = [
                'label' => $config['label'],
                'manifeste' => $manifesteValue,
                'declaration' => $declarationValue,
                'etat' => 'warn',
            ];
        }

        return $diffs;
    }

    private function formatBanqueSadDetails(BanqueSad $banque): array
    {
        // Construire un identifiant composite pour BanqueSad
        $identifiantParts = array_filter([
            $banque->num_ddu ? "DDU {$banque->num_ddu}" : null,
            $banque->num_man ? "MAN {$banque->num_man}" : null,
            $banque->num_dom ? "DOM {$banque->num_dom}" : null,
        ]);
        $identifiant = !empty($identifiantParts) ? implode(' / ', $identifiantParts) : ("SAD #{$banque->id}");

        return [
            'id' => $banque->id,
            'id_sad' => $banque->id_sad,
            'ulid' => $banque->ulid,
            'identifiant' => $identifiant,
            'ref_ddu' => $banque->ref_ddu,
            'num_ddu' => $banque->num_ddu,
            'date_ddu' => $banque->date_ddu?->toIso8601String(),
            'num_man' => $banque->num_man,
            'bl_ddu' => $banque->bl_ddu,
            'type_dec' => $banque->type_dec,
            'sous_regime' => $banque->sous_regime,
            'code_declarant' => $banque->code_declarant,
            'num_demande_ac' => $banque->num_demande_ac,
            'date_dem_ac' => $banque->date_dem_ac?->toIso8601String(),
            'statut_ac' => $banque->statut_ac,
            'base_sur' => $banque->base_sur,
            'num_dom' => $banque->num_dom,
            'date_dom' => $banque->date_dom?->toIso8601String(),
            'bank_dom' => $banque->bank_dom,
            'banq_enreg' => $banque->banq_enreg,
            'num_enreg_banq' => $banque->num_enreg_banq,
            'date_aprob_bank' => $banque->date_aprob_bank?->toIso8601String(),
            'date_aprob_bank2' => $banque->date_aprob_bank2?->toIso8601String(),
            'pays_exp' => $banque->pays_exp,
            'adr_exp' => $banque->adr_exp,
            'autorise_par' => $banque->autorise_par,
            'cda' => $banque->cda,
            'code_cda' => $banque->code_cda,
            'benef_fonds' => $banque->benef_fonds,
            'type_op' => $banque->type_op,
            'dev_trans' => $banque->dev_trans,
            'dev_paiem' => $banque->dev_paiem,
            'valeur_fob_ddu' => $banque->valeur_fob_ddu,
            'valeur_caf_ddu' => $banque->valeur_caf_ddu,
            'valeur_fret_ddu' => $banque->valeur_fret_ddu,
            'val_ass_ddu' => $banque->val_ass_ddu,
            'mont_ac_dev' => $banque->mont_ac_dev,
            'mont_ac_xof' => $banque->mont_ac_xof,
            'mont_fact_xof' => $banque->mont_fact_xof,
            'mont_fact_dev' => $banque->mont_fact_dev,
            'mont_tot_march_xof' => $banque->mont_tot_march_xof,
            'solde_dev' => $banque->solde_dev,
        ];
    }

    private function buildBanqueDeclarationFinancialDiffs(BanqueSad $banque, DeclarationSg $declaration): array
    {
        $metrics = [
            'valeur_caf' => [
                'banque' => 'valeur_caf_ddu',
                'declaration' => 'valeur_caf_declaration',
                'label' => 'Valeur CAF',
            ],
            'valeur_fob' => [
                'banque' => 'valeur_fob_ddu',
                'declaration' => 'valeur_fob_declaration',
                'label' => 'Valeur FOB',
            ],
            'fret' => [
                'banque' => 'valeur_fret_ddu',
                'declaration' => null, // Pas de fret direct dans declaration
                'label' => 'Fret',
            ],
            'assurance' => [
                'banque' => 'val_ass_ddu',
                'declaration' => null, // Pas d'assurance directe dans declaration
                'label' => 'Assurance',
            ],
        ];

        $diffs = [];

        foreach ($metrics as $key => $config) {
            $banqueValue = $config['banque'] ? $banque->{$config['banque']} : null;
            $declarationValue = $config['declaration'] ? $declaration->{$config['declaration']} : null;

            // Skip si les deux sont null
            if ($banqueValue === null && $declarationValue === null) {
                continue;
            }

            // Si l'un est null et l'autre non, c'est une différence
            if ($banqueValue === null || $declarationValue === null) {
                $diffs[$key] = [
                    'label' => $config['label'],
                    'banque' => $banqueValue,
                    'declaration' => $declarationValue,
                    'ecart' => null,
                    'ecart_percent' => null,
                    'etat' => 'warn',
                ];
                continue;
            }

            if ($banqueValue == $declarationValue) {
                continue;
            }

            $banqueFloat = (float) $banqueValue;
            $declarationFloat = (float) $declarationValue;
            $delta = $banqueFloat - $declarationFloat;
            $base = max(abs($banqueFloat), abs($declarationFloat), 1);
            $percent = $base !== 0 ? $delta / $base : 0;

            $state = 'ok';
            $absPercent = abs($percent);
            if ($absPercent >= self::FINANCIAL_DANGER_THRESHOLD) {
                $state = 'danger';
            } elseif ($absPercent >= self::FINANCIAL_WARNING_THRESHOLD) {
                $state = 'warn';
            }

            $diffs[$key] = [
                'label' => $config['label'],
                'banque' => $banqueValue,
                'declaration' => $declarationValue,
                'ecart' => number_format($delta, 4, '.', ''),
                'ecart_percent' => number_format($percent * 100, 2, '.', ''),
                'etat' => $state,
            ];
        }

        return $diffs;
    }

    private function buildBanqueDeclarationContextDiffs(BanqueSad $banque, DeclarationSg $declaration): array
    {
        $fields = [
            'num_ddu' => [
                'banque' => 'ref_ddu',
                'declaration' => 'declaration',
                'label' => 'Numéro DDU / Déclaration',
            ],
            'num_dom' => [
                'banque' => 'num_dom',
                'declaration' => 'num_dossier',
                'label' => 'Numéro de domiciliation',
            ],
            'date_dom' => [
                'banque' => 'date_dom',
                'declaration' => 'date_quittance',
                'label' => 'Date de domiciliation / Quittance',
            ],
            'bank_dom' => [
                'banque' => 'bank_dom',
                'declaration' => 'banq_code',
                'label' => 'Banque de domiciliation',
            ],
            'num_manifeste' => [
                'banque' => 'num_man',
                'declaration' => 'num_manifeste',
                'label' => 'Numéro manifeste',
            ],
            'statut_ac' => [
                'banque' => 'statut_ac',
                'declaration' => null, // Pas de statut AC dans declaration
                'label' => 'Statut AC',
            ],
            'pays_exp' => [
                'banque' => 'pays_exp',
                'declaration' => 'provenance',
                'label' => 'Pays expéditeur / Provenance',
            ],
        ];

        $diffs = [];

        foreach ($fields as $key => $config) {
            $banqueValue = $config['banque'] ? $banque->{$config['banque']} : null;
            $declarationValue = $config['declaration'] ? $declaration->{$config['declaration']} : null;

            // Skip si les deux sont null
            if ($banqueValue === null && $declarationValue === null) {
                continue;
            }

            if ($banqueValue == $declarationValue) {
                continue;
            }

            $diffs[$key] = [
                'label' => $config['label'],
                'banque' => $banqueValue,
                'declaration' => $declarationValue,
                'etat' => 'warn',
            ];
        }

        return $diffs;
    }
}



