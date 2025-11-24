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
        Schema::create('fcvr_article', function (Blueprint $table) {
            $table->integer('instanceid')->nullable();
            $table->string('annee', 4)->nullable();
            $table->string('num_rfcv', 60)->nullable();
            $table->timestamp('date_rfcv')->nullable();
            $table->decimal('fob_article', 19, 4)->nullable();
            $table->integer('nombre_total_article')->nullable();
            $table->integer('num_article')->nullable();
            $table->decimal('caf_article', 19, 4)->nullable();
            $table->decimal('quantite_article', 19, 4)->nullable();
            $table->string('unite_quantite', 44)->nullable();
            $table->string('sh_rfcv', 40)->nullable();
            $table->text('libelle_sh_rfcv')->nullable();
            $table->decimal('fob_declaree', 19, 4)->nullable();
            $table->decimal('caf_declaree', 19, 4)->nullable();
            $table->decimal('quantite_declaree', 19, 4)->nullable();
            $table->string('sh_declaration', 40)->nullable();
            $table->string('libelle_sh_declaration', 700)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fcvr_article');
    }
};
