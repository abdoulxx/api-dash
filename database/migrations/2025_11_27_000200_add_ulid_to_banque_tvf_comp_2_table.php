<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('banque_tvf_comp_2', function (Blueprint $table) {
            if (!Schema::hasColumn('banque_tvf_comp_2', 'ulid')) {
                $table->char('ulid', 26)->nullable()->after('id');
            }
        });

        DB::table('banque_tvf_comp_2')
            ->select('id')
            ->orderBy('id')
            ->chunkById(500, function ($rows) {
                foreach ($rows as $row) {
                    DB::table('banque_tvf_comp_2')
                        ->where('id', $row->id)
                        ->update(['ulid' => (string) Str::ulid()]);
                }
            });

        DB::statement('ALTER TABLE banque_tvf_comp_2 ALTER COLUMN ulid SET NOT NULL');
        DB::statement('CREATE UNIQUE INDEX IF NOT EXISTS banque_tvf_comp_2_ulid_unique ON banque_tvf_comp_2 (ulid)');
    }

    public function down(): void
    {
        Schema::table('banque_tvf_comp_2', function (Blueprint $table) {
            if (Schema::hasColumn('banque_tvf_comp_2', 'ulid')) {
                $table->dropIndex('banque_tvf_comp_2_ulid_unique');
                $table->dropColumn('ulid');
            }
        });
    }
};

