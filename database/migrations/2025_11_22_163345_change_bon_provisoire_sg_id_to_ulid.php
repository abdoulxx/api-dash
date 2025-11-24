<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Vérifier si la colonne id existe et est de type bigint
        if (Schema::hasColumn('bon_provisoire_sg', 'id')) {
            // Supprimer l'ancienne clé primaire
            Schema::table('bon_provisoire_sg', function (Blueprint $table) {
                $table->dropPrimary(['id']);
            });

            // Créer une colonne temporaire pour stocker les ULIDs
            Schema::table('bon_provisoire_sg', function (Blueprint $table) {
                $table->char('id_new', 26)->nullable()->after('id');
            });

            // Générer des ULIDs pour les enregistrements existants
            $records = DB::table('bon_provisoire_sg')->get();
            foreach ($records as $record) {
                $ulid = (string) \Illuminate\Support\Str::ulid();
                DB::table('bon_provisoire_sg')
                    ->where('id', $record->id)
                    ->update(['id_new' => $ulid]);
            }

            // Supprimer l'ancienne colonne id
            Schema::table('bon_provisoire_sg', function (Blueprint $table) {
                $table->dropColumn('id');
            });

            // Renommer id_new en id
            DB::statement('ALTER TABLE bon_provisoire_sg RENAME COLUMN id_new TO id');

            // Remettre la clé primaire
            Schema::table('bon_provisoire_sg', function (Blueprint $table) {
                $table->primary('id');
            });
        } else {
            // Si la colonne n'existe pas, créer directement avec ULID
            Schema::table('bon_provisoire_sg', function (Blueprint $table) {
                $table->char('id', 26)->primary()->first();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('bon_provisoire_sg', 'id')) {
            // Supprimer la clé primaire
            Schema::table('bon_provisoire_sg', function (Blueprint $table) {
                $table->dropPrimary(['id']);
            });

            // Créer une colonne temporaire bigint
            Schema::table('bon_provisoire_sg', function (Blueprint $table) {
                $table->bigInteger('id_old')->nullable()->after('id');
            });

            // Générer des IDs séquentiels
            $records = DB::table('bon_provisoire_sg')->orderBy('created_at')->get();
            $counter = 1;
            foreach ($records as $record) {
                DB::table('bon_provisoire_sg')
                    ->where('id', $record->id)
                    ->update(['id_old' => $counter++]);
            }

            // Supprimer l'ancienne colonne id
            Schema::table('bon_provisoire_sg', function (Blueprint $table) {
                $table->dropColumn('id');
            });

            // Renommer id_old en id et remettre auto-increment
            DB::statement('ALTER TABLE bon_provisoire_sg RENAME COLUMN id_old TO id');
            
            Schema::table('bon_provisoire_sg', function (Blueprint $table) {
                $table->primary('id');
                // Note: PostgreSQL n'a pas de auto-increment natif, on utilise une séquence
                // Il faudrait créer une séquence manuellement si nécessaire
            });
        }
    }
};
