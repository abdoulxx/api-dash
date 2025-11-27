<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Vérifier si la colonne ulid n'existe pas déjà
        if (!Schema::hasColumn('bon_provisoire_sg', 'ulid')) {
            Schema::table('bon_provisoire_sg', function (Blueprint $table) {
                $table->string('ulid', 26)->nullable()->unique()->after('id');
            });

            // Générer des ULIDs pour les enregistrements existants
            DB::table('bon_provisoire_sg')
                ->whereNull('ulid')
                ->orderBy('id')
                ->chunkById(200, function ($records) {
                    foreach ($records as $record) {
                        DB::table('bon_provisoire_sg')
                            ->where('id', $record->id)
                            ->update(['ulid' => (string) Str::ulid()]);
                    }
                });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('bon_provisoire_sg', 'ulid')) {
            Schema::table('bon_provisoire_sg', function (Blueprint $table) {
                $table->dropColumn('ulid');
            });
        }
    }
};
