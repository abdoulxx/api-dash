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
        Schema::create('bon_provisoire_article', function (Blueprint $table) {
            $table->id();
            $table->integer('instance_id')->nullable();
            $table->integer('annee')->nullable();
            $table->string('bureau', 20)->nullable();
            $table->string('serie_bp', 4)->nullable();
            $table->string('num_serie_bp', 40)->nullable();
            $table->string('numero_bon_provisoire', 224)->nullable();
            $table->timestamp('date_bp')->nullable();
            $table->timestamp('date_expiration')->nullable();
            $table->integer('delai_jours')->nullable();
            $table->string('num_vol_lta', 40)->nullable();
            $table->string('num_lta', 60)->nullable();
            $table->timestamp('date_lta')->nullable();
            $table->string('ncc', 32)->nullable();
            $table->string('nom_importateur', 400)->nullable();
            $table->string('nom_fournisseur', 140)->nullable();
            $table->string('pays_origine', 140)->nullable();
            $table->string('code_declarant', 68)->nullable();
            $table->string('nom_declarant', 140)->nullable();
            $table->string('type_bon_provisoire', 12)->nullable();
            $table->string('postar', 48)->nullable();
            $table->text('libelle_marchandise')->nullable();
            $table->decimal('poids_net_kgs', 19, 4)->nullable();
            $table->string('code_devise', 12)->nullable();
            $table->decimal('montant_devise', 19, 4)->nullable();
            $table->decimal('valeur_fob_article', 19, 4)->nullable();
            $table->decimal('valeur_fob_cfa', 19, 4)->nullable();
            $table->decimal('valeur_fret_article', 19, 4)->nullable();
            $table->decimal('valeur_fret_article_cfa', 19, 4)->nullable();
            $table->string('num_declaration', 384)->nullable();
            $table->timestamp('datenr')->nullable();
            $table->decimal('nbre_colis', 19, 4)->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bon_provisoire_article');
    }
};
