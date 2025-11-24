<?php

namespace Database\Seeders;

use App\Models\BanqueTvf;
use App\Models\BanqueTvfComp1;
use App\Models\BanqueTvfComp2;
use App\Models\FdiSg;
use Illuminate\Database\Seeder;

class LinkTvfFdiSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $tvf = BanqueTvf::where('ulid', '01KATSK353JWBSHM25BTR3Q9TY')->first();
        
        if (!$tvf) {
            $this->command->error('TVF avec ULID 01KATSK353JWBSHM25BTR3Q9TY introuvable');
            return;
        }

        // 1. Trouver ou créer une FDI
        $fdi = FdiSg::orderBy('id')->first();
        
        if (!$fdi) {
            // Créer une FDI de test
            $fdi = FdiSg::create([
                'serie_fdi' => 'A',
                'bureau' => 'CIAB1',
                'annee' => 2024,
                'numero_serie' => '240001',
                'numero_fdi' => '99999',
                'date_fdi' => now(),
                'derniere_operation' => 'Direct_Validate',
                'date_derniere_operation' => now(),
                'reglement' => 'Paiement sur compte bancaire',
                'banque' => 'ORABANK',
                'ref_domiciliation' => 'TEST/2024',
                'date_domiciliation' => now(),
                'montant_domicilie_cfa' => 10000000,
                'cc' => '0504375P',
                'importateur' => 'TEST IMPORTATEUR',
                'adresse_importateur' => 'ABIDJAN',
                'telephone_importateur' => '01234567',
                'fournisseur' => 'TEST FOURNISSEUR',
                'adresse_fournisseur' => 'EUROPE',
                'pays_fournisseur' => 'France',
                'incoterm' => 'CFR',
                'libelle_incoterm' => 'Cout et FRET',
                'ref_facture' => 'FACT-001',
                'date_facture' => now(),
                'valeur_facture_cfa' => 10000000,
                'valeur_fob_cfa' => 10000000,
                'nom_devise' => 'Euro',
                'devise' => '655.957',
                'declarant' => 'TEST DECLARANT',
            ]);
            $this->command->info("FDI créée avec numero_fdi: {$fdi->numero_fdi}");
        }

        // 2. Mettre à jour la TVF avec le num_fdi
        if (!$tvf->old_id) {
            $tvf->old_id = (string) $tvf->id;
        }
        
        $tvf->num_fdi = $fdi->numero_fdi;
        $tvf->annee_fdi = $fdi->annee ?? date('Y');
        $tvf->date_fdi = $fdi->date_fdi ?? now();
        $tvf->status_fdi = 'APPROUVE';
        $tvf->statut_ac = $tvf->statut_ac ?? 'APPROUVE';
        $tvf->pays_exp = $tvf->pays_exp ?? $fdi->pays_fournisseur ?? 'France';
        $tvf->save();
        
        $this->command->info("TVF mise à jour avec num_fdi: {$tvf->num_fdi}");

        // 3. Créer la première comparaison
        $comp1 = BanqueTvfComp1::where('old_id', $tvf->old_id)->first();
        if (!$comp1) {
            $comp1 = BanqueTvfComp1::create([
                'old_id' => $tvf->old_id,
                'annee_fdi' => $tvf->annee_fdi,
                'num_fdi' => $tvf->num_fdi,
                'date_fdi' => $tvf->date_fdi,
                'status_fdi' => $tvf->status_fdi,
                'statut_ac' => $tvf->statut_ac,
                'ref_ddu' => $tvf->ref_ddu ?? 'DDU-TEST-001',
                'bank_dom' => $tvf->bank_dom ?? 'ORABANK',
                'date_dom' => $tvf->date_dom ?? now(),
                'num_dom' => $tvf->num_dom ?? 'DOM-TEST-001',
                'pays_exp' => $tvf->pays_exp,
                'type_op' => $tvf->type_op ?? 'IMPORTATION',
                'dev_trans' => $tvf->dev_trans ?? 'Euro',
                'dev_paiem' => $tvf->dev_paiem ?? 'Euro',
                'mont_fact_dev' => $tvf->mont_fact_dev ?? 10000,
                'mont_fact_xof' => $tvf->mont_fact_xof ?? 6559570,
                'mont_ac_dev' => $tvf->mont_ac_dev ?? 10000,
                'mont_ac_xof' => $tvf->mont_ac_xof ?? 6559570,
            ]);
            $this->command->info("Comparaison 1 créée avec old_id: {$comp1->old_id}");
        }

        // 4. Créer la deuxième comparaison
        $comp2 = BanqueTvfComp2::where('old_id', $tvf->old_id)->first();
        if (!$comp2) {
            $comp2 = BanqueTvfComp2::create([
                'old_id' => $tvf->old_id,
                'annee_fdi' => $tvf->annee_fdi,
                'num_fdi' => $tvf->num_fdi,
                'date_fdi' => $tvf->date_fdi,
                'status_fdi' => $tvf->status_fdi,
                'statut_ac' => $tvf->statut_ac,
                'ref_ddu' => $tvf->ref_ddu ?? 'DDU-TEST-002',
                'bank_dom' => $tvf->bank_dom ?? 'S.I.B',
                'date_dom' => $tvf->date_dom ?? now()->addDays(1),
                'num_dom' => $tvf->num_dom ?? 'DOM-TEST-002',
                'pays_exp' => $tvf->pays_exp,
                'type_op' => $tvf->type_op ?? 'IMPORTATION',
                'dev_trans' => $tvf->dev_trans ?? 'Euro',
                'dev_paiem' => $tvf->dev_paiem ?? 'Euro',
                'mont_fact_dev' => $tvf->mont_fact_dev ?? 10000,
                'mont_fact_xof' => $tvf->mont_fact_xof ?? 6559570,
                'mont_ac_dev' => $tvf->mont_ac_dev ?? 10000,
                'mont_ac_xof' => $tvf->mont_ac_xof ?? 6559570,
            ]);
            $this->command->info("Comparaison 2 créée avec old_id: {$comp2->old_id}");
        }

        $this->command->info("✅ TVF {$tvf->ulid} liée à FDI {$fdi->numero_fdi} avec comparaisons créées");
    }
}

