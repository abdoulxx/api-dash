<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('fdi_sg_comp_1', function (Blueprint $table) {
            $table->id();
            $table->integer('instance_id')->nullable();
            $table->string('serie_fdi', 4)->nullable();
            $table->string('bureau', 20)->nullable();
            $table->integer('annee')->nullable();
            $table->string('numero_serie', 24)->nullable();
            $table->string('numero_fdi', 60)->nullable();
            $table->timestamp('date_fdi')->nullable();
            $table->string('derniere_operation', 200)->nullable();
            $table->timestamp('date_derniere_operation')->nullable();
            $table->string('reglement', 280)->nullable();
            $table->string('banque', 140)->nullable();
            $table->string('ref_domiciliation', 35)->nullable();
            $table->timestamp('date_domiciliation')->nullable();
            $table->decimal('montant_domicilie_cfa', 19, 4)->nullable();
            $table->string('cc', 68)->nullable();
            $table->string('importateur', 255)->nullable();
            $table->string('adresse_importateur', 255)->nullable();
            $table->string('telephone_importateur', 400)->nullable();
            $table->string('fournisseur', 255)->nullable();
            $table->string('adresse_fournisseur', 255)->nullable();
            $table->string('pays_fournisseur', 140)->nullable();
            $table->string('tel_fournisseur', 400)->nullable();
            $table->string('fax_fournisseur', 400)->nullable();
            $table->string('incoterm', 12)->nullable();
            $table->string('libelle_incoterm', 140)->nullable();
            $table->string('ref_facture', 140)->nullable();
            $table->timestamp('date_facture')->nullable();
            $table->decimal('valeur_facture_cfa', 19, 4)->nullable();
            $table->decimal('valeur_fob_cfa', 19, 4)->nullable();
            $table->decimal('valeur_caf', 19, 4)->nullable();
            $table->decimal('valeur_fret_cfa', 19, 4)->nullable();
            $table->decimal('valeur_assurance_cfa', 19, 4)->nullable();
            $table->string('nom_devise', 140)->nullable();
            $table->decimal('devise', 19, 4)->nullable();
            $table->string('declarant', 140)->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fdi_sg_comp_1');
    }
};
