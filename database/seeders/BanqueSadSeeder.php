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

        foreach ($matches[1] as $valuesString) {
            try {
                $values = $this->parseValues($valuesString);

                BanqueSad::create([
                    'id_sad' => $this->parseValue($values[0] ?? null),
                    'ref_ddu' => $this->parseValue($values[1] ?? null),
                    'num_man' => $this->parseValue($values[2] ?? null),
                    'bl_ddu' => $this->parseValue($values[3] ?? null),
                    'num_ddu' => $this->parseValue($values[4] ?? null),
                    'date_ddu' => $this->parseDate($values[5] ?? null),
                    'type_dec' => $this->parseValue($values[6] ?? null),
                    'sous_regime' => $this->parseValue($values[7] ?? null),
                    'code_add' => $this->parseValue($values[8] ?? null),
                    'num_cc' => $this->parseValue($values[9] ?? null),
                    'dt_ddu' => $this->parseDecimal($values[10] ?? null),
                    'code_declarant' => $this->parseValue($values[11] ?? null),
                    'valeur_fob_ddu' => $this->parseDecimal($values[12] ?? null),
                    'valeur_caf_ddu' => $this->parseDecimal($values[13] ?? null),
                    'valeur_fret_ddu' => $this->parseDecimal($values[14] ?? null),
                    'val_ass_ddu' => $this->parseDecimal($values[15] ?? null),
                    'num_demande_ac' => $this->parseValue($values[16] ?? null),
                    'date_dem_ac' => $this->parseDate($values[17] ?? null),
                    'statut_ac' => $this->parseValue($values[18] ?? null),
                    'base_sur' => $this->parseValue($values[19] ?? null),
                    'num_dom' => $this->parseValue($values[20] ?? null),
                    'date_dom' => $this->parseDate($values[21] ?? null),
                    'bank_dom' => $this->parseValue($values[22] ?? null),
                    'banq_enreg' => $this->parseValue($values[23] ?? null),
                    'num_enreg_banq' => $this->parseValue($values[24] ?? null),
                    'date_aprob_bank' => $this->parseDate($values[25] ?? null),
                    'date_aprob_bank2' => $this->parseDate($values[26] ?? null),
                    'pays_exp' => $this->parseValue($values[27] ?? null),
                    'adr_exp' => $this->parseValue($values[28] ?? null),
                    'autorise_par' => $this->parseValue($values[29] ?? null),
                    'cda' => $this->parseValue($values[30] ?? null),
                    'code_cda' => $this->parseValue($values[31] ?? null),
                    'benef_fonds' => $this->parseValue($values[32] ?? null),
                    'type_op' => $this->parseValue($values[33] ?? null),
                    'dev_trans' => $this->parseValue($values[34] ?? null),
                    'dev_paiem' => $this->parseValue($values[35] ?? null),
                    'mont_ac_dev' => $this->parseDecimal($values[36] ?? null),
                    'mont_ac_xof' => $this->parseDecimal($values[37] ?? null),
                    'mont_fact_xof' => $this->parseValue($values[38] ?? null),
                    'solde_dev' => $this->parseDecimal($values[39] ?? null),
                    'mont_fact_dev' => $this->parseValue($values[40] ?? null),
                    'mont_tot_march_xof' => $this->parseValue($values[41] ?? null),
                ]);

                $count++;
                if ($count % 100 === 0) {
                    $this->command?->info("{$count} enregistrements SAD créés...");
                }
            } catch (\Exception $e) {
                $errors++;
                if ($errors <= 10) {
                    $this->command?->warn("Erreur lors de la création d'un enregistrement SAD : " . $e->getMessage());
                }
            }
        }

        $this->command?->info("✅ {$count} enregistrement(s) SAD créé(s)" . ($errors > 0 ? " ({$errors} erreur(s))" : ""));
    }

    private function parseValues(string $valuesString): array
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

        if (trim($current) !== '') {
            $values[] = trim($current);
        }

        return $values;
    }

    private function parseValue(?string $value): ?string
    {
        if ($value === null || $value === 'NULL' || $value === '') {
            return null;
        }

        $value = trim($value, " '\"");
        return $value === '' ? null : $value;
    }

    private function parseDate(?string $value): ?\DateTime
    {
        if ($value === null || $value === 'NULL' || $value === '') {
            return null;
        }

        $value = trim($value, " '\"");
        if ($value === '') {
            return null;
        }

        try {
            return new \DateTime($value);
        } catch (\Exception $e) {
            return null;
        }
    }

    private function parseDecimal(?string $value): ?float
    {
        if ($value === null || $value === 'NULL' || $value === '') {
            return null;
        }

        $value = trim($value, " '\"");
        if ($value === '') {
            return null;
        }

        return (float) $value;
    }

    private function createTestData(): void
    {
        BanqueSad::create([
            'num_ddu' => 'DDU-2024-001',
            'ref_ddu' => 'REF-DDU-001',
            'num_man' => 'MAN-2024-001',
            'date_ddu' => now(),
            'statut_ac' => 'APPROUVE',
            'pays_exp' => 'France',
            'bank_dom' => 'ORABANK',
            'valeur_caf_ddu' => 10000000,
            'valeur_fob_ddu' => 9500000,
        ]);

        $this->command?->info('3 enregistrements SAD de test créés');
    }
}

