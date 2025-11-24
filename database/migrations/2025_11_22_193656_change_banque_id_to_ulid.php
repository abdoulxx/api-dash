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
        // Vérifier si la colonne ID existe
        if (Schema::hasColumn('BANQUE', 'ID')) {
            // Supprimer l'ancienne clé primaire
            Schema::table('BANQUE', function (Blueprint $table) {
                $table->dropPrimary(['ID']);
            });

            // Créer une colonne temporaire pour stocker les ULIDs
            Schema::table('BANQUE', function (Blueprint $table) {
                $table->char('ID_NEW', 26)->nullable()->after('ID');
            });

            // Générer des ULIDs pour les enregistrements existants
            $records = DB::table('BANQUE')->get();
            foreach ($records as $record) {
                $ulid = (string) \Illuminate\Support\Str::ulid();
                DB::table('BANQUE')
                    ->where('ID', $record->ID)
                    ->update(['ID_NEW' => $ulid]);
            }

            // Supprimer l'ancienne colonne ID
            Schema::table('BANQUE', function (Blueprint $table) {
                $table->dropColumn('ID');
            });

            // Renommer ID_NEW en ID
            DB::statement('ALTER TABLE "BANQUE" RENAME COLUMN "ID_NEW" TO "ID"');

            // Remettre la clé primaire
            Schema::table('BANQUE', function (Blueprint $table) {
                $table->primary('ID');
            });
        } else {
            // Si la colonne n'existe pas, créer directement avec ULID
            Schema::table('BANQUE', function (Blueprint $table) {
                $table->char('ID', 26)->primary()->first();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('BANQUE', 'ID')) {
            // Supprimer la clé primaire
            Schema::table('BANQUE', function (Blueprint $table) {
                $table->dropPrimary(['ID']);
            });

            // Créer une colonne temporaire VARCHAR(20)
            Schema::table('BANQUE', function (Blueprint $table) {
                $table->string('ID_OLD', 20)->nullable()->after('ID');
            });

            // Générer des IDs basés sur NUM_DVT ou un compteur
            $records = DB::table('BANQUE')->orderBy('DATE_DVT')->get();
            $counter = 1;
            foreach ($records as $record) {
                $oldId = 'BANQUE-' . str_pad($counter++, 10, '0', STR_PAD_LEFT);
                DB::table('BANQUE')
                    ->where('ID', $record->ID)
                    ->update(['ID_OLD' => $oldId]);
            }

            // Supprimer l'ancienne colonne ID
            Schema::table('BANQUE', function (Blueprint $table) {
                $table->dropColumn('ID');
            });

            // Renommer ID_OLD en ID
            DB::statement('ALTER TABLE "BANQUE" RENAME COLUMN "ID_OLD" TO "ID"');
            
            Schema::table('BANQUE', function (Blueprint $table) {
                $table->primary('ID');
            });
        }
    }
};
