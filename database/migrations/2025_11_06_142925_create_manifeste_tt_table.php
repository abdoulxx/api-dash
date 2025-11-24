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
        Schema::create('manifeste_tt', function (Blueprint $table) {
            $table->string('etat_apurement', 9)->nullable();
            $table->integer('instance_id')->nullable();
            $table->string('code_bureau', 20)->nullable();
            $table->string('code_bureau_de_provenance', 20)->nullable();
            $table->string('nom_bureau', 140)->nullable();
            $table->string('num_voy_ds', 120)->nullable();
            $table->string('num_voy_de_provenance', 120)->nullable();
            $table->timestamp('date_arrive')->nullable();
            $table->string('num_titre_transport', 120)->nullable();
            $table->string('num_bl_provenance', 120)->nullable();
            $table->integer('annee_manifeste')->nullable();
            $table->integer('num_man_sydam')->nullable();
            $table->string('num_manifeste', 348)->nullable();
            $table->timestamp('date_manifeste')->nullable();
            $table->string('code_consignataire', 68)->nullable();
            $table->string('nom_consignataire', 140)->nullable();
            $table->text('adresse_consignataire')->nullable();
            $table->integer('ligne_manfeste')->nullable();
            $table->string('status_cns', 68)->nullable();
            $table->string('nature_cns', 8)->nullable();
            $table->integer('nombre_conteneur')->nullable();
            $table->decimal('poids_brut', 19, 4)->nullable();
            $table->decimal('poids_restant', 19, 4)->nullable();
            $table->string('type_cns', 12)->nullable();
            $table->string('libelle_cns', 140)->nullable();
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
            $table->string('lieu_charg', 20)->nullable();
            $table->string('nom_lieu_charg', 140)->nullable();
            $table->string('lieu_decharg', 20)->nullable();
            $table->string('nom_lieu_decharg', 140)->nullable();
            $table->text('nature_marchandise')->nullable();
            $table->text('description_marchandise')->nullable();
            $table->string('code_emballage', 68)->nullable();
            $table->string('nature_emballage', 140)->nullable();
            $table->decimal('nbr_colis', 19, 4)->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('manifeste_tt');
    }
};
