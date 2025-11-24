<?php

namespace Database\Seeders;

use App\Models\ManifesteSg;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;

class ManifesteSgSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $sqlFile = base_path('../s360_ analyse/manifeste_sg_inserts.sql');
        
        if (!File::exists($sqlFile)) {
            $this->command->warn("Fichier SQL non trouvé : {$sqlFile}");
            $this->command->info("Création de quelques enregistrements de test...");
            $this->createTestData();
            return;
        }

        // Vider la table si elle contient déjà des données
        $existingCount = ManifesteSg::count();
        if ($existingCount > 0) {
            $this->command->info("Suppression de {$existingCount} enregistrement(s) existant(s)...");
            ManifesteSg::truncate();
        }

        $this->command->info("Importation des données depuis le fichier SQL avec ULIDs...");
        
        // Lire le fichier SQL
        $content = File::get($sqlFile);
        
        // Extraire les lignes INSERT (gérer les lignes multi-lignes)
        preg_match_all('/INSERT INTO MANIFESTE_SG VALUES \((.*?)\);/s', $content, $matches);
        
        $count = 0;
        $errors = 0;
        
        foreach ($matches[1] as $valuesString) {
            try {
                // Parser les valeurs
                $values = $this->parseValues($valuesString);
                
                // Helper pour tronquer les chaînes selon la longueur max
                $truncate = function($value, $maxLength) {
                    if ($value === null) return null;
                    $value = (string) $value;
                    return mb_substr($value, 0, $maxLength);
                };
                
                // Fonction helper pour parser les dates
                $parseDate = function($value) {
                    if (!$value || $value === 'NULL' || strlen($value) < 10) {
                        return null;
                    }
                    try {
                        return \Carbon\Carbon::parse($value);
                    } catch (\Exception $e) {
                        return null;
                    }
                };
                
                // Créer l'enregistrement avec Eloquent pour générer automatiquement l'ULID
                ManifesteSg::create([
                    'instance_id' => $this->parseValue($values[0] ?? null),
                    'code_bureau' => $truncate($values[1] ?? null, 20),
                    'libelle_bureau' => $truncate($values[2] ?? null, 140),
                    'num_voyage' => $truncate($values[3] ?? null, 68),
                    'date_voyage' => $parseDate($values[4] ?? null),
                    'nbre_total_bl' => $this->parseInteger($values[5] ?? null),
                    'nbre_total_colis' => $this->parseDecimal($values[6] ?? null),
                    'nbre_total_conteneur' => $this->parseInteger($values[7] ?? null),
                    'total_poids_brut' => $this->parseDecimal($values[8] ?? null),
                    'date_arrivee_navire' => $parseDate($values[9] ?? null),
                    'annee_manifeste' => $this->parseInteger($values[10] ?? null),
                    'num_man_sydam' => $this->parseInteger($values[11] ?? null),
                    'num_manifeste' => $truncate($values[12] ?? null, 128),
                    'date_manifeste' => $parseDate($values[13] ?? null),
                    'code_port_charg' => $truncate($values[14] ?? null, 20),
                    'nom_port_charg' => $truncate($values[15] ?? null, 140),
                    'code_port_decharg' => $truncate($values[16] ?? null, 20),
                    'nom_port_decharg' => $truncate($values[17] ?? null, 140),
                    'code_consignataire' => $truncate($values[18] ?? null, 20),
                    'nom_consignataire' => $truncate($values[19] ?? null, 600),
                    'adresse_consignataire' => $truncate($values[20] ?? null, 600),
                    'nom_moyen_transport' => $truncate($values[21] ?? null, 200),
                    'code_transport' => $truncate($values[22] ?? null, 20),
                    'nom_transport' => $truncate($values[23] ?? null, 140),
                    'code_nationalite_navire' => $truncate($values[24] ?? null, 20),
                    'nom_nationalite_navire' => $truncate($values[25] ?? null, 140),
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
        
        $this->command->info("✅ {$count} enregistrement(s) Manifeste SG importé(s) avec ULIDs avec succès" . ($errors > 0 ? " ({$errors} erreur(s))" : ""));
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
        ManifesteSg::create([
            'instance_id' => 460947,
            'code_bureau' => 'CIAB1',
            'libelle_bureau' => 'ABIDJAN-PORT',
            'num_voyage' => 'DST2-0WW3UE1MA',
            'date_voyage' => '2019-07-27 00:00:00',
            'nbre_total_bl' => 1,
            'nbre_total_colis' => 207,
            'nbre_total_conteneur' => 2,
            'total_poids_brut' => 29706,
            'date_arrivee_navire' => '2019-07-30 00:00:00',
            'annee_manifeste' => 2020,
            'num_man_sydam' => 2659,
            'num_manifeste' => 'CIAB1 2020 2659',
            'date_manifeste' => '2020-06-23 00:00:00',
            'code_port_charg' => 'BJCOO',
            'nom_port_charg' => 'COTONOU',
            'code_port_decharg' => 'CIABJ',
            'nom_port_decharg' => 'ABIDJAN',
            'code_consignataire' => '50005Q',
            'nom_consignataire' => 'CMA CGM CI',
            'adresse_consignataire' => '01 BP 3749 ABIDJAN 01 RCI',
            'nom_moyen_transport' => 'SEASPAN CHIWAN',
            'code_transport' => '1',
            'nom_transport' => 'Transport maritime',
            'code_nationalite_navire' => 'HK',
            'nom_nationalite_navire' => 'Hong-Kong',
        ]);
        
        $this->command->info('1 enregistrement Manifeste SG de test créé');
    }
}
