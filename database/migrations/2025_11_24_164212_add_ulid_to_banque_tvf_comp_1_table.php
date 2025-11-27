<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (!Schema::hasColumn('banque_tvf_comp_1', 'ulid')) {
            Schema::table('banque_tvf_comp_1', function (Blueprint $table) {
                $table->string('ulid', 26)->nullable()->unique()->after('id');
            });
        }

        // Populate ULID for existing records
        DB::table('banque_tvf_comp_1')
            ->whereNull('ulid')
            ->orderBy('id')
            ->chunkById(200, function ($records) {
                foreach ($records as $record) {
                    DB::table('banque_tvf_comp_1')
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
        if (Schema::hasColumn('banque_tvf_comp_1', 'ulid')) {
            Schema::table('banque_tvf_comp_1', function (Blueprint $table) {
                $table->dropColumn('ulid');
            });
        }
    }
};
