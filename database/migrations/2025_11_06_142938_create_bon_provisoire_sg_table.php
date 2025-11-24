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
        Schema::create('bon_provisoire_sg', function (Blueprint $table) {
            $table->id();
            $table->integer('instance_id')->nullable();
            $table->integer('annee')->nullable();
            $table->string('bureau', 20)->nullable();
            $table->string('serie_bp', 4)->nullable();
            $table->string('num_serie_bp', 40)->nullable();
            $table->string('numero_bon_provisoire', 224)->nullable();
            $table->timestamp('date_bp')->nullable();
            $table->string('num_vol_lta', 40)->nullable();
            $table->string('num_lta', 60)->nullable();
            $table->timestamp('date_lta')->nullable();
            $table->timestamp('date_expiration')->nullable();
            $table->integer('delai_jours')->nullable();
            $table->string('ncc', 32)->nullable();
            $table->string('nom_importateur', 400)->nullable();
            $table->string('nom_fournisseur', 140)->nullable();
            $table->string('pays_origine', 140)->nullable();
            $table->string('code_declarant', 68)->nullable();
            $table->string('nom_declarant', 140)->nullable();
            $table->string('type_bon_provisoire', 12)->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bon_provisoire_sg');
    }
};
