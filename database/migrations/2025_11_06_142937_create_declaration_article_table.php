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
        Schema::create('declaration_article', function (Blueprint $table) {
            $table->id();
            $table->integer('instanceid');
            $table->string('entrepot', 68)->nullable();
            $table->integer('annee')->nullable();
            $table->string('num_manifeste', 120)->nullable();
            $table->string('num_bl', 120)->nullable();
            $table->string('typdec', 16)->nullable();
            $table->string('sens', 4)->nullable();
            $table->string('num_dossier', 200)->nullable();
            $table->string('declaration', 240)->nullable();
            $table->timestamp('date_declaration')->nullable();
            $table->string('provenance', 12)->nullable();
            $table->string('origine', 12)->nullable();
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
            $table->string('postar', 44)->nullable();
            $table->text('libelle_postar')->nullable();
            $table->string('marque1', 200)->nullable();
            $table->string('marque2', 200)->nullable();
            $table->string('emballage', 140)->nullable();
            $table->string('unite_apurement', 12)->nullable();
            $table->decimal('nbre_colis', 19, 4)->nullable();
            $table->integer('nbre_total_article')->nullable();
            $table->string('sous_regime', 28)->nullable();
            $table->integer('numero_article');
            $table->string('quittance', 240)->nullable();
            $table->timestamp('date_quittance')->nullable();
            $table->decimal('valcaf', 19, 4)->nullable();
            $table->decimal('valfob', 19, 4)->nullable();
            $table->decimal('valdou', 19, 4)->nullable();
            $table->decimal('valtax', 19, 4)->nullable();
            $table->decimal('valfret_ext', 19, 4)->nullable();
            $table->decimal('valfret_int', 19, 4)->nullable();
            $table->decimal('valass', 19, 4)->nullable();
            $table->decimal('autre_cout', 19, 4)->nullable();
            $table->decimal('droits_taxes', 19, 4)->nullable();
            $table->decimal('taxe_susp', 19, 4)->nullable();
            $table->decimal('poids_net', 19, 4)->nullable();
            $table->decimal('poids_brut', 19, 4)->nullable();
            $table->string('credit_paiement', 68)->nullable();
            $table->string('mode_paiement', 140)->nullable();
            $table->timestamp('date_annulation')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('declaration_article');
    }
};
