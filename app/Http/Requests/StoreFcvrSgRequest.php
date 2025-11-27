<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreFcvrSgRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'instanceid' => ['nullable', 'integer'],
            'num_tt' => ['nullable', 'integer'],
            'num_rfcv' => ['required', 'string', 'max:60'],
            'annee' => ['nullable', 'integer', 'digits:4'],
            'bureau' => ['nullable', 'string', 'max:20'],
            'num_fdi' => ['nullable', 'string', 'max:128'],
            'date_fdi' => ['nullable', 'date'],
            'num_voyage' => ['nullable', 'string', 'max:68'],
            'nom_navire' => ['nullable', 'string', 'max:200'],
            'num_bl' => ['nullable', 'string', 'max:30'],
            'date_arrivee' => ['nullable', 'date'],
            'nombre_conteneur' => ['nullable', 'integer'],
            'poids_net_total' => ['nullable', 'numeric'],
            'poids_brut_total' => ['nullable', 'numeric'],
            'nombre_total_colis' => ['nullable', 'numeric'],
            'lieu_chrg' => ['nullable', 'string', 'max:240'],
            'lieu_dechrg' => ['nullable', 'string', 'max:240'],
            'date_rfcv' => ['nullable', 'date'],
            'derniere_operation' => ['nullable', 'string', 'max:50'],
            'date_derniere_operation' => ['nullable', 'date'],
            'cc' => ['nullable', 'string', 'max:80'],
            'nom_importateur' => ['nullable', 'string', 'max:600'],
            'pays_importateur' => ['nullable', 'string', 'max:120'],
            'code_pays' => ['nullable', 'string', 'max:12'],
            'nom_pays_importateur' => ['nullable', 'string', 'max:140'],
            'nom_fournisseur' => ['nullable', 'string', 'max:600'],
            'pays_fournisseur' => ['nullable', 'string', 'max:120'],
            'code_declarant' => ['nullable', 'string', 'max:80'],
            'nom_declarant' => ['nullable', 'string', 'max:600'],
            'code_pays_origine' => ['nullable', 'string', 'max:12'],
            'nom_pays_origine' => ['nullable', 'string', 'max:140'],
            'nombre_total_article' => ['nullable', 'integer'],
            'numero_facture' => ['nullable', 'string', 'max:160'],
            'date_facture' => ['nullable', 'date'],
            'val_fact_rfcv_devise' => ['nullable', 'numeric'],
            'val_fact_rfcv_cfa' => ['nullable', 'numeric'],
            'observation' => ['nullable', 'string'],
            'incoterm' => ['nullable', 'string', 'max:40'],
            'devise' => ['nullable', 'string', 'max:40'],
            'taux_devise' => ['nullable', 'numeric'],
            'fob_rfcv' => ['nullable', 'numeric'],
            'fob_rfcv_cfa' => ['nullable', 'numeric'],
            'fret_rfcv' => ['nullable', 'numeric'],
            'fret_rfcv_cfa' => ['nullable', 'numeric'],
            'assurance_rfcv' => ['nullable', 'numeric'],
            'assurance_rfcv_cfa' => ['nullable', 'numeric'],
            'autres_couts_rfcv' => ['nullable', 'numeric'],
            'autres_rfcv_cfa' => ['nullable', 'numeric'],
            'caf_rfcv' => ['nullable', 'numeric'],
            'caf_rfcv_cfa' => ['nullable', 'numeric'],
            'num_declaration' => ['nullable', 'string', 'max:384'],
            'date_declaration' => ['nullable', 'date'],
        ];
    }
}
