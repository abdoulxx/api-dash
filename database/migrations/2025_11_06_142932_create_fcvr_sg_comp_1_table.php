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
        Schema::create('fcvr_sg_comp_1', function (Blueprint $table) {
            $table->integer('instanceid');
            $table->string('annee', 4)->nullable();
            $table->integer('num_tt');
            $table->string('bureau', 20)->nullable();
            $table->string('num_fdi', 128)->nullable();
            $table->timestamp('date_fdi')->nullable();
            $table->string('num_voyage', 68)->nullable();
            $table->string('nom_navire', 200)->nullable();
            $table->string('num_bl', 30)->nullable();
            $table->timestamp('date_arrivee')->nullable();
            $table->integer('nombre_conteneur')->nullable();
            $table->decimal('poids_net_total', 19, 4)->nullable();
            $table->decimal('poids_brut_total', 19, 4)->nullable();
            $table->string('nombre_total_colis', 80)->nullable();
            $table->string('nbre_total_colis', 80)->nullable();
            $table->string('lieu_chrg', 240)->nullable();
            $table->string('lieu_dechrg', 240)->nullable();
            $table->string('num_rfcv', 60);
            $table->timestamp('date_rfcv')->nullable();
            $table->string('derniere_operation', 200)->nullable();
            $table->timestamp('date_derniere_operation')->nullable();
            $table->string('cc', 80)->nullable();
            $table->string('nom_importateur', 600)->nullable();
            $table->string('pays_importateur', 120)->nullable();
            $table->string('code_pays', 12)->nullable();
            $table->string('nom_pays_importateur', 140)->nullable();
            $table->string('nom_fournisseur', 600)->nullable();
            $table->string('pays_fournisseur', 120)->nullable();
            $table->string('code_declarant', 80)->nullable();
            $table->string('nom_declarant', 600)->nullable();
            $table->string('code_pays_origine', 12)->nullable();
            $table->string('nom_pays_origine', 140)->nullable();
            $table->integer('nombre_total_article')->nullable();
            $table->string('numero_facture', 160)->nullable();
            $table->timestamp('date_facture')->nullable();
            $table->decimal('val_fact_rfcv_devise', 19, 4)->nullable();
            $table->decimal('val_fact_rfcv_cfa', 19, 4)->nullable();
            $table->text('observation')->nullable();
            $table->string('incoterm', 40)->nullable();
            $table->string('devise', 12)->nullable();
            $table->decimal('taux_devise', 19, 4)->nullable();
            $table->decimal('fob_rfcv', 19, 4)->nullable();
            $table->decimal('fob_rfcv_cfa', 19, 4)->nullable();
            $table->decimal('fret_rfcv', 19, 4)->nullable();
            $table->decimal('fret_rfcv_cfa', 19, 4)->nullable();
            $table->decimal('assurance_rfcv', 19, 4)->nullable();
            $table->decimal('assurance_rfcv_cfa', 19, 4)->nullable();
            $table->decimal('autres_couts_rfcv', 19, 4)->nullable();
            $table->decimal('autres_rfcv_cfa', 19, 4)->nullable();
            $table->decimal('caf_rfcv', 19, 4)->nullable();
            $table->decimal('caf_rfcv_cfa', 19, 4)->nullable();
            $table->string('num_declaration', 384)->nullable();
            $table->timestamp('date_declaration')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fcvr_sg_comp_1');
    }
};
