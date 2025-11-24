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
        Schema::create('manifeste_sg', function (Blueprint $table) {
            $table->integer('instance_id')->primary();
            $table->string('code_bureau', 20)->nullable();
            $table->string('libelle_bureau', 140)->nullable();
            $table->string('num_voyage', 120)->nullable();
            $table->timestamp('date_voyage')->nullable();
            $table->integer('nbre_total_bl')->nullable();
            $table->decimal('nbre_total_colis', 19, 4)->nullable();
            $table->integer('nbre_total_conteneur')->nullable();
            $table->decimal('total_poids_brut', 19, 4)->nullable();
            $table->timestamp('date_arrivee_navire')->nullable();
            $table->integer('annee_manifeste')->nullable();
            $table->integer('num_man_sydam')->nullable();
            $table->string('num_manifeste', 348)->nullable();
            $table->timestamp('date_manifeste')->nullable();
            $table->string('code_port_charg', 20)->nullable();
            $table->string('nom_port_charg', 140)->nullable();
            $table->string('code_port_decharg', 20)->nullable();
            $table->string('nom_port_decharg', 140)->nullable();
            $table->string('code_consignataire', 68)->nullable();
            $table->string('nom_consignataire', 140)->nullable();
            $table->text('adresse_consignataire')->nullable();
            $table->string('nom_moyen_transport', 200)->nullable();
            $table->string('code_transport', 12)->nullable();
            $table->string('nom_transport', 140)->nullable();
            $table->string('code_nationalite_navire', 12)->nullable();
            $table->string('nom_nationalite_navire', 140)->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('manifeste_sg');
    }
};
