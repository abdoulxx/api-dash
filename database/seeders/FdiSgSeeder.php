<?php

namespace Database\Seeders;

use App\Models\FdiSg;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;

class FdiSgSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $sqlFile = base_path('../s360_ analyse/fdi_sg_inserts.sql');
        
        if (!File::exists($sqlFile)) {
            $this->command->warn("Fichier SQL non trouvé : {$sqlFile}");
            $this->command->info("Création de quelques enregistrements de test...");
            $this->createTestData();
            return;
        }

        // Vider la table si elle contient déjà des données
        $existingCount = FdiSg::count();
        if ($existingCount > 0) {
            $this->command->info("Suppression de {$existingCount} enregistrement(s) existant(s)...");
            FdiSg::truncate();
        }

        $this->command->info("Importation des données depuis le fichier SQL avec ULIDs...");
        
        // Lire le fichier SQL
        $content = File::get($sqlFile);
        
        // Extraire les lignes INSERT (gérer les lignes multi-lignes)
        preg_match_all('/INSERT INTO FDI_SG VALUES \((.*?)\);/s', $content, $matches);
        
        $count = 0;
        $errors = 0;
        
        foreach ($matches[1] as $valuesString) {
            try {
                // Parser les valeurs
                $values = $this->parseValues($valuesString);
                
                // Créer l'enregistrement avec Eloquent pour générer automatiquement l'ULID
                FdiSg::create([
                    'instance_id' => $this->parseValue($values[0] ?? null),
                    'serie_fdi' => $this->parseValue($values[1] ?? null),
                    'bureau' => $this->parseValue($values[2] ?? null),
                    'annee' => $this->parseValue($values[3] ?? null),
                    'numero_serie' => $this->parseValue($values[4] ?? null),
                    'numero_fdi' => $this->parseValue($values[5] ?? null),
                    'date_fdi' => $this->parseDate($values[6] ?? null),
                    'derniere_operation' => $this->parseValue($values[7] ?? null),
                    'date_derniere_operation' => $this->parseDate($values[8] ?? null),
                    'reglement' => $this->parseValue($values[9] ?? null),
                    'banque' => $this->parseValue($values[10] ?? null),
                    'ref_domiciliation' => $this->parseValue($values[11] ?? null),
                    'date_domiciliation' => $this->parseDate($values[12] ?? null),
                    'montant_domicilie_cfa' => $this->parseDecimal($values[13] ?? null),
                    'cc' => $this->parseValue($values[14] ?? null),
                    'importateur' => $this->parseValue($values[15] ?? null),
                    'adresse_importateur' => $this->parseValue($values[16] ?? null),
                    'telephone_importateur' => $this->parseValue($values[17] ?? null),
                    'fournisseur' => $this->parseValue($values[18] ?? null),
                    'adresse_fournisseur' => $this->parseValue($values[19] ?? null),
                    'pays_fournisseur' => $this->parseValue($values[20] ?? null),
                    'tel_fournisseur' => $this->parseValue($values[21] ?? null),
                    'fax_fournisseur' => $this->parseValue($values[22] ?? null),
                    'incoterm' => $this->parseValue($values[23] ?? null),
                    'libelle_incoterm' => $this->parseValue($values[24] ?? null),
                    'ref_facture' => $this->parseValue($values[25] ?? null),
                    'date_facture' => $this->parseDate($values[26] ?? null),
                    'valeur_facture_cfa' => $this->parseDecimal($values[27] ?? null),
                    'valeur_fob_cfa' => $this->parseDecimal($values[28] ?? null),
                    'valeur_caf' => $this->parseDecimal($values[29] ?? null),
                    'valeur_fret_cfa' => $this->parseDecimal($values[30] ?? null),
                    'valeur_assurance_cfa' => $this->parseDecimal($values[31] ?? null),
                    'nom_devise' => $this->parseValue($values[32] ?? null),
                    'devise' => $this->parseDecimal($values[33] ?? null),
                    'declarant' => $this->parseValue($values[34] ?? null),
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
        
        $this->command->info("✅ {$count} enregistrement(s) FDI SG importé(s) avec ULIDs avec succès" . ($errors > 0 ? " ({$errors} erreur(s))" : ""));
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
    
    private function createTestData()
    {
        FdiSg::create([
            'instance_id' => 800227,
            'serie_fdi' => 'A',
            'bureau' => 'CIAB1',
            'annee' => 2020,
            'numero_serie' => '002128',
            'numero_fdi' => '3857',
            'date_fdi' => '2020-01-13 00:00:00',
            'derniere_operation' => 'Direct_Validate',
            'date_derniere_operation' => '2020-01-13 07:16:29',
            'reglement' => 'Paiement sur compte bancaire',
            'banque' => 'ECOBANK-COTE D\'IVOIRE',
            'ref_domiciliation' => 'DOM IMP 0201/2020',
            'date_domiciliation' => '2020-01-13 00:00:00',
            'montant_domicilie_cfa' => 1142515.8,
            'cc' => '1722037N',
            'importateur' => 'SORAL SERVICES',
            'adresse_importateur' => '01 BP 1945 ABIDJAN (VILLE) 01 TREICHVILLE AV.21 - 17',
            'telephone_importateur' => '06396141',
            'fournisseur' => 'AFRODITTE V/ POUL FEDERSEN',
            'adresse_fournisseur' => 'KRONBORGVEJ 48 5450 OTTERUPNDENMARK',
            'pays_fournisseur' => 'Allemagne',
            'tel_fournisseur' => '+49213588954',
            'fax_fournisseur' => null,
            'incoterm' => 'CFR',
            'libelle_incoterm' => 'Cout et FRET',
            'ref_facture' => 'AFTY1',
            'date_facture' => '2020-01-06 00:00:00',
            'valeur_facture_cfa' => 1142515.8,
            'valeur_fob_cfa' => 1142515.8,
            'valeur_caf' => null,
            'valeur_fret_cfa' => 459950,
            'valeur_assurance_cfa' => null,
            'nom_devise' => 'Dollar Canadien',
            'devise' => 459.95,
            'declarant' => 'SITRACOM',
        ]);
        
        $this->command->info('1 enregistrement FDI SG de test créé');
    }
}
