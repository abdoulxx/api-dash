<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (!Schema::hasColumn('banque_sad', 'ulid')) {
            Schema::table('banque_sad', function (Blueprint $table) {
                $table->string('ulid', 26)->nullable()->unique()->after('id');
            });
        }

        // Populate ULID for existing records
        DB::table('banque_sad')
            ->whereNull('ulid')
            ->orderBy('id')
            ->chunkById(200, function ($records) {
                foreach ($records as $record) {
                    DB::table('banque_sad')
                        ->where('id', $record->id)
                        ->update(['ulid' => (string) Str::ulid()]);
                }
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('banque_sad', 'ulid')) {
            Schema::table('banque_sad', function (Blueprint $table) {
                $table->dropColumn('ulid');
            });
        }
    }
};
