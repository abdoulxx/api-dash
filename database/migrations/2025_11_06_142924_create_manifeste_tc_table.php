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
        Schema::create('manifeste_tc', function (Blueprint $table) {
            $table->string('etat_apurement', 9)->nullable();
            $table->integer('instance_id')->primary();
            $table->string('code_bureau', 20)->nullable();
            $table->string('code_bureau_de_provenance', 20)->nullable();
            $table->string('nom_bureau', 140)->nullable();
            $table->string('numero_voyage', 120)->nullable();
            $table->string('numero_voyage_de_provenance', 120)->nullable();
            $table->timestamp('date_arrive')->nullable();
            $table->string('numero_bl', 120)->nullable();
            $table->string('numero_bl_provenance', 120)->nullable();
            $table->integer('annee_manifeste')->nullable();
            $table->integer('num_man_sydam')->nullable();
            $table->string('num_manifeste', 348)->nullable();
            $table->timestamp('date_manifeste')->nullable();
            $table->string('code_consignataire', 68)->nullable();
            $table->string('nom_consignataire', 140)->nullable();
            $table->text('adresse_manifeste')->nullable();
            $table->integer('ligne_manfeste')->nullable();
            $table->string('status_manifeste', 68)->nullable();
            $table->string('nature_manifeste', 8)->nullable();
            $table->integer('nombre_conteneur')->nullable();
            $table->decimal('poids_brut', 19, 4)->nullable();
            $table->decimal('poids_restant', 19, 4)->nullable();
            $table->string('type_manifeste', 12)->nullable();
            $table->string('libelle_manifeste', 140)->nullable();
            $table->string('code_exportateur', 68)->nullable();
            $table->string('nom_exportateur', 140)->nullable();
            $table->string('code_importateur', 68)->nullable();
            $table->string('nom_importateur', 140)->nullable();
            $table->string('nom_navire', 600)->nullable();
            $table->string('code_mode_transport', 12)->nullable();
            $table->string('nom_mode_transport', 140)->nullable();
            $table->string('nationalite_navire', 140)->nullable();
            $table->string('code_nationalite_navire', 12)->nullable();
            $table->string('notifie_a', 140)->nullable();
            $table->text('adresse_notifie_a')->nullable();
            $table->string('code_port_chargement', 20)->nullable();
            $table->string('nom_port_chargement', 140)->nullable();
            $table->string('code_port_dechargement', 20)->nullable();
            $table->string('nom_port_dechargement', 140)->nullable();
            $table->string('code_emballage', 68)->nullable();
            $table->string('nature_emballage', 140)->nullable();
            $table->decimal('nombre_colis', 19, 4)->nullable();
            $table->integer('nbre_colis_conteneur')->nullable();
            $table->string('num_conteneur', 68)->nullable();
            $table->string('taille_conteneur', 16)->nullable();
            $table->string('plomb1', 80)->nullable();
            $table->string('plomb2', 80)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('manifeste_tc');
    }
};
