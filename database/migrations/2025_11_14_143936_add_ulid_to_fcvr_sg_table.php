<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('fcvr_sg', function (Blueprint $table) {
            if (!Schema::hasColumn('fcvr_sg', 'ulid')) {
                $table->string('ulid', 26)->nullable()->unique()->after('id');
            }
        });

        // Générer des ULIDs pour les enregistrements existants
        $fcvrRecords = DB::table('fcvr_sg')->whereNull('ulid')->get();
        foreach ($fcvrRecords as $record) {
            DB::table('fcvr_sg')
                ->where('id', $record->id)
                ->update(['ulid' => (string) Str::ulid()]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('fcvr_sg', function (Blueprint $table) {
            if (Schema::hasColumn('fcvr_sg', 'ulid')) {
                $table->dropColumn('ulid');
            }
        });
    }
};
