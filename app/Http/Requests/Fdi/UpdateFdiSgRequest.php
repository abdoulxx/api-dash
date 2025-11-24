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
        $fdiId = $this->route('sg')?->id ?? null;

        return [
            'numero_fdi' => ['sometimes', 'string', 'max:60', 'unique:fdi_sg,numero_fdi,' . $fdiId],
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
}

