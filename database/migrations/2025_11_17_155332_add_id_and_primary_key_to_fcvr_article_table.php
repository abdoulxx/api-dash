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
        Schema::table('fcvr_article', function (Blueprint $table) {
            // Ajouter la colonne id en premier (avant les autres colonnes)
            if (!Schema::hasColumn('fcvr_article', 'id')) {
                // Pour PostgreSQL, on doit ajouter la colonne avec auto-increment
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
        Schema::table('fcvr_article', function (Blueprint $table) {
            if (Schema::hasColumn('fcvr_article', 'id')) {
                $table->dropColumn('id');
            }
        });
    }
};
