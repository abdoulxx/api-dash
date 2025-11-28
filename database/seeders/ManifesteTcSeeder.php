<?php

namespace Database\Seeders;

use App\Models\ManifesteTc;
use Illuminate\Database\Seeder;

class ManifesteTcSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Vérifier si le manifeste MAN-2024-012 existe
        $numManifeste = 'MAN-2024-012';
        
        // Créer un conteneur de test
        ManifesteTc::create([
            'instance_id' => (int) time(), // instance_id est un integer
            'num_manifeste' => $numManifeste,
            'num_conteneur' => 'CONT-001',
            'taille_conteneur' => '20',
            'plomb1' => 'PLOMB-001',
            'plomb2' => 'PLOMB-002',
            'nombre_conteneur' => 1,
            'poids_brut' => 15000.50,
            'poids_restant' => 15000.50,
            'nombre_colis' => 100,
            'nbre_colis_conteneur' => 100,
            'code_bureau' => 'ABJ',
            'nom_bureau' => 'Abidjan',
            'date_arrive' => now(),
            'date_manifeste' => now(),
            'annee_manifeste' => 2024,
        ]);
        
        $this->command->info("Conteneur de test créé pour le manifeste {$numManifeste}");
    }
}









