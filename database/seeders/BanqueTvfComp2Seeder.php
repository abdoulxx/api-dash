<?php

namespace Database\Seeders;

use App\Models\BanqueTvfComp2;
use Illuminate\Database\Seeder;

class BanqueTvfComp2Seeder extends Seeder
{
    public function run(): void
    {
        $entries = [
            [
                'old_id' => 'TVF-COMP2-0001',
                'annee_fdi' => '2020',
                'num_fdi' => '36860',
                'date_fdi' => '2020-03-24',
                'status_fdi' => 'Validated',
                'statut_ac' => 'Approuvé',
                'num_demande_ac' => 'AC-2020-001',
                'ref_ddu' => 'DDU-2020-001',
                'bank_dom' => 'SGBCI',
                'num_dom' => 'DOM-2020-123',
                'pays_exp' => 'Togo',
                'mont_ac_xof' => 9000000,
                'mont_fact_xof' => 9000000,
            ],
            [
                'old_id' => 'TVF-COMP2-0105',
                'annee_fdi' => '2020',
                'num_fdi' => '3857',
                'date_fdi' => '2020-01-13',
                'status_fdi' => 'Validé',
                'statut_ac' => 'Validation manuelle',
                'num_demande_ac' => 'AC-2020-105',
                'ref_ddu' => 'DDU-TEST-001',
                'bank_dom' => 'ORABANK',
                'num_dom' => 'DOM-TEST-001',
                'pays_exp' => 'Allemagne',
                'dev_trans' => 'EUR',
                'dev_paiem' => 'EUR',
                'mont_fact_dev' => 30000,
                'mont_fact_xof' => 19650000,
                'mont_ac_dev' => 10000,
                'mont_ac_xof' => 6559570,
            ],
        ];

        foreach ($entries as $entry) {
            BanqueTvfComp2::updateOrCreate(
                ['old_id' => $entry['old_id']],
                $entry
            );
        }
    }
}

