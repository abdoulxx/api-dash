<?php

namespace Database\Seeders;

use App\Models\DeclarationSg;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;

class DeclarationSgSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $sqlFile = base_path('../s360_ analyse/declaration_sg_inserts.sql');
        
        if (!File::exists($sqlFile)) {
            $this->command->warn("Fichier SQL non trouvé : {$sqlFile}");
            $this->command->info("Création de quelques enregistrements de test...");
            $this->createTestData();
            return;
        }

        // Vérifier si des données existent déjà
        $existingCount = DeclarationSg::count();
        if ($existingCount > 0) {
            $this->command->info("Suppression de {$existingCount} enregistrement(s) existant(s)...");
            DeclarationSg::truncate();
        }

        $this->command->info("Importation des données depuis le fichier SQL avec ULIDs...");
        
        // Lire le fichier SQL
        $content = File::get($sqlFile);
        
        // Extraire les lignes INSERT
        preg_match_all('/INSERT INTO DECLARATION_SG VALUES \((.*?)\);/s', $content, $matches);
        
        $count = 0;
        $errors = 0;
        
        foreach ($matches[1] as $valuesString) {
            try {
                // Parser les valeurs
                $values = $this->parseValues($valuesString);
                
                // Fonction helper pour parser les dates
                $parseDate = function($value) {
                    if (!$value || $value === 'NULL' || strlen($value) < 10) {
                        return null;
                    }
                    // Vérifier si c'est une date valide (format YYYY-MM-DD ou YYYY-MM-DD HH:MM:SS)
                    if (preg_match('/^\d{4}-\d{2}-\d{2}/', $value)) {
                        try {
                            return \Carbon\Carbon::parse($value);
                        } catch (\Exception $e) {
                            return null;
                        }
                    }
                    return null;
                };
                
                // Helper pour tronquer les chaînes selon la longueur max
                $truncate = function($value, $maxLength) {
                    if ($value === null) return null;
                    $value = (string) $value;
                    return mb_substr($value, 0, $maxLength);
                };
                
                // Créer l'enregistrement avec Eloquent pour générer automatiquement l'ULID
                DeclarationSg::create([
                    'instanceid' => $values[0] ?? null,
                    'entrepot' => $truncate($values[1] ?? null, 20),
                    'annee' => is_numeric($values[2] ?? null) ? (int) $values[2] : null,
                    'num_manifeste' => $truncate($values[3] ?? null, 128),
                    'num_fdi' => $truncate($values[4] ?? null, 60),
                    'num_bl' => $truncate($values[5] ?? null, 30),
                    'typdec' => $truncate($values[6] ?? null, 20),
                    'sens' => $truncate($values[7] ?? null, 4), // VARCHAR(4)
                    'num_dossier' => $truncate($values[8] ?? null, 20),
                    'declaration' => $truncate($values[9] ?? null, 384),
                    'date_declaration' => $parseDate($values[10] ?? null),
                    'provenance' => $truncate($values[11] ?? null, 4), // VARCHAR(4)
                    'destination' => $truncate($values[12] ?? null, 4), // VARCHAR(4)
                    'code_port_chargement' => $truncate($values[13] ?? null, 20),
                    'nom_port_chargement' => $truncate($values[14] ?? null, 140),
                    'code_mode_transport' => $truncate($values[15] ?? null, 4), // VARCHAR(4)
                    'nom_mode_transport' => $truncate($values[16] ?? null, 140),
                    'nom_navire' => $truncate($values[17] ?? null, 200),
                    'condition_liv' => $truncate($values[18] ?? null, 4), // VARCHAR(4)
                    'devise' => $truncate($values[19] ?? null, 12),
                    'taux_conversion' => is_numeric($values[20] ?? null) ? (float) $values[20] : null,
                    'banq_code' => $truncate($values[21] ?? null, 20),
                    'bureau' => $truncate($values[22] ?? null, 20),
                    'nom_bureau' => $truncate($values[23] ?? null, 140),
                    'cc_exp' => $truncate($values[24] ?? null, 20),
                    'exportateur' => $truncate($values[25] ?? null, 600),
                    'cc_imp' => $truncate($values[26] ?? null, 20),
                    'importateur' => $truncate($values[27] ?? null, 600),
                    'code_destinataire_reel' => $truncate($values[28] ?? null, 20),
                    'nom_destinataire_reel' => $truncate($values[29] ?? null, 600),
                    'codagr' => $truncate($values[30] ?? null, 20),
                    'declarant' => $truncate($values[31] ?? null, 600),
                    'sous_regime' => $truncate($values[32] ?? null, 4), // VARCHAR(4)
                    'nbre_total_article' => is_numeric($values[33] ?? null) ? (int) $values[33] : null,
                    'quittance' => $truncate($values[34] ?? null, 8), // VARCHAR(8)
                    'date_quittance' => $parseDate($values[35] ?? null),
                    'valeur_caf_declaration' => is_numeric($values[36] ?? null) ? (float) $values[36] : null,
                    'nbre_colis' => is_numeric($values[37] ?? null) ? (float) $values[37] : null,
                    'valeur_fob_declaration' => is_numeric($values[38] ?? null) ? (float) $values[38] : null,
                    'droits_taxes_declaration' => is_numeric($values[39] ?? null) ? (float) $values[39] : null,
                    'poids_brut_declaration' => is_numeric($values[40] ?? null) ? (float) $values[40] : null,
                    'nombre_conteneur' => is_numeric($values[41] ?? null) ? (float) $values[41] : null,
                ]);
                
                $count++;
                
                if ($count % 100 === 0) {
                    $this->command->info("Importé {$count} déclarations avec ULIDs...");
                }
            } catch (\Exception $e) {
                $errors++;
                if ($errors <= 10) {
                    $this->command->warn("Erreur ligne " . ($count + $errors) . ": " . $e->getMessage());
                }
            }
        }
        
        $this->command->info("{$count} déclaration(s) importée(s) avec ULIDs avec succès" . ($errors > 0 ? " ({$errors} erreur(s))" : ""));
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
        DeclarationSg::create([
            'instanceid' => (int) time(),
            'annee' => 2024,
            'num_manifeste' => 'MAN-2024-012',
            'num_fdi' => 'FDI-2024-001',
            'num_bl' => 'BL-2024-001',
            'declaration' => 'DEC-2024-001',
            'date_declaration' => now(),
            'typdec' => 'IMPORT',
            'sens' => 'ENT',
            'bureau' => 'ABJ',
            'nom_bureau' => 'Abidjan',
            'code_mode_transport' => 'MAR',
            'nom_mode_transport' => 'Maritime',
            'devise' => 'XOF',
            'taux_conversion' => 1.0,
            'nbre_total_article' => 5,
        ]);
        
        $this->command->info('1 déclaration de test créée');
    }
}








