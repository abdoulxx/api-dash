<?php

namespace Database\Seeders;

use App\Models\FcvrSg;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;

class FcvrSgSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $sqlFile = base_path('../s360_ analyse/fcvr_sg_inserts.sql');
        
        if (!File::exists($sqlFile)) {
            $this->command->warn("Fichier SQL non trouvé : {$sqlFile}");
            $this->command->info("Création de quelques enregistrements de test...");
            $this->createTestData();
            return;
        }

        // Vider la table si elle contient déjà des données
        $existingCount = FcvrSg::count();
        if ($existingCount > 0) {
            $this->command->info("Suppression de {$existingCount} enregistrement(s) existant(s)...");
            FcvrSg::truncate();
        }

        $this->command->info("Importation des données depuis le fichier SQL avec ULIDs...");
        
        // Lire le fichier SQL
        $content = File::get($sqlFile);
        
        // Extraire les lignes INSERT (gérer les lignes multi-lignes)
        preg_match_all('/INSERT INTO FCVR_SG VALUES \((.*?)\);/s', $content, $matches);
        
        $count = 0;
        $errors = 0;
        
        foreach ($matches[1] as $valuesString) {
            try {
                // Parser les valeurs
                $values = $this->parseValues($valuesString);
                
                // Créer l'enregistrement avec Eloquent pour générer automatiquement l'ULID
                FcvrSg::create([
                    'instanceid' => $this->parseValue($values[0] ?? null),
                    'annee' => $this->parseValue($values[1] ?? null),
                    'num_tt' => $this->parseValue($values[2] ?? null),
                    'bureau' => $this->parseValue($values[3] ?? null),
                    'num_fdi' => $this->parseValue($values[4] ?? null),
                    'date_fdi' => $this->parseDate($values[5] ?? null),
                    'num_voyage' => $this->parseValue($values[6] ?? null),
                    'nom_navire' => $this->parseValue($values[7] ?? null),
                    'num_bl' => $this->parseValue($values[8] ?? null),
                    'date_arrivee' => $this->parseDate($values[9] ?? null),
                    'nombre_conteneur' => $this->parseInteger($values[10] ?? null),
                    'poids_net_total' => $this->parseDecimal($values[11] ?? null),
                    'poids_brut_total' => $this->parseDecimal($values[12] ?? null),
                    'nombre_total_colis' => $this->parseValue($values[13] ?? null),
                    'nbre_total_colis' => $this->parseValue($values[14] ?? null),
                    'lieu_chrg' => $this->parseValue($values[15] ?? null),
                    'lieu_dechrg' => $this->parseValue($values[16] ?? null),
                    'num_rfcv' => $this->parseValue($values[17] ?? null),
                    'date_rfcv' => $this->parseDate($values[18] ?? null),
                    'derniere_operation' => $this->parseValue($values[19] ?? null),
                    'date_derniere_operation' => $this->parseDate($values[20] ?? null),
                    'cc' => $this->parseValue($values[21] ?? null),
                    'nom_importateur' => $this->parseValue($values[22] ?? null),
                    'pays_importateur' => $this->parseValue($values[23] ?? null),
                    'code_pays' => $this->parseValue($values[24] ?? null),
                    'nom_pays_importateur' => $this->parseValue($values[25] ?? null),
                    'nom_fournisseur' => $this->parseValue($values[26] ?? null),
                    'pays_fournisseur' => $this->parseValue($values[27] ?? null),
                    'code_declarant' => $this->parseValue($values[28] ?? null),
                    'nom_declarant' => $this->parseValue($values[29] ?? null),
                    'code_pays_origine' => $this->parseValue($values[30] ?? null),
                    'nom_pays_origine' => $this->parseValue($values[31] ?? null),
                    'nombre_total_article' => $this->parseInteger($values[32] ?? null),
                    'numero_facture' => $this->parseValue($values[33] ?? null),
                    'date_facture' => $this->parseDate($values[34] ?? null),
                    'val_fact_rfcv_devise' => $this->parseDecimal($values[35] ?? null),
                    'val_fact_rfcv_cfa' => $this->parseDecimal($values[36] ?? null),
                    'observation' => $this->parseValue($values[37] ?? null),
                    'incoterm' => $this->parseValue($values[38] ?? null),
                    'devise' => $this->parseValue($values[39] ?? null),
                    'taux_devise' => $this->parseDecimal($values[40] ?? null),
                    'fob_rfcv' => $this->parseDecimal($values[41] ?? null),
                    'fob_rfcv_cfa' => $this->parseDecimal($values[42] ?? null),
                    'fret_rfcv' => $this->parseDecimal($values[43] ?? null),
                    'fret_rfcv_cfa' => $this->parseDecimal($values[44] ?? null),
                    'assurance_rfcv' => $this->parseDecimal($values[45] ?? null),
                    'assurance_rfcv_cfa' => $this->parseDecimal($values[46] ?? null),
                    'autres_couts_rfcv' => $this->parseDecimal($values[47] ?? null),
                    'autres_rfcv_cfa' => $this->parseDecimal($values[48] ?? null),
                    'caf_rfcv' => $this->parseDecimal($values[49] ?? null),
                    'caf_rfcv_cfa' => $this->parseDecimal($values[50] ?? null),
                    'num_declaration' => $this->parseValue($values[51] ?? null),
                    'date_declaration' => $this->parseDate($values[52] ?? null),
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
        
        $this->command->info("✅ {$count} enregistrement(s) FCVR SG importé(s) avec ULIDs avec succès" . ($errors > 0 ? " ({$errors} erreur(s))" : ""));
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
        FcvrSg::create([
            'instanceid' => 570980,
            'annee' => '2020',
            'num_tt' => 590497,
            'bureau' => null,
            'num_fdi' => '113527',
            'date_fdi' => '2020-09-23 00:00:00',
            'num_voyage' => 'DST1-140',
            'nom_navire' => 'HOEGH TROVE',
            'num_bl' => 'HOEGHC48MUAB0002',
            'date_arrivee' => '2019-09-15 00:00:00',
            'nombre_conteneur' => 0,
            'poids_net_total' => 5400,
            'poids_brut_total' => 5400,
            'nombre_total_colis' => '1 UNITS',
            'nbre_total_colis' => '1',
            'lieu_chrg' => 'INMUM',
            'lieu_dechrg' => 'CIABJ',
            'num_rfcv' => 'RCS19081792P2',
            'date_rfcv' => '2020-10-20 00:00:00',
            'derniere_operation' => 'Amend',
            'date_derniere_operation' => '2020-10-20 17:04:41',
            'cc' => '1840598H',
            'nom_importateur' => 'TRANSPORT STE AGATHE',
            'pays_importateur' => null,
            'code_pays' => 'CI',
            'nom_pays_importateur' => 'Cote d\'Ivoire',
            'nom_fournisseur' => 'ASHOK LEYLAND LIMITED',
            'pays_fournisseur' => 'CHENNAI 600 032',
            'code_declarant' => '00402Y',
            'nom_declarant' => 'TTI - TROPICALE TRANSIT INTER -',
            'code_pays_origine' => 'IN',
            'nom_pays_origine' => 'Inde',
            'nombre_total_article' => 1,
            'numero_facture' => '019/1',
            'date_facture' => '2020-09-16 00:00:00',
            'val_fact_rfcv_devise' => 50835,
            'val_fact_rfcv_cfa' => 28775660.1,
            'observation' => null,
            'incoterm' => 'CIF',
            'devise' => 'USD',
            'taux_devise' => 566.06,
            'fob_rfcv' => 45535,
            'fob_rfcv_cfa' => 25775542.1,
            'fret_rfcv' => 5200,
            'fret_rfcv_cfa' => 2943512,
            'assurance_rfcv' => 100,
            'assurance_rfcv_cfa' => 56606,
            'autres_couts_rfcv' => null,
            'autres_rfcv_cfa' => null,
            'caf_rfcv' => 50835,
            'caf_rfcv_cfa' => 28775660.1,
            'num_declaration' => null,
            'date_declaration' => null,
        ]);
        
        $this->command->info('1 enregistrement FCVR SG de test créé');
    }
}
