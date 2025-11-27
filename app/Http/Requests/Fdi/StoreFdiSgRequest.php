<?php

namespace App\Http\Requests\Fdi;

use Illuminate\Foundation\Http\FormRequest;

class StoreFdiSgRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'numero_fdi' => ['required', 'string', 'max:60', 'unique:fdi_sg,numero_fdi'],
            'serie_fdi' => ['nullable', 'string', 'max:4'],
            'bureau' => ['nullable', 'string', 'max:20'],
            'annee' => ['nullable', 'integer'],
            'numero_serie' => ['nullable', 'string', 'max:24'],
            'date_fdi' => ['nullable', 'date'],
            'derniere_operation' => ['nullable', 'string', 'max:200'],
            'date_derniere_operation' => ['nullable', 'date'],
            'reglement' => ['nullable', 'string', 'max:280'],
            'banque' => ['nullable', 'string', 'max:140'],
            'ref_domiciliation' => ['nullable', 'string', 'max:35'],
            'date_domiciliation' => ['nullable', 'date'],
            'montant_domicilie_cfa' => ['nullable', 'numeric'],
            'cc' => ['nullable', 'string', 'max:68'],
            'importateur' => ['nullable', 'string'],
            'adresse_importateur' => ['nullable', 'string'],
            'telephone_importateur' => ['nullable', 'string', 'max:400'],
            'fournisseur' => ['nullable', 'string'],
            'adresse_fournisseur' => ['nullable', 'string'],
            'pays_fournisseur' => ['nullable', 'string', 'max:140'],
            'tel_fournisseur' => ['nullable', 'string', 'max:400'],
            'fax_fournisseur' => ['nullable', 'string', 'max:400'],
            'incoterm' => ['nullable', 'string', 'max:12'],
            'libelle_incoterm' => ['nullable', 'string', 'max:140'],
            'ref_facture' => ['nullable', 'string', 'max:140'],
            'date_facture' => ['nullable', 'date'],
            'valeur_facture_cfa' => ['nullable', 'numeric'],
            'valeur_fob_cfa' => ['nullable', 'numeric'],
            'valeur_caf' => ['nullable', 'numeric'],
            'valeur_fret_cfa' => ['nullable', 'numeric'],
            'valeur_assurance_cfa' => ['nullable', 'numeric'],
            'nom_devise' => ['nullable', 'string', 'max:140'],
            'devise' => ['nullable', 'numeric'],
            'declarant' => ['nullable', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'numero_fdi.required' => 'Le numéro FDI est obligatoire.',
            'numero_fdi.unique' => 'Ce numéro FDI est déjà utilisé. Veuillez choisir un numéro unique.',
            'numero_fdi.max' => 'Le numéro FDI ne peut pas dépasser 60 caractères.',
            'serie_fdi.max' => 'La série FDI ne peut pas dépasser 4 caractères.',
            'bureau.max' => 'Le code bureau ne peut pas dépasser 20 caractères.',
            'numero_serie.max' => 'Le numéro de série ne peut pas dépasser 24 caractères.',
        ];
    }
}









