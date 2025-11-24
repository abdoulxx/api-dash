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
        Schema::create('declaration_sg', function (Blueprint $table) {
            $table->id();
            $table->integer('instanceid');
            $table->string('entrepot', 68)->nullable();
            $table->integer('annee')->nullable();
            $table->string('num_manifeste', 120)->nullable();
            $table->string('num_fdi', 60)->nullable();
            $table->string('num_bl', 120)->nullable();
            $table->string('typdec', 16)->nullable();
            $table->string('sens', 4)->nullable();
            $table->string('num_dossier', 200)->nullable();
            $table->string('declaration', 240)->nullable();
            $table->timestamp('date_declaration')->nullable();
            $table->string('provenance', 12)->nullable();
            $table->string('destination', 12)->nullable();
            $table->string('code_port_chargement', 20)->nullable();
            $table->string('nom_port_chargement', 140)->nullable();
            $table->string('code_mode_transport', 8)->nullable();
            $table->string('nom_mode_transport', 35)->nullable();
            $table->string('nom_navire', 140)->nullable();
            $table->string('condition_liv', 12)->nullable();
            $table->string('devise', 12)->nullable();
            $table->decimal('taux_conversion', 19, 4)->nullable();
            $table->string('banq_code', 68)->nullable();
            $table->string('bureau', 20)->nullable();
            $table->string('nom_bureau', 140)->nullable();
            $table->string('cc_exp', 68)->nullable();
            $table->text('exportateur')->nullable();
            $table->string('cc_imp', 68)->nullable();
            $table->text('importateur')->nullable();
            $table->string('code_destinataire_reel', 68)->nullable();
            $table->text('nom_destinataire_reel')->nullable();
            $table->string('codagr', 68)->nullable();
            $table->text('declarant')->nullable();
            $table->string('sous_regime', 28)->nullable();
            $table->integer('nbre_total_article')->nullable();
            $table->string('quittance', 240)->nullable();
            $table->timestamp('date_quittance')->nullable();
            $table->decimal('valeur_caf_declaration', 19, 4)->nullable();
            $table->decimal('nbre_colis', 19, 4)->nullable();
            $table->decimal('valeur_fob_declaration', 19, 4)->nullable();
            $table->decimal('droits_taxes_declaration', 19, 4)->nullable();
            $table->decimal('poids_brut_declaration', 19, 4)->nullable();
            $table->decimal('nombre_conteneur', 19, 4)->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('declaration_sg');
    }
};
