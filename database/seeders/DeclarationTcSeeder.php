<?php

namespace Database\Seeders;

use App\Models\DeclarationSg;
use App\Models\DeclarationTc;
use Illuminate\Database\Seeder;

class DeclarationTcSeeder extends Seeder
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
            $this->command->warn("Déclaration DEC-2024-004 non trouvée. Création de conteneurs pour la première déclaration...");
            $declaration = DeclarationSg::first();
        }

        if (!$declaration) {
            $this->command->error("Aucune déclaration trouvée pour créer des conteneurs.");
            return;
        }

        $this->command->info("Création de conteneurs pour la déclaration: {$declaration->declaration} (ULID: {$declaration->ulid})");

        // Supprimer les conteneurs existants pour cette déclaration pour éviter les doublons
        DeclarationTc::where('num_manifeste', $declaration->num_manifeste)
            ->orWhere('instanceid', $declaration->instanceid)
            ->forceDelete();

        $conteneursData = [
            [
                'instanceid' => $declaration->instanceid ?? 9999994,
                'annee' => $declaration->annee ?? 2024,
                'num_manifeste' => $declaration->num_manifeste,
                'num_bl' => $declaration->num_bl,
                'numenr' => 1,
                'numero_conteneur' => 'CONT-2024-001',
                'taille_conteneur' => '20',
            ],
            [
                'instanceid' => $declaration->instanceid ?? 9999994,
                'annee' => $declaration->annee ?? 2024,
                'num_manifeste' => $declaration->num_manifeste,
                'num_bl' => $declaration->num_bl,
                'numenr' => 2,
                'numero_conteneur' => 'CONT-2024-002',
                'taille_conteneur' => '40',
            ],
            [
                'instanceid' => $declaration->instanceid ?? 9999994,
                'annee' => $declaration->annee ?? 2024,
                'num_manifeste' => $declaration->num_manifeste,
                'num_bl' => $declaration->num_bl,
                'numenr' => 3,
                'numero_conteneur' => 'CONT-2024-003',
                'taille_conteneur' => '20',
            ],
        ];

        foreach ($conteneursData as $data) {
            DeclarationTc::create($data);
        }

        $this->command->info(count($conteneursData) . " conteneur(s) créé(s) avec succès pour la déclaration {$declaration->declaration}");
    }
}

