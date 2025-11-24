<?php

namespace Database\Seeders;

use App\Models\BanqueSad;
use App\Models\DeclarationSg;
use App\Models\ManifesteSg;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class BanqueSadSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Récupérer des déclarations et manifestes existants pour créer des SAD liées
        $declarations = DeclarationSg::orderBy('instanceid')->limit(10)->get();
        $manifestes = ManifesteSg::orderBy('instance_id')->limit(10)->get();

        if ($declarations->isEmpty() && $manifestes->isEmpty()) {
            $this->command->warn('Aucune déclaration ou manifeste trouvé. Création de SAD de test...');
            $this->createTestSads();
            return;
        }

        $sadsCreated = 0;

        // Créer des SAD basées sur les déclarations
        foreach ($declarations as $index => $declaration) {
            $manifeste = $manifestes->get($index % $manifestes->count());
            
            BanqueSad::create([
                'id_sad' => 'SAD-' . (1000 + $index),
                'ref_ddu' => $declaration->declaration,
                'num_man' => $manifeste->num_manifeste ?? null,
                'bl_ddu' => 'BL-' . (1000 + $index),
                'num_ddu' => 'DDU-' . (2024 + $index) . '-' . str_pad($index + 1, 4, '0', STR_PAD_LEFT),
                'date_ddu' => $declaration->date_declaration ?? now()->subDays(rand(1, 30)),
                'type_dec' => $declaration->typdec ?? 'IMPORTATION',
                'sous_regime' => $declaration->sous_regime ?? '40',
                'code_add' => 'ADD-' . $index,
                'num_cc' => 'CC-' . (1000 + $index),
                'dt_ddu' => rand(10000, 100000) / 100,
                'code_declarant' => $declaration->declarant ?? 'DECL-' . $index,
                'valeur_fob_ddu' => $declaration->valeur_fob_declaration ?? rand(1000000, 10000000),
                'valeur_caf_ddu' => $declaration->valeur_caf_declaration ?? rand(1000000, 10000000),
                'valeur_fret_ddu' => rand(100000, 1000000),
                'val_ass_ddu' => rand(50000, 500000),
                'num_demande_ac' => 'AC-' . (2024 + $index) . '-' . str_pad($index + 1, 4, '0', STR_PAD_LEFT),
                'date_dem_ac' => now()->subDays(rand(1, 15)),
                'statut_ac' => collect(['APPROUVE', 'EN_ATTENTE', 'REJETE', 'VALIDATION MANUELLE'])->random(),
                'base_sur' => 'SAD',
                'num_dom' => 'DOM-' . (2024 + $index) . '-' . str_pad($index + 1, 3, '0', STR_PAD_LEFT),
                'date_dom' => now()->subDays(rand(1, 10)),
                'bank_dom' => collect(['ORABANK', 'S.I.B', 'B.I.C.I.C.I.', 'NSIA', 'BRIDGEBANK'])->random(),
                'banq_enreg' => collect(['ORABANK', 'S.I.B', 'B.I.C.I.C.I.'])->random(),
                'num_enreg_banq' => 'REG-' . (2024 + $index) . '-' . str_pad($index + 1, 4, '0', STR_PAD_LEFT),
                'date_aprob_bank' => now()->subDays(rand(1, 5)),
                'date_aprob_bank2' => now()->subDays(rand(0, 3)),
                'pays_exp' => collect(['France', 'Belgique', 'Chine', 'Allemagne', 'Royaume-Uni', 'Espagne'])->random(),
                'adr_exp' => 'Adresse exportateur ' . ($index + 1),
                'autorise_par' => 'Autorité ' . ($index + 1),
                'cda' => 'CDA-' . $index,
                'code_cda' => 'CODE-CDA-' . $index,
                'benef_fonds' => 'Bénéficiaire ' . ($index + 1),
                'type_op' => 'IMPORTATION',
                'dev_trans' => collect(['Euro', 'Dollar Américain', 'Franc CFA'])->random(),
                'dev_paiem' => collect(['Euro', 'Dollar Américain', 'Franc CFA'])->random(),
                'mont_ac_dev' => rand(5000, 50000),
                'mont_ac_xof' => rand(3000000, 30000000),
                'mont_fact_xof' => (string) rand(3000000, 30000000),
                'solde_dev' => rand(0, 10000),
                'mont_fact_dev' => (string) rand(5000, 50000),
                'mont_tot_march_xof' => (string) rand(3000000, 30000000),
            ]);
            
            $sadsCreated++;
        }

        $this->command->info("✅ {$sadsCreated} SAD créée(s) avec succès");
    }

    private function createTestSads(): void
    {
        for ($i = 0; $i < 5; $i++) {
            BanqueSad::create([
                'id_sad' => 'SAD-TEST-' . ($i + 1),
                'ref_ddu' => 'DEC-TEST-' . ($i + 1),
                'num_man' => 'MAN-TEST-' . ($i + 1),
                'bl_ddu' => 'BL-TEST-' . ($i + 1),
                'num_ddu' => 'DDU-2024-' . str_pad($i + 1, 4, '0', STR_PAD_LEFT),
                'date_ddu' => now()->subDays($i * 5),
                'type_dec' => 'IMPORTATION',
                'sous_regime' => '40',
                'statut_ac' => collect(['APPROUVE', 'EN_ATTENTE', 'REJETE'])->random(),
                'num_dom' => 'DOM-2024-' . str_pad($i + 1, 3, '0', STR_PAD_LEFT),
                'date_dom' => now()->subDays($i * 3),
                'bank_dom' => collect(['ORABANK', 'S.I.B', 'B.I.C.I.C.I.'])->random(),
                'pays_exp' => collect(['France', 'Belgique', 'Chine'])->random(),
                'type_op' => 'IMPORTATION',
                'dev_trans' => 'Euro',
                'dev_paiem' => 'Euro',
                'valeur_fob_ddu' => rand(1000000, 10000000),
                'valeur_caf_ddu' => rand(1000000, 10000000),
            ]);
        }
        
        $this->command->info("✅ 5 SAD de test créées");
    }
}
