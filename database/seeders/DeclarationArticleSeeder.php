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
        // Récupérer la déclaration DEC-2024-004
        $declaration = DeclarationSg::where('ulid', '01KAPCP5EZKJ96JJ938GQFVBB2')
            ->orWhere('declaration', 'DEC-2024-004')
            ->first();

        if (!$declaration) {
            $this->command->warn("Déclaration DEC-2024-004 non trouvée. Création d'articles pour la première déclaration...");
            $declaration = DeclarationSg::first();
        }

        if (!$declaration) {
            $this->command->error("Aucune déclaration trouvée pour créer des articles.");
            return;
        }

        $this->command->info("Création d'articles pour la déclaration: {$declaration->declaration} (ULID: {$declaration->ulid})");

        // Supprimer les articles existants pour cette déclaration pour éviter les doublons
        DeclarationArticle::where('declaration', $declaration->declaration)->forceDelete();

        $articlesData = [
            [
                'instanceid' => $declaration->instanceid ?? 9999994,
                'annee' => $declaration->annee ?? 2024,
                'num_manifeste' => $declaration->num_manifeste,
                'num_bl' => $declaration->num_bl,
                'declaration' => $declaration->declaration,
                'date_declaration' => $declaration->date_declaration,
                'typdec' => $declaration->typdec ?? 'IMPORT',
                'sens' => $declaration->sens ?? 'ENT',
                'provenance' => $declaration->provenance,
                'destination' => $declaration->destination,
                'code_port_chargement' => $declaration->code_port_chargement,
                'nom_port_chargement' => $declaration->nom_port_chargement,
                'code_mode_transport' => $declaration->code_mode_transport,
                'nom_mode_transport' => $declaration->nom_mode_transport,
                'nom_navire' => $declaration->nom_navire,
                'condition_liv' => $declaration->condition_liv,
                'devise' => $declaration->devise ?? 'EUR',
                'taux_conversion' => $declaration->taux_conversion ?? 655.957,
                'bureau' => $declaration->bureau,
                'nom_bureau' => $declaration->nom_bureau,
                'importateur' => $declaration->importateur,
                'exportateur' => $declaration->exportateur,
                'declarant' => $declaration->declarant,
                'postar' => '8708.10.00',
                'libelle_postar' => 'Équipements de traitement des eaux',
                'numero_article' => 1,
                'nbre_colis' => 25.00,
                'valcaf' => 15000000.00,
                'valfob' => 13500000.00,
                'droits_taxes' => 2500000.00,
                'poids_net' => 1250.50,
                'poids_brut' => 1500.00,
            ],
            [
                'instanceid' => $declaration->instanceid ?? 9999994,
                'annee' => $declaration->annee ?? 2024,
                'num_manifeste' => $declaration->num_manifeste,
                'num_bl' => $declaration->num_bl,
                'declaration' => $declaration->declaration,
                'date_declaration' => $declaration->date_declaration,
                'typdec' => $declaration->typdec ?? 'IMPORT',
                'sens' => $declaration->sens ?? 'ENT',
                'provenance' => $declaration->provenance,
                'destination' => $declaration->destination,
                'code_port_chargement' => $declaration->code_port_chargement,
                'nom_port_chargement' => $declaration->nom_port_chargement,
                'code_mode_transport' => $declaration->code_mode_transport,
                'nom_mode_transport' => $declaration->nom_mode_transport,
                'nom_navire' => $declaration->nom_navire,
                'condition_liv' => $declaration->condition_liv,
                'devise' => $declaration->devise ?? 'EUR',
                'taux_conversion' => $declaration->taux_conversion ?? 655.957,
                'bureau' => $declaration->bureau,
                'nom_bureau' => $declaration->nom_bureau,
                'importateur' => $declaration->importateur,
                'exportateur' => $declaration->exportateur,
                'declarant' => $declaration->declarant,
                'postar' => '8414.80.10',
                'libelle_postar' => 'Pompes centrifuges pour liquides',
                'numero_article' => 2,
                'nbre_colis' => 15.00,
                'valcaf' => 8500000.00,
                'valfob' => 7500000.00,
                'droits_taxes' => 1200000.00,
                'poids_net' => 850.25,
                'poids_brut' => 1000.00,
            ],
            [
                'instanceid' => $declaration->instanceid ?? 9999994,
                'annee' => $declaration->annee ?? 2024,
                'num_manifeste' => $declaration->num_manifeste,
                'num_bl' => $declaration->num_bl,
                'declaration' => $declaration->declaration,
                'date_declaration' => $declaration->date_declaration,
                'typdec' => $declaration->typdec ?? 'IMPORT',
                'sens' => $declaration->sens ?? 'ENT',
                'provenance' => $declaration->provenance,
                'destination' => $declaration->destination,
                'code_port_chargement' => $declaration->code_port_chargement,
                'nom_port_chargement' => $declaration->nom_port_chargement,
                'code_mode_transport' => $declaration->code_mode_transport,
                'nom_mode_transport' => $declaration->nom_mode_transport,
                'nom_navire' => $declaration->nom_navire,
                'condition_liv' => $declaration->condition_liv,
                'devise' => $declaration->devise ?? 'EUR',
                'taux_conversion' => $declaration->taux_conversion ?? 655.957,
                'bureau' => $declaration->bureau,
                'nom_bureau' => $declaration->nom_bureau,
                'importateur' => $declaration->importateur,
                'exportateur' => $declaration->exportateur,
                'declarant' => $declaration->declarant,
                'postar' => '7308.90.00',
                'libelle_postar' => 'Structures et parties de structures en acier',
                'numero_article' => 3,
                'nbre_colis' => 10.00,
                'valcaf' => 20000000.00,
                'valfob' => 18000000.00,
                'droits_taxes' => 3000000.00,
                'poids_net' => 5000.00,
                'poids_brut' => 5500.00,
            ],
            [
                'instanceid' => $declaration->instanceid ?? 9999994,
                'annee' => $declaration->annee ?? 2024,
                'num_manifeste' => $declaration->num_manifeste,
                'num_bl' => $declaration->num_bl,
                'declaration' => $declaration->declaration,
                'date_declaration' => $declaration->date_declaration,
                'typdec' => $declaration->typdec ?? 'IMPORT',
                'sens' => $declaration->sens ?? 'ENT',
                'provenance' => $declaration->provenance,
                'destination' => $declaration->destination,
                'code_port_chargement' => $declaration->code_port_chargement,
                'nom_port_chargement' => $declaration->nom_port_chargement,
                'code_mode_transport' => $declaration->code_mode_transport,
                'nom_mode_transport' => $declaration->nom_mode_transport,
                'nom_navire' => $declaration->nom_navire,
                'condition_liv' => $declaration->condition_liv,
                'devise' => $declaration->devise ?? 'EUR',
                'taux_conversion' => $declaration->taux_conversion ?? 655.957,
                'bureau' => $declaration->bureau,
                'nom_bureau' => $declaration->nom_bureau,
                'importateur' => $declaration->importateur,
                'exportateur' => $declaration->exportateur,
                'declarant' => $declaration->declarant,
                'postar' => '8471.30.00',
                'libelle_postar' => 'Ordinateurs portables et tablettes',
                'numero_article' => 4,
                'nbre_colis' => 50.00,
                'valcaf' => 30000000.00,
                'valfob' => 27000000.00,
                'droits_taxes' => 4500000.00,
                'poids_net' => 150.00,
                'poids_brut' => 200.00,
            ],
            [
                'instanceid' => $declaration->instanceid ?? 9999994,
                'annee' => $declaration->annee ?? 2024,
                'num_manifeste' => $declaration->num_manifeste,
                'num_bl' => $declaration->num_bl,
                'declaration' => $declaration->declaration,
                'date_declaration' => $declaration->date_declaration,
                'typdec' => $declaration->typdec ?? 'IMPORT',
                'sens' => $declaration->sens ?? 'ENT',
                'provenance' => $declaration->provenance,
                'destination' => $declaration->destination,
                'code_port_chargement' => $declaration->code_port_chargement,
                'nom_port_chargement' => $declaration->nom_port_chargement,
                'code_mode_transport' => $declaration->code_mode_transport,
                'nom_mode_transport' => $declaration->nom_mode_transport,
                'nom_navire' => $declaration->nom_navire,
                'condition_liv' => $declaration->condition_liv,
                'devise' => $declaration->devise ?? 'EUR',
                'taux_conversion' => $declaration->taux_conversion ?? 655.957,
                'bureau' => $declaration->bureau,
                'nom_bureau' => $declaration->nom_bureau,
                'importateur' => $declaration->importateur,
                'exportateur' => $declaration->exportateur,
                'declarant' => $declaration->declarant,
                'postar' => '8517.12.00',
                'libelle_postar' => 'Téléphones portables et smartphones',
                'numero_article' => 5,
                'nbre_colis' => 100.00,
                'valcaf' => 25000000.00,
                'valfob' => 22500000.00,
                'droits_taxes' => 3750000.00,
                'poids_net' => 75.00,
                'poids_brut' => 100.00,
            ],
        ];

        foreach ($articlesData as $data) {
            DeclarationArticle::create($data);
        }

        $this->command->info(count($articlesData) . " article(s) créé(s) avec succès pour la déclaration {$declaration->declaration}");
    }
}

