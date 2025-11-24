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
        Schema::create('declaration_tc', function (Blueprint $table) {
            $table->id();
            $table->integer('instanceid');
            $table->string('annee', 4)->nullable();
            $table->string('num_manifeste', 120)->nullable();
            $table->string('num_bl', 120)->nullable();
            $table->string('numenr', 240)->nullable();
            $table->string('numero_conteneur', 68)->nullable();
            $table->string('taille_conteneur', 16)->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('declaration_tc');
    }
};
