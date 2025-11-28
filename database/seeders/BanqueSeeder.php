<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class BanqueSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Vider la table si elle contient déjà des données
        $existingCount = DB::table('BANQUE')->count();
        if ($existingCount > 0) {
            $this->command->info("Suppression de {$existingCount} enregistrement(s) existant(s)...");
            DB::table('BANQUE')->truncate();
        }

        $this->command->info("Création de données de test pour la table BANQUE avec ULIDs...");

        $banques = [
            [
                'ANNEE_DVT' => 2024,
                'NUM_DVT' => 'DVT-2024-001',
                'DATE_DVT' => Carbon::parse('2024-01-15 10:30:00'),
                'MONT_AC_XOF' => 15000000.00,
                'MONT_FACT_XOF' => 15000000.00,
                'REF_DDU' => 'DDU-2024-001',
                'CDA_AC' => 'CDA-AC-001',
            ],
            [
                'ANNEE_DVT' => 2024,
                'NUM_DVT' => 'DVT-2024-002',
                'DATE_DVT' => Carbon::parse('2024-02-20 14:15:00'),
                'MONT_AC_XOF' => 25000000.50,
                'MONT_FACT_XOF' => 25000000.50,
                'REF_DDU' => 'DDU-2024-002',
                'CDA_AC' => 'CDA-AC-002',
            ],
            [
                'ANNEE_DVT' => 2024,
                'NUM_DVT' => 'DVT-2024-003',
                'DATE_DVT' => Carbon::parse('2024-03-10 09:00:00'),
                'MONT_AC_XOF' => 18000000.75,
                'MONT_FACT_XOF' => 18000000.75,
                'REF_DDU' => 'DDU-2024-003',
                'CDA_AC' => 'CDA-AC-003',
            ],
            [
                'ANNEE_DVT' => 2024,
                'NUM_DVT' => 'DVT-2024-004',
                'DATE_DVT' => Carbon::parse('2024-04-05 11:45:00'),
                'MONT_AC_XOF' => 32000000.25,
                'MONT_FACT_XOF' => 32000000.25,
                'REF_DDU' => 'DDU-2024-004',
                'CDA_AC' => 'CDA-AC-004',
            ],
            [
                'ANNEE_DVT' => 2024,
                'NUM_DVT' => 'DVT-2024-005',
                'DATE_DVT' => Carbon::parse('2024-05-12 16:20:00'),
                'MONT_AC_XOF' => 45000000.00,
                'MONT_FACT_XOF' => 45000000.00,
                'REF_DDU' => 'DDU-2024-005',
                'CDA_AC' => 'CDA-AC-005',
            ],
            [
                'ANNEE_DVT' => 2024,
                'NUM_DVT' => 'DVT-2024-006',
                'DATE_DVT' => Carbon::parse('2024-06-18 08:30:00'),
                'MONT_AC_XOF' => 28000000.50,
                'MONT_FACT_XOF' => 28000000.50,
                'REF_DDU' => 'DDU-2024-006',
                'CDA_AC' => 'CDA-AC-006',
            ],
            [
                'ANNEE_DVT' => 2024,
                'NUM_DVT' => 'DVT-2024-007',
                'DATE_DVT' => Carbon::parse('2024-07-22 13:15:00'),
                'MONT_AC_XOF' => 19500000.75,
                'MONT_FACT_XOF' => 19500000.75,
                'REF_DDU' => 'DDU-2024-007',
                'CDA_AC' => 'CDA-AC-007',
            ],
            [
                'ANNEE_DVT' => 2024,
                'NUM_DVT' => 'DVT-2024-008',
                'DATE_DVT' => Carbon::parse('2024-08-30 10:00:00'),
                'MONT_AC_XOF' => 36000000.00,
                'MONT_FACT_XOF' => 36000000.00,
                'REF_DDU' => 'DDU-2024-008',
                'CDA_AC' => 'CDA-AC-008',
            ],
            [
                'ANNEE_DVT' => 2024,
                'NUM_DVT' => 'DVT-2024-009',
                'DATE_DVT' => Carbon::parse('2024-09-14 15:45:00'),
                'MONT_AC_XOF' => 22000000.25,
                'MONT_FACT_XOF' => 22000000.25,
                'REF_DDU' => 'DDU-2024-009',
                'CDA_AC' => 'CDA-AC-009',
            ],
            [
                'ANNEE_DVT' => 2024,
                'NUM_DVT' => 'DVT-2024-010',
                'DATE_DVT' => Carbon::parse('2024-10-25 12:30:00'),
                'MONT_AC_XOF' => 41000000.50,
                'MONT_FACT_XOF' => 41000000.50,
                'REF_DDU' => 'DDU-2024-010',
                'CDA_AC' => 'CDA-AC-010',
            ],
            [
                'ANNEE_DVT' => 2023,
                'NUM_DVT' => 'DVT-2023-001',
                'DATE_DVT' => Carbon::parse('2023-01-10 09:00:00'),
                'MONT_AC_XOF' => 12000000.00,
                'MONT_FACT_XOF' => 12000000.00,
                'REF_DDU' => 'DDU-2023-001',
                'CDA_AC' => 'CDA-AC-2023-001',
            ],
            [
                'ANNEE_DVT' => 2023,
                'NUM_DVT' => 'DVT-2023-002',
                'DATE_DVT' => Carbon::parse('2023-02-15 14:20:00'),
                'MONT_AC_XOF' => 19000000.75,
                'MONT_FACT_XOF' => 19000000.75,
                'REF_DDU' => 'DDU-2023-002',
                'CDA_AC' => 'CDA-AC-2023-002',
            ],
            [
                'ANNEE_DVT' => 2023,
                'NUM_DVT' => 'DVT-2023-003',
                'DATE_DVT' => Carbon::parse('2023-03-20 11:30:00'),
                'MONT_AC_XOF' => 27000000.25,
                'MONT_FACT_XOF' => 27000000.25,
                'REF_DDU' => 'DDU-2023-003',
                'CDA_AC' => 'CDA-AC-2023-003',
            ],
            [
                'ANNEE_DVT' => 2025,
                'NUM_DVT' => 'DVT-2025-001',
                'DATE_DVT' => Carbon::parse('2025-01-08 10:00:00'),
                'MONT_AC_XOF' => 50000000.00,
                'MONT_FACT_XOF' => 50000000.00,
                'REF_DDU' => 'DDU-2025-001',
                'CDA_AC' => 'CDA-AC-2025-001',
            ],
            [
                'ANNEE_DVT' => 2025,
                'NUM_DVT' => 'DVT-2025-002',
                'DATE_DVT' => Carbon::parse('2025-01-20 15:30:00'),
                'MONT_AC_XOF' => 38000000.50,
                'MONT_FACT_XOF' => 38000000.50,
                'REF_DDU' => 'DDU-2025-002',
                'CDA_AC' => 'CDA-AC-2025-002',
            ],
        ];

        $count = 0;
        $errors = 0;

        foreach ($banques as $banqueData) {
            try {
                // Générer un ULID manuellement
                $ulid = (string) \Illuminate\Support\Str::ulid();
                
                // Insérer directement avec DB pour éviter les timestamps
                \Illuminate\Support\Facades\DB::table('BANQUE')->insert([
                    'ID' => $ulid,
                    'ANNEE_DVT' => $banqueData['ANNEE_DVT'],
                    'NUM_DVT' => $banqueData['NUM_DVT'],
                    'DATE_DVT' => $banqueData['DATE_DVT'],
                    'MONT_AC_XOF' => $banqueData['MONT_AC_XOF'],
                    'MONT_FACT_XOF' => $banqueData['MONT_FACT_XOF'],
                    'REF_DDU' => $banqueData['REF_DDU'],
                    'CDA_AC' => $banqueData['CDA_AC'],
                ]);
                $count++;
            } catch (\Exception $e) {
                $errors++;
                $this->command->warn("Erreur lors de la création de l'enregistrement : " . $e->getMessage());
            }
        }

        $this->command->info("✅ {$count} enregistrement(s) BANQUE créé(s) avec succès avec ULIDs");
        if ($errors > 0) {
            $this->command->warn("⚠️  {$errors} erreur(s) rencontrée(s)");
        }
    }
}









