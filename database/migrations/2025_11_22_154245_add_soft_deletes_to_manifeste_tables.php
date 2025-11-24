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
        // Ajouter deleted_at à manifeste_sg si elle n'existe pas
        if (!Schema::hasColumn('manifeste_sg', 'deleted_at')) {
            Schema::table('manifeste_sg', function (Blueprint $table) {
                $table->softDeletes();
            });
        }

        // Ajouter deleted_at à manifeste_tt si elle n'existe pas
        if (!Schema::hasColumn('manifeste_tt', 'deleted_at')) {
            Schema::table('manifeste_tt', function (Blueprint $table) {
                $table->softDeletes();
            });
        }

        // Ajouter deleted_at à manifeste_tc si elle n'existe pas
        if (!Schema::hasColumn('manifeste_tc', 'deleted_at')) {
            Schema::table('manifeste_tc', function (Blueprint $table) {
                $table->softDeletes();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('manifeste_sg', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });

        Schema::table('manifeste_tt', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });

        Schema::table('manifeste_tc', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
    }
};
