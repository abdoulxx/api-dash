<?php

namespace Database\Seeders;

use App\Models\DeclarationArticle;
use App\Models\DeclarationSg;
use Illuminate\Database\Seeder;

class DeclarationArticleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $declaration = DeclarationSg::where('ulid', '01KAZVZYGYEB3NV8T2ERD86AJ0')->first();

        if (!$declaration) {
            $this->command->error('Déclaration avec ULID 01KAZVZYGYEB3NV8T2ERD86AJ0 non trouvée');
            return;
        }

        // Vérifier si un article existe déjà
        $existingArticle = DeclarationArticle::where('declaration', $declaration->declaration)
            ->where('numero_article', 1)
            ->first();

        if ($existingArticle) {
            $this->command->info('Un article existe déjà pour cette déclaration');
            return;
        }

        // Créer un article de déclaration basé sur la structure de s360_analyse
        DeclarationArticle::create([
            'instanceid' => $declaration->instanceid ?? 999999,
            'entrepot' => $declaration->entrepot,
            'annee' => $declaration->annee,
            'num_manifeste' => $declaration->num_manifeste,
            'num_bl' => $declaration->num_bl,
            'typdec' => $declaration->typdec,
            'sens' => $declaration->sens ?? 'I',
            'num_dossier' => $declaration->num_dossier,
            'declaration' => $declaration->declaration,
            'date_declaration' => $declaration->date_declaration,
            'provenance' => $declaration->provenance,
            'origine' => $declaration->provenance,
            'destination' => $declaration->destination,
            'code_port_chargement' => $declaration->code_port_chargement,
            'nom_port_chargement' => $declaration->nom_port_chargement,
            'code_mode_transport' => $declaration->code_mode_transport,
            'nom_mode_transport' => $declaration->nom_mode_transport,
            'nom_navire' => $declaration->nom_navire,
            'condition_liv' => $declaration->condition_liv,
            'devise' => $declaration->devise,
            'taux_conversion' => $declaration->taux_conversion,
            'banq_code' => $declaration->banq_code,
            'bureau' => $declaration->bureau,
            'nom_bureau' => $declaration->nom_bureau,
            'cc_exp' => $declaration->cc_exp,
            'exportateur' => $declaration->exportateur,
            'cc_imp' => $declaration->cc_imp,
            'importateur' => $declaration->importateur,
            'code_destinataire_reel' => $declaration->code_destinataire_reel,
            'nom_destinataire_reel' => $declaration->nom_destinataire_reel,
            'codagr' => $declaration->codagr,
            'declarant' => $declaration->declarant,
            'postar' => '8471300000', // Code SH exemple
            'libelle_postar' => 'Moteurs et générateurs électriques',
            'marque1' => null,
            'marque2' => null,
            'emballage' => 'Colis ("package")',
            'unite_apurement' => null,
            'nbre_colis' => 10,
            'nbre_total_article' => 1,
            'sous_regime' => $declaration->sous_regime,
            'numero_article' => 1,
            'quittance' => $declaration->quittance,
            'date_quittance' => $declaration->date_quittance,
            'valcaf' => $declaration->valeur_caf_declaration ?? 0,
            'valfob' => $declaration->valeur_fob_declaration ?? 0,
            'valdou' => 0,
            'valtax' => 0,
            'valfret_ext' => 0,
            'valfret_int' => 0,
            'valass' => 0,
            'autre_cout' => 0,
            'droits_taxes' => $declaration->droits_taxes_declaration ?? 0,
            'taxe_susp' => 0,
            'poids_net' => 5000, // 5 tonnes
            'poids_brut' => 5500, // 5.5 tonnes
            'credit_paiement' => null,
            'mode_paiement' => null,
            'date_annulation' => null,
        ]);

        $this->command->info("Article créé avec succès pour la déclaration {$declaration->declaration}");
    }
}
