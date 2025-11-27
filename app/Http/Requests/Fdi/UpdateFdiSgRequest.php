<?php

namespace App\Http\Requests\Fdi;

use Illuminate\Foundation\Http\FormRequest;

class UpdateFdiSgRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        // Récupérer la FDI depuis le paramètre de route
        $fdi = $this->route('fdi_sg') ?? $this->route('sg');
        $fdiId = $fdi?->id ?? null;
        $currentNumeroFdi = $fdi?->numero_fdi ?? null;

        // Règle pour numero_fdi : uniquement si modifié
        $numeroFdiRule = ['sometimes', 'string', 'max:60'];
        
        // Si numero_fdi est fourni et différent de la valeur actuelle, vérifier l'unicité
        if ($this->has('numero_fdi') && $this->input('numero_fdi') !== $currentNumeroFdi) {
            if ($fdiId) {
                $numeroFdiRule[] = 'unique:fdi_sg,numero_fdi,' . $fdiId;
            } else {
                $numeroFdiRule[] = 'unique:fdi_sg,numero_fdi';
            }
        }
        // Si numero_fdi n'est pas fourni ou est identique, pas de vérification d'unicité

        return [
            'numero_fdi' => $numeroFdiRule,
            'serie_fdi' => ['sometimes', 'nullable', 'string', 'max:4'],
            'bureau' => ['sometimes', 'nullable', 'string', 'max:20'],
            'annee' => ['sometimes', 'nullable', 'integer'],
            'numero_serie' => ['sometimes', 'nullable', 'string', 'max:24'],
            'date_fdi' => ['sometimes', 'nullable', 'date'],
            'derniere_operation' => ['sometimes', 'nullable', 'string', 'max:200'],
            'date_derniere_operation' => ['sometimes', 'nullable', 'date'],
            'reglement' => ['sometimes', 'nullable', 'string', 'max:280'],
            'banque' => ['sometimes', 'nullable', 'string', 'max:140'],
            'ref_domiciliation' => ['sometimes', 'nullable', 'string', 'max:35'],
            'date_domiciliation' => ['sometimes', 'nullable', 'date'],
            'montant_domicilie_cfa' => ['sometimes', 'nullable', 'numeric'],
            'cc' => ['sometimes', 'nullable', 'string', 'max:68'],
            'importateur' => ['sometimes', 'nullable', 'string'],
            'adresse_importateur' => ['sometimes', 'nullable', 'string'],
            'telephone_importateur' => ['sometimes', 'nullable', 'string', 'max:400'],
            'fournisseur' => ['sometimes', 'nullable', 'string'],
            'adresse_fournisseur' => ['sometimes', 'nullable', 'string'],
            'pays_fournisseur' => ['sometimes', 'nullable', 'string', 'max:140'],
            'tel_fournisseur' => ['sometimes', 'nullable', 'string', 'max:400'],
            'fax_fournisseur' => ['sometimes', 'nullable', 'string', 'max:400'],
            'incoterm' => ['sometimes', 'nullable', 'string', 'max:12'],
            'libelle_incoterm' => ['sometimes', 'nullable', 'string', 'max:140'],
            'ref_facture' => ['sometimes', 'nullable', 'string', 'max:140'],
            'date_facture' => ['sometimes', 'nullable', 'date'],
            'valeur_facture_cfa' => ['sometimes', 'nullable', 'numeric'],
            'valeur_fob_cfa' => ['sometimes', 'nullable', 'numeric'],
            'valeur_caf' => ['sometimes', 'nullable', 'numeric'],
            'valeur_fret_cfa' => ['sometimes', 'nullable', 'numeric'],
            'valeur_assurance_cfa' => ['sometimes', 'nullable', 'numeric'],
            'nom_devise' => ['sometimes', 'nullable', 'string', 'max:140'],
            'devise' => ['sometimes', 'nullable', 'numeric'],
            'declarant' => ['sometimes', 'nullable', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'numero_fdi.unique' => 'Ce numéro FDI est déjà utilisé par une autre FDI. Veuillez choisir un numéro unique.',
            'numero_fdi.max' => 'Le numéro FDI ne peut pas dépasser 60 caractères.',
            'serie_fdi.max' => 'La série FDI ne peut pas dépasser 4 caractères.',
            'bureau.max' => 'Le code bureau ne peut pas dépasser 20 caractères.',
            'numero_serie.max' => 'Le numéro de série ne peut pas dépasser 24 caractères.',
        ];
    }
}







