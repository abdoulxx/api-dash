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
        Schema::create('fdi_article', function (Blueprint $table) {
            $table->id();
            $table->integer('instance_id')->nullable();
            $table->string('serie_fdi', 4)->nullable();
            $table->string('bureau', 20)->nullable();
            $table->integer('annee')->nullable();
            $table->string('numero_serie', 24)->nullable();
            $table->string('numero_fdi', 60)->nullable();
            $table->timestamp('date_fdi')->nullable();
            $table->integer('numart');
            $table->string('postar', 48)->nullable();
            $table->text('nature_marchandise')->nullable();
            $table->text('description_marchandise')->nullable();
            $table->decimal('quantite', 19, 4)->nullable();
            $table->decimal('poids_net', 19, 4)->nullable();
            $table->decimal('poids_brut', 19, 4)->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fdi_article');
    }
};
