<?php

namespace Database\Seeders;

use App\Models\BanqueTvf;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;

class BanqueTvfSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $sqlFile = base_path('../s360_ analyse/banque_tvf_inserts.sql');

        if (!File::exists($sqlFile)) {
            $this->command?->warn("Fichier SQL non trouvé : {$sqlFile}");
            $this->command?->warn('Création de quelques enregistrements TVF de test...');
            $this->createTestData();
            return;
        }

        $existingCount = BanqueTvf::count();
        if ($existingCount > 0) {
            $this->command?->info("Suppression de {$existingCount} enregistrement(s) TVF existant(s)...");
            BanqueTvf::truncate();
        }

        $content = File::get($sqlFile);
        preg_match_all('/INSERT INTO BANQUE_TVF VALUES \((.*?)\);/s', $content, $matches);

        $count = 0;
        $errors = 0;

        foreach ($matches[1] as $valuesString) {
            try {
                $values = $this->parseValues($valuesString);

                BanqueTvf::create([
                    'old_id' => $this->parseValue($values[0] ?? null),
                    'annee_fdi' => $this->parseValue($values[1] ?? null),
                    'num_fdi' => $this->parseDecimal($values[2] ?? null),
                    'date_fdi' => $this->parseDate($values[3] ?? null),
                    'status_fdi' => $this->parseValue($values[4] ?? null),
                    'date_autorisation_fdi' => $this->parseDate($values[5] ?? null),
                    'date_expiration_fdi' => $this->parseDate($values[6] ?? null),
                    'statut_ac' => $this->parseValue($values[7] ?? null),
                    'base_sur_ac' => $this->parseValue($values[8] ?? null),
                    'num_demande_ac' => $this->parseValue($values[9] ?? null),
                    'date_dem_ac' => $this->parseDate($values[10] ?? null),
                    'ref_ddu' => $this->parseValue($values[11] ?? null),
                    'bank_dom' => $this->parseValue($values[12] ?? null),
                    'date_dom' => $this->parseDate($values[13] ?? null),
                    'num_dom' => $this->parseValue($values[14] ?? null),
                    'banq_enreg' => $this->parseValue($values[15] ?? null),
                    'num_enreg_banq' => $this->parseValue($values[16] ?? null),
                    'date_aprob_bank' => $this->parseDate($values[17] ?? null),
                    'date_aprob_bank2' => $this->parseDate($values[18] ?? null),
                    'pays_exp' => $this->parseValue($values[19] ?? null),
                    'autorise_par' => $this->parseValue($values[20] ?? null),
                    'adr_exp' => $this->parseValue($values[21] ?? null),
                    'code_cda_ac' => $this->parseValue($values[22] ?? null),
                    'cda_ac' => $this->parseValue($values[23] ?? null),
                    'benef_fonds' => $this->parseValue($values[24] ?? null),
                    'adr_benef' => $this->parseValue($values[25] ?? null),
                    'type_op' => $this->parseValue($values[26] ?? null),
                    'dev_trans' => $this->parseValue($values[27] ?? null),
                    'dev_paiem' => $this->parseValue($values[28] ?? null),
                    'mont_fact_dev' => $this->parseDecimal($values[29] ?? null),
                    'mont_fact_xof' => $this->parseDecimal($values[30] ?? null),
                    'mont_ac_dev' => $this->parseDecimal($values[31] ?? null),
                    'mont_ac_xof' => $this->parseDecimal($values[32] ?? null),
                    'mont_tot_march_xof' => $this->parseValue($values[33] ?? null),
                    'solde_dev' => $this->parseDecimal($values[34] ?? null),
                ]);

                $count++;

                if ($count % 100 === 0) {
                    $this->command?->info("Importé {$count} enregistrement(s) TVF...");
                }
            } catch (\Throwable $e) {
                $errors++;
                if ($errors <= 10) {
                    $this->command?->warn("Erreur lors de l'import (ligne {$count}): " . $e->getMessage());
                }
            }
        }

        $message = "✅ {$count} enregistrement(s) TVF importé(s)";
        if ($errors > 0) {
            $message .= " ({$errors} erreur(s))";
        }
        $this->command?->info($message);
    }

    private function parseValues(string $valuesString): array
    {
        $values = [];
        $current = '';
        $inQuotes = false;
        $quoteChar = null;

        $valuesString = str_replace(["\r\n", "\n", "\r"], ' ', $valuesString);

        $length = strlen($valuesString);
        for ($i = 0; $i < $length; $i++) {
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

    private function parseValue(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim($value);

        if ($value === '' || strtoupper($value) === 'NULL') {
            return null;
        }

        if ((str_starts_with($value, "'") && str_ends_with($value, "'")) ||
            (str_starts_with($value, '"') && str_ends_with($value, '"'))) {
            $value = substr($value, 1, -1);
        }

        return $value === '' ? null : str_replace("''", "'", $value);
    }

    private function parseDate(?string $value): ?string
    {
        $parsed = $this->parseValue($value);
        if (!$parsed) {
            return null;
        }

        try {
            return \Carbon\Carbon::parse($parsed)->toDateTimeString();
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function parseDecimal(?string $value): ?float
    {
        $parsed = $this->parseValue($value);

        if ($parsed === null || $parsed === '') {
            return null;
        }

        return is_numeric($parsed) ? (float) $parsed : null;
    }

    private function createTestData(): void
    {
        BanqueTvf::create([
            'old_id' => 'TEST-001',
            'annee_fdi' => '2024',
            'num_fdi' => 12345,
            'date_fdi' => now()->subDays(30),
            'status_fdi' => 'APPROUVE',
            'statut_ac' => 'TVF',
            'base_sur_ac' => 'TVF',
            'num_demande_ac' => 'EA2024-0001',
            'ref_ddu' => 'DDU-2024-001',
            'bank_dom' => 'ECOBANK',
            'date_dom' => now()->subDays(28),
            'num_dom' => 'DOM-2024-001',
            'banq_enreg' => 'ECOBANK',
            'num_enreg_banq' => 'REG-2024-001',
            'date_aprob_bank' => now()->subDays(25),
            'pays_exp' => 'France',
            'autorise_par' => 'DG Douanes',
            'type_op' => 'IMPORTATION NON EFFECTIVE',
            'dev_trans' => 'Euro',
            'dev_paiem' => 'Euro',
            'mont_fact_dev' => 15000,
            'mont_fact_xof' => 9835000,
            'mont_ac_dev' => 15000,
            'mont_ac_xof' => 9835000,
            'mont_tot_march_xof' => '0',
            'solde_dev' => 15000,
        ]);
    }
}

