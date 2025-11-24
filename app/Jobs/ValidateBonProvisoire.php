<?php

namespace App\Jobs;

use App\Models\BonProvisoireSg;
use App\Services\AuditService;
use App\Support\CacheTagger;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ValidateBonProvisoire implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public readonly string $bonProvisoireId,
        public readonly string $cacheKey
    ) {
        $this->onQueue('bons-provisoires');
    }

    public function handle(): void
    {
        $bon = BonProvisoireSg::where('id', $this->bonProvisoireId)->firstOrFail();
        
        // Champs obligatoires
        $requiredFields = [
            'numero_bon_provisoire' => 'Numéro du Bon Provisoire',
            'num_lta' => 'Numéro LTA',
        ];

        // Champs recommandés
        $recommendedFields = [
            'date_bp' => 'Date du Bon Provisoire',
            'date_expiration' => 'Date d\'expiration',
            'nom_importateur' => 'Nom de l\'importateur',
            'nom_fournisseur' => 'Nom du fournisseur',
            'ncc' => 'NCC',
        ];

        // Vérifier les champs obligatoires
        $missing = collect($requiredFields)
            ->filter(fn ($label, $field) => blank($bon->{$field}))
            ->map(fn ($label, $field) => [
                'field' => $field,
                'label' => $label,
            ])
            ->values();

        // Vérifier les champs recommandés
        $missingRecommended = collect($recommendedFields)
            ->filter(fn ($label, $field) => blank($bon->{$field}))
            ->map(fn ($label, $field) => [
                'field' => $field,
                'label' => $label,
            ])
            ->values();

        $isValid = $missing->isEmpty();
        $isComplete = $missing->isEmpty() && $missingRecommended->isEmpty();
        $articlesCount = $bon->articles()->count();

        $result = [
            'valid' => $isValid,
            'complete' => $isComplete,
            'missing_fields' => $missing->toArray(),
            'missing_recommended_fields' => $missingRecommended->toArray(),
            'validation_summary' => [
                'total_required_fields' => count($requiredFields),
                'filled_required_fields' => count($requiredFields) - $missing->count(),
                'total_recommended_fields' => count($recommendedFields),
                'filled_recommended_fields' => count($recommendedFields) - $missingRecommended->count(),
            ],
            'bon_provisoire_info' => [
                'id' => $bon->id,
                'numero_bon_provisoire' => $bon->numero_bon_provisoire,
                'instance_id' => $bon->instance_id,
                'date_bp' => $bon->date_bp,
                'date_expiration' => $bon->date_expiration,
                'articles_count' => $articlesCount,
            ],
            'validated_at' => now()->toDateTimeString(),
        ];

        // Store result in cache
        CacheTagger::tags(['bons-provisoires'])
            ->put($this->cacheKey, $result, now()->addHour());

        // Log the validation
        AuditService::log('validate', "Validation du bon provisoire \"{$bon->numero_bon_provisoire}\" - " . ($isValid ? ($isComplete ? 'Valide et complet' : 'Valide mais incomplet') : 'Invalide'), 'BonProvisoireSg', $bon->id, null, $result);
    }
}

