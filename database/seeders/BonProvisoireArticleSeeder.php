<?php

namespace Database\Seeders;

use App\Models\BonProvisoireArticle;
use App\Models\BonProvisoireSg;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;
use Carbon\Carbon;

class BonProvisoireArticleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $sqlFile = base_path('../s360_ analyse/bon_provisoire_article_inserts.sql');
        
        if (!File::exists($sqlFile)) {
            $this->command->warn("Fichier SQL non trouvé : {$sqlFile}");
            $this->command->info("Création d'articles de test pour le bon provisoire spécifique...");
            $this->createTestDataForSpecificBon();
            return;
        }

        // Vérifier si des données existent déjà
        $existingCount = BonProvisoireArticle::count();
        if ($existingCount > 0) {
            $this->command->info("Suppression de {$existingCount} enregistrement(s) existant(s)...");
            BonProvisoireArticle::truncate();
        }

        $this->command->info("Importation des articles depuis le fichier SQL...");
        
        // Lire le fichier SQL
        $content = File::get($sqlFile);
        
        // Extraire les lignes INSERT
        preg_match_all('/INSERT INTO BON_PROVISOIRE_ARTICLE VALUES \((.*?)\);/s', $content, $matches);
        
        $count = 0;
        $errors = 0;
        
        foreach ($matches[1] as $valuesString) {
            try {
                // Parser les valeurs
                $values = $this->parseValues($valuesString);
                
                // Vérifier que le bon provisoire existe
                $instanceId = $values[0] ?? null;
                if (!$instanceId) {
                    continue;
                }
                
                $bon = BonProvisoireSg::where('instance_id', $instanceId)->first();
                if (!$bon) {
                    $errors++;
                    if ($errors <= 10) {
                        $this->command->warn("Bon provisoire avec instance_id {$instanceId} non trouvé, article ignoré");
                    }
                    continue;
                }
                
                // Helper pour parser les dates
                $parseDate = function($value) {
                    if (!$value || $value === 'NULL' || strlen($value) < 10) {
                        return null;
                    }
                    if (preg_match('/^\d{4}-\d{2}-\d{2}/', $value)) {
                        try {
                            return Carbon::parse($value);
                        } catch (\Exception $e) {
                            return null;
                        }
                    }
                    return null;
                };
                
                // Helper pour tronquer les chaînes
                $truncate = function($value, $maxLength) {
                    if ($value === null) return null;
                    $value = (string) $value;
                    return mb_substr($value, 0, $maxLength);
                };
                
                // Créer l'article
                BonProvisoireArticle::create([
                    'instance_id' => $instanceId,
                    'annee' => is_numeric($values[1] ?? null) ? (int) $values[1] : null,
                    'bureau' => $truncate($values[2] ?? null, 20),
                    'serie_bp' => $truncate($values[3] ?? null, 4),
                    'num_serie_bp' => $truncate($values[4] ?? null, 40),
                    'numero_bon_provisoire' => $truncate($values[5] ?? null, 224),
                    'date_bp' => $parseDate($values[6] ?? null),
                    'date_expiration' => $parseDate($values[7] ?? null),
                    'delai_jours' => is_numeric($values[8] ?? null) ? (int) $values[8] : null,
                    'num_vol_lta' => $truncate($values[9] ?? null, 40),
                    'num_lta' => $truncate($values[10] ?? null, 60),
                    'date_lta' => $parseDate($values[11] ?? null),
                    'ncc' => $truncate($values[12] ?? null, 32),
                    'nom_importateur' => $truncate($values[13] ?? null, 400),
                    'nom_fournisseur' => $truncate($values[14] ?? null, 140),
                    'pays_origine' => $truncate($values[15] ?? null, 140),
                    'code_declarant' => $truncate($values[16] ?? null, 68),
                    'nom_declarant' => $truncate($values[17] ?? null, 140),
                    'type_bon_provisoire' => $truncate($values[18] ?? null, 12),
                    'postar' => $truncate($values[19] ?? null, 48),
                    'libelle_marchandise' => $values[20] ?? null, // TEXT field
                    'poids_net_kgs' => is_numeric($values[21] ?? null) ? (float) $values[21] : null,
                    'code_devise' => $truncate($values[22] ?? null, 12),
                    'montant_devise' => is_numeric($values[23] ?? null) ? (float) $values[23] : null,
                    'valeur_fob_article' => is_numeric($values[24] ?? null) ? (float) $values[24] : null,
                    'valeur_fob_cfa' => is_numeric($values[25] ?? null) ? (float) $values[25] : null,
                    'valeur_fret_article' => is_numeric($values[26] ?? null) ? (float) $values[26] : null,
                    'valeur_fret_article_cfa' => is_numeric($values[27] ?? null) ? (float) $values[27] : null,
                    'num_declaration' => $truncate($values[28] ?? null, 384),
                    'datenr' => $parseDate($values[29] ?? null),
                    'nbre_colis' => is_numeric($values[30] ?? null) ? (float) $values[30] : null,
                ]);
                
                $count++;
                
                if ($count % 100 === 0) {
                    $this->command->info("Importé {$count} articles...");
                }
            } catch (\Exception $e) {
                $errors++;
                if ($errors <= 10) {
                    $this->command->warn("Erreur ligne " . ($count + $errors) . ": " . $e->getMessage());
                }
            }
        }
        
        $this->command->info("{$count} article(s) importé(s) avec succès" . ($errors > 0 ? " ({$errors} erreur(s))" : ""));
    }
    
    private function createTestDataForSpecificBon(): void
    {
        // Récupérer le bon provisoire spécifique mentionné par l'utilisateur
        $bon = BonProvisoireSg::where('ulid', '01KAZYFV92HT0833XZMMGMD25Q')->first();
        
        if (!$bon) {
            $this->command->warn("Bon provisoire avec ULID 01KAZYFV92HT0833XZMMGMD25Q non trouvé. Création d'articles pour le premier bon provisoire...");
            $bon = BonProvisoireSg::first();
        }
        
        if (!$bon) {
            $this->command->error("Aucun bon provisoire trouvé dans la base de données.");
            return;
        }
        
        $this->command->info("Création d'articles pour le bon provisoire: {$bon->numero_bon_provisoire} (Instance ID: {$bon->instance_id})");
        
        // Créer plusieurs articles de test basés sur les données réelles de s360_analyse
        // Format: instance_id, annee, bureau, serie_bp, num_serie_bp, numero_bon_provisoire, date_bp, date_expiration, delai_jours, num_vol_lta, num_lta, date_lta, ncc, nom_importateur, nom_fournisseur, pays_origine, code_declarant, nom_declarant, type_bon_provisoire, postar, libelle_marchandise, poids_net_kgs, code_devise, montant_devise, valeur_fob_article, valeur_fob_cfa, valeur_fret_article, valeur_fret_article_cfa, num_declaration, datenr, nbre_colis
        $articles = [
            [
                'instance_id' => $bon->instance_id,
                'annee' => $bon->annee,
                'bureau' => $bon->bureau,
                'serie_bp' => $bon->serie_bp,
                'num_serie_bp' => $bon->num_serie_bp,
                'numero_bon_provisoire' => $bon->numero_bon_provisoire,
                'date_bp' => $bon->date_bp,
                'date_expiration' => $bon->date_expiration,
                'delai_jours' => $bon->delai_jours,
                'num_vol_lta' => $bon->num_vol_lta,
                'num_lta' => $bon->num_lta,
                'date_lta' => $bon->date_lta,
                'ncc' => $bon->ncc,
                'nom_importateur' => $bon->nom_importateur,
                'nom_fournisseur' => $bon->nom_fournisseur,
                'pays_origine' => $bon->pays_origine,
                'code_declarant' => $bon->code_declarant,
                'nom_declarant' => $bon->nom_declarant,
                'type_bon_provisoire' => $bon->type_bon_provisoire,
                'postar' => '3004900000',
                'libelle_marchandise' => 'Médicaments et produits pharmaceutiques',
                'poids_net_kgs' => 45.5000,
                'code_devise' => 'EUR',
                'montant_devise' => 12500.0000,
                'valeur_fob_article' => 12500.0000,
                'valeur_fob_cfa' => 8199462.5000,
                'valeur_fret_article' => 850.0000,
                'valeur_fret_article_cfa' => 557563.4500,
                'num_declaration' => null,
                'nbre_colis' => 8.0000,
            ],
            [
                'instance_id' => $bon->instance_id,
                'annee' => $bon->annee,
                'bureau' => $bon->bureau,
                'serie_bp' => $bon->serie_bp,
                'num_serie_bp' => $bon->num_serie_bp,
                'numero_bon_provisoire' => $bon->numero_bon_provisoire,
                'date_bp' => $bon->date_bp,
                'date_expiration' => $bon->date_expiration,
                'delai_jours' => $bon->delai_jours,
                'num_vol_lta' => $bon->num_vol_lta,
                'num_lta' => $bon->num_lta,
                'date_lta' => $bon->date_lta,
                'ncc' => $bon->ncc,
                'nom_importateur' => $bon->nom_importateur,
                'nom_fournisseur' => $bon->nom_fournisseur,
                'pays_origine' => $bon->pays_origine,
                'code_declarant' => $bon->code_declarant,
                'nom_declarant' => $bon->nom_declarant,
                'type_bon_provisoire' => $bon->type_bon_provisoire,
                'postar' => '3004900000',
                'libelle_marchandise' => 'Produits pharmaceutiques et médicaments',
                'poids_net_kgs' => 32.2500,
                'code_devise' => 'EUR',
                'montant_devise' => 8500.0000,
                'valeur_fob_article' => 8500.0000,
                'valeur_fob_cfa' => 5575634.5000,
                'valeur_fret_article' => 650.0000,
                'valeur_fret_article_cfa' => 426371.0500,
                'num_declaration' => null,
                'nbre_colis' => 12.0000,
            ],
            [
                'instance_id' => $bon->instance_id,
                'annee' => $bon->annee,
                'bureau' => $bon->bureau,
                'serie_bp' => $bon->serie_bp,
                'num_serie_bp' => $bon->num_serie_bp,
                'numero_bon_provisoire' => $bon->numero_bon_provisoire,
                'date_bp' => $bon->date_bp,
                'date_expiration' => $bon->date_expiration,
                'delai_jours' => $bon->delai_jours,
                'num_vol_lta' => $bon->num_vol_lta,
                'num_lta' => $bon->num_lta,
                'date_lta' => $bon->date_lta,
                'ncc' => $bon->ncc,
                'nom_importateur' => $bon->nom_importateur,
                'nom_fournisseur' => $bon->nom_fournisseur,
                'pays_origine' => $bon->pays_origine,
                'code_declarant' => $bon->code_declarant,
                'nom_declarant' => $bon->nom_declarant,
                'type_bon_provisoire' => $bon->type_bon_provisoire,
                'postar' => '9018900000',
                'libelle_marchandise' => 'Instruments et appareils médicaux',
                'poids_net_kgs' => 18.7500,
                'code_devise' => 'EUR',
                'montant_devise' => 6200.0000,
                'valeur_fob_article' => 6200.0000,
                'valeur_fob_cfa' => 4066933.4000,
                'valeur_fret_article' => 420.0000,
                'valeur_fret_article_cfa' => 275501.9400,
                'num_declaration' => null,
                'nbre_colis' => 5.0000,
            ],
        ];
        
        $count = 0;
        foreach ($articles as $articleData) {
            try {
                BonProvisoireArticle::create($articleData);
                $count++;
            } catch (\Exception $e) {
                $this->command->warn("Erreur lors de la création d'un article : " . $e->getMessage());
            }
        }
        
        $this->command->info("{$count} article(s) créé(s) avec succès pour le bon provisoire {$bon->numero_bon_provisoire}");
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
}







