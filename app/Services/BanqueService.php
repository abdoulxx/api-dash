<?php

namespace App\Services;

use App\Models\BanqueSad;
use App\Models\BanqueTvf;
use Illuminate\Support\Facades\Log;

class BanqueService
{
    public function processPayment(BanqueSad $banqueSad): array
    {
        $status = $banqueSad->statut_ac === 'APPROVED';

        Log::channel('fdi')->info('Traitement paiement BanqueSad', [
            'banque_sad_id' => $banqueSad->id,
            'status' => $status ? 'processed' : 'pending',
        ]);

        return [
            'processed' => $status,
            'reference' => $banqueSad->num_ddu,
        ];
    }

    public function verifyPayment(BanqueSad $banqueSad): array
    {
        $isValid = ! blank($banqueSad->num_dom) && ! blank($banqueSad->date_dom);

        return [
            'valid' => $isValid,
            'missing' => $isValid ? [] : collect(['num_dom', 'date_dom'])
                ->filter(fn ($field) => blank($banqueSad->{$field}))
                ->values()
                ->all(),
        ];
    }

    public function verifyDomiciliation(BanqueTvf $banqueTvf): bool
    {
        return ! blank($banqueTvf->num_dom) && ! blank($banqueTvf->date_dom);
    }
}




