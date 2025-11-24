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
        Schema::table('fcvr_sg', function (Blueprint $table) {
            // Ajouter la colonne id en premier (avant les autres colonnes)
            if (!Schema::hasColumn('fcvr_sg', 'id')) {
                // Pour PostgreSQL, on doit ajouter la colonne sans AUTO_INCREMENT d'abord
                // puis créer une séquence et l'utiliser
                if (Schema::getConnection()->getDriverName() === 'pgsql') {
                    $table->bigIncrements('id')->first();
                } else {
                    $table->id()->first();
                }
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('fcvr_sg', function (Blueprint $table) {
            if (Schema::hasColumn('fcvr_sg', 'id')) {
                $table->dropColumn('id');
            }
        });
    }
};
