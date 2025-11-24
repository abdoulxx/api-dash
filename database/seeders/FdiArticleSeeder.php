<?php

namespace Database\Seeders;

use App\Models\FdiArticle;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;

class FdiArticleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $sqlFile = base_path('../s360_ analyse/fdi_article_inserts.sql');
        
        if (!File::exists($sqlFile)) {
            $this->command->warn("Fichier SQL non trouvé : {$sqlFile}");
            $this->command->info("Création de quelques enregistrements de test...");
            $this->createTestData();
            return;
        }

        // Vider la table si elle contient déjà des données
        $existingCount = FdiArticle::count();
        if ($existingCount > 0) {
            $this->command->info("Suppression de {$existingCount} enregistrement(s) existant(s)...");
            FdiArticle::truncate();
        }

        $this->command->info("Importation des données depuis le fichier SQL avec ULIDs...");
        
        // Lire le fichier SQL
        $content = File::get($sqlFile);
        
        // Extraire les lignes INSERT (gérer les lignes multi-lignes)
        preg_match_all('/INSERT INTO FDI_ARTICLE VALUES \((.*?)\);/s', $content, $matches);
        
        $count = 0;
        $errors = 0;
        
        foreach ($matches[1] as $valuesString) {
            try {
                // Parser les valeurs
                $values = $this->parseValues($valuesString);
                
                // Créer l'enregistrement avec Eloquent pour générer automatiquement l'ULID
                FdiArticle::create([
                    'instance_id' => $this->parseValue($values[0] ?? null),
                    'serie_fdi' => $this->parseValue($values[1] ?? null),
                    'bureau' => $this->parseValue($values[2] ?? null),
                    'annee' => $this->parseInteger($values[3] ?? null),
                    'numero_serie' => $this->parseValue($values[4] ?? null),
                    'numero_fdi' => $this->parseValue($values[5] ?? null),
                    'date_fdi' => $this->parseDate($values[6] ?? null),
                    'numart' => $this->parseInteger($values[7] ?? null),
                    'postar' => $this->parseValue($values[8] ?? null),
                    'nature_marchandise' => $this->parseValue($values[9] ?? null),
                    'description_marchandise' => $this->parseValue($values[10] ?? null),
                    'quantite' => $this->parseDecimal($values[11] ?? null),
                    'poids_net' => $this->parseDecimal($values[12] ?? null),
                    'poids_brut' => $this->parseDecimal($values[13] ?? null),
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
        
        $this->command->info("✅ {$count} enregistrement(s) FDI Article importé(s) avec ULIDs avec succès" . ($errors > 0 ? " ({$errors} erreur(s))" : ""));
    }
    
    private function parseValues($valuesString)
    {
        $values = [];
        $current = '';
        $inQuotes = false;
        $quoteChar = null;
        
        // Nettoyer les sauts de ligne dans les valeurs
        $valuesString = str_replace(["\r\n", "\n", "\r"], ' ', $valuesString);
        
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
        
        return $values;
    }
    
    private function parseValue($value)
    {
        if ($value === null || $value === 'NULL' || $value === '') {
            return null;
        }
        
        $value = trim($value);
        
        // Enlever les guillemets
        if (($value[0] === '"' && substr($value, -1) === '"') || ($value[0] === "'" && substr($value, -1) === "'")) {
            $value = substr($value, 1, -1);
        }
        
        // Gérer les doubles guillemets échappés
        $value = str_replace("''", "'", $value);
        
        return $value === '' ? null : $value;
    }
    
    private function parseDate($value)
    {
        $parsed = $this->parseValue($value);
        if (!$parsed) {
            return null;
        }
        
        try {
            return \Carbon\Carbon::parse($parsed);
        } catch (\Exception $e) {
            return null;
        }
    }
    
    private function parseDecimal($value)
    {
        $parsed = $this->parseValue($value);
        if ($parsed === null || $parsed === 'NULL') {
            return null;
        }
        
        return is_numeric($parsed) ? (float) $parsed : null;
    }
    
    private function parseInteger($value)
    {
        $parsed = $this->parseValue($value);
        if ($parsed === null || $parsed === 'NULL') {
            return null;
        }
        
        return is_numeric($parsed) ? (int) $parsed : null;
    }
    
    private function createTestData()
    {
        FdiArticle::create([
            'instance_id' => 797355,
            'serie_fdi' => 'A',
            'bureau' => 'CIAB1',
            'annee' => 2020,
            'numero_serie' => '000353',
            'numero_fdi' => '474',
            'date_fdi' => '2020-01-03 00:00:00',
            'numart' => 54,
            'postar' => '3401300000',
            'nature_marchandise' => null,
            'description_marchandise' => null,
            'quantite' => 24,
            'poids_net' => 0,
            'poids_brut' => 0,
        ]);
        
        $this->command->info('1 enregistrement FDI Article de test créé');
    }
}
