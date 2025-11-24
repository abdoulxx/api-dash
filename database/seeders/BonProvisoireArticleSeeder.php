<?php

namespace Database\Seeders;

use App\Models\BonProvisoireArticle;
use App\Models\BonProvisoireSg;
use Illuminate\Database\Seeder;

class BonProvisoireArticleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Récupérer le bon provisoire spécifique
        $bon = BonProvisoireSg::where('id', '01kap72fxdcay4gbkp3txhs1n1')->first();
        
        if (!$bon) {
            $this->command->warn("Bon provisoire avec ULID 01kap72fxdcay4gbkp3txhs1n1 non trouvé. Création d'articles pour le premier bon provisoire...");
            $bon = BonProvisoireSg::first();
        }
        
        if (!$bon) {
            $this->command->error("Aucun bon provisoire trouvé dans la base de données.");
            return;
        }
        
        $this->command->info("Création d'articles pour le bon provisoire: {$bon->numero_bon_provisoire} (Instance ID: {$bon->instance_id})");
        
        // Créer plusieurs articles de test
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
                'postar' => '8708.10.00',
                'libelle_marchandise' => 'Équipements de traitement des eaux',
                'poids_net_kgs' => 1250.5000,
                'code_devise' => 'EUR',
                'montant_devise' => 15000.0000,
                'valeur_fob_article' => 15000.0000,
                'valeur_fob_cfa' => 9847500.0000,
                'valeur_fret_article' => 2500.0000,
                'valeur_fret_article_cfa' => 1641250.0000,
                'num_declaration' => 'DEC-2020-001',
                'nbre_colis' => 25.0000,
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
                'postar' => '8414.80.10',
                'libelle_marchandise' => 'Pompes centrifuges pour liquides',
                'poids_net_kgs' => 850.2500,
                'code_devise' => 'EUR',
                'montant_devise' => 8500.0000,
                'valeur_fob_article' => 8500.0000,
                'valeur_fob_cfa' => 5577250.0000,
                'valeur_fret_article' => 1200.0000,
                'valeur_fret_article_cfa' => 787200.0000,
                'num_declaration' => 'DEC-2020-002',
                'nbre_colis' => 15.0000,
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
                'postar' => '7308.90.00',
                'libelle_marchandise' => 'Structures et parties de structures en acier',
                'poids_net_kgs' => 3200.7500,
                'code_devise' => 'USD',
                'montant_devise' => 25000.0000,
                'valeur_fob_article' => 25000.0000,
                'valeur_fob_cfa' => 14750000.0000,
                'valeur_fret_article' => 4500.0000,
                'valeur_fret_article_cfa' => 2655000.0000,
                'num_declaration' => 'DEC-2020-003',
                'nbre_colis' => 50.0000,
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
                'postar' => '8471.30.00',
                'libelle_marchandise' => 'Ordinateurs portables et tablettes',
                'poids_net_kgs' => 45.5000,
                'code_devise' => 'EUR',
                'montant_devise' => 12000.0000,
                'valeur_fob_article' => 12000.0000,
                'valeur_fob_cfa' => 7878000.0000,
                'valeur_fret_article' => 800.0000,
                'valeur_fret_article_cfa' => 525200.0000,
                'num_declaration' => 'DEC-2020-004',
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
                'postar' => '8517.12.00',
                'libelle_marchandise' => 'Téléphones portables et smartphones',
                'poids_net_kgs' => 12.2500,
                'code_devise' => 'USD',
                'montant_devise' => 8500.0000,
                'valeur_fob_article' => 8500.0000,
                'valeur_fob_cfa' => 5015000.0000,
                'valeur_fret_article' => 500.0000,
                'valeur_fret_article_cfa' => 295000.0000,
                'num_declaration' => 'DEC-2020-005',
                'nbre_colis' => 3.0000,
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
}

