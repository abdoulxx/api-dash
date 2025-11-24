<?php

namespace Database\Seeders;

use App\Models\BonProvisoireSg;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class BonProvisoireSgSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $sqlFile = base_path('../s360_ analyse/bon_provisoire_sg_inserts.sql');
        
        if (!File::exists($sqlFile)) {
            $this->command->warn("Fichier SQL non trouvé : {$sqlFile}");
            $this->command->info("Création de quelques enregistrements de test...");
            $this->createTestData();
            return;
        }

        // Vider la table si elle contient déjà des données
        $existingCount = BonProvisoireSg::count();
        if ($existingCount > 0) {
            $this->command->info("Suppression de {$existingCount} enregistrement(s) existant(s)...");
            BonProvisoireSg::truncate();
        }

        $this->command->info("Importation des données depuis le fichier SQL avec ULIDs...");
        
        // Lire le fichier SQL
        $content = File::get($sqlFile);
        
        // Extraire les lignes INSERT
        preg_match_all('/INSERT INTO BON_PROVISOIRE_SG VALUES \((.*?)\);/s', $content, $matches);
        
        $count = 0;
        $errors = 0;
        
        foreach ($matches[1] as $valuesString) {
            try {
                // Parser les valeurs
                $values = $this->parseValues($valuesString);
                
                // Créer l'enregistrement avec Eloquent pour générer automatiquement l'ULID
                BonProvisoireSg::create([
                    'instance_id' => $values[0] ?? null,
                    'annee' => $values[1] ?? null,
                    'bureau' => $values[2] ?? null,
                    'serie_bp' => $values[3] ?? null,
                    'num_serie_bp' => $values[4] ?? null,
                    'numero_bon_provisoire' => $values[5] ?? null,
                    'date_bp' => $values[6] && $values[6] !== 'NULL' ? $values[6] : null,
                    'num_vol_lta' => $values[7] ?? null,
                    'num_lta' => $values[8] ?? null,
                    'date_lta' => $values[9] && $values[9] !== 'NULL' ? $values[9] : null,
                    'date_expiration' => $values[10] && $values[10] !== 'NULL' ? $values[10] : null,
                    'delai_jours' => $values[11] ?? null,
                    'ncc' => $values[12] ?? null,
                    'nom_importateur' => $values[13] ?? null,
                    'nom_fournisseur' => $values[14] ?? null,
                    'pays_origine' => $values[15] ?? null,
                    'code_declarant' => $values[16] ?? null,
                    'nom_declarant' => $values[17] ?? null,
                    'type_bon_provisoire' => $values[18] ?? null,
                ]);
                
                $count++;
                
                if ($count % 100 === 0) {
                    $this->command->info("Importé {$count} enregistrements avec ULIDs...");
                }
            } catch (\Exception $e) {
                $errors++;
                if ($errors <= 10) { // Afficher seulement les 10 premières erreurs
                    $this->command->warn("Erreur ligne " . ($count + $errors) . ": " . $e->getMessage());
                }
            }
        }
        
        $this->command->info("{$count} bon(s) provisoire(s) importé(s) avec ULIDs avec succès" . ($errors > 0 ? " ({$errors} erreur(s))" : ""));
    }
    
    private function parseValues($valuesString)
    {
        $values = [];
        $current = '';
        $inQuotes = false;
        $quoteChar = null;
        
        for ($i = 0; $i < strlen($valuesString); $i++) {
            $char = $valuesString[$i];
            
            if (($char === '"' || $char === "'") && ($i === 0 || $valuesString[$i - 1] !== '\\')) {
                if (!$inQuotes) {
                    $inQuotes = true;
                    $quoteChar = $char;
                } elseif ($char === $quoteChar) {
                    $inQuotes = false;
                    $quoteChar = null;
                } else {
                    $current .= $char;
                }
            } elseif ($char === ',' && !$inQuotes) {
                $values[] = trim($current);
                $current = '';
            } else {
                $current .= $char;
            }
        }
        
        if ($current !== '') {
            $values[] = trim($current);
        }
        
        return array_map(function($value) {
            $value = trim($value);
            if ($value === 'NULL' || $value === '') {
                return null;
            }
            // Enlever les guillemets
            if (($value[0] === '"' && $value[-1] === '"') || ($value[0] === "'" && $value[-1] === "'")) {
                $value = substr($value, 1, -1);
            }
            // Gérer les doubles guillemets échappés
            $value = str_replace("''", "'", $value);
            return $value;
        }, $values);
    }
    
    private function createTestData()
    {
        BonProvisoireSg::create([
            'instance_id' => 91679,
            'annee' => 2021,
            'bureau' => 'CIAB3',
            'serie_bp' => 'B',
            'num_serie_bp' => '1197',
            'numero_bon_provisoire' => '2021CIAB3B1197',
            'date_bp' => '2021-02-26 00:00:00',
            'num_vol_lta' => 'EK787',
            'num_lta' => '17680476922',
            'date_lta' => '2014-04-07 00:00:00',
            'date_expiration' => '2021-03-13 00:00:00',
            'delai_jours' => 15,
            'ncc' => '4114941P',
            'nom_importateur' => 'GROUPE IVOIRIEN DU MEDICAL',
            'nom_fournisseur' => 'LABOR DIAGNOSTIC SYSTEM',
            'pays_origine' => 'Allemagne',
            'code_declarant' => '00373L',
            'nom_declarant' => 'FRANCE AFRIQUE TRANSIT CI',
            'type_bon_provisoire' => 'IMP',
        ]);
        
        $this->command->info('1 bon provisoire de test créé');
    }
}

