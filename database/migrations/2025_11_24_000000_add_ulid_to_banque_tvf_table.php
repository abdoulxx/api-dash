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
        if (!Schema::hasTable('banque_tvf')) {
            return;
        }

        if (!Schema::hasColumn('banque_tvf', 'ulid')) {
            Schema::table('banque_tvf', function (Blueprint $table) {
                $table->string('ulid', 26)->nullable()->unique()->after('id');
            });
        }

        DB::table('banque_tvf')
            ->whereNull('ulid')
            ->orderBy('id')
            ->chunkById(200, function ($records) {
                foreach ($records as $record) {
                    DB::table('banque_tvf')
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
        if (!Schema::hasTable('banque_tvf')) {
            return;
        }

        if (Schema::hasColumn('banque_tvf', 'ulid')) {
            Schema::table('banque_tvf', function (Blueprint $table) {
                $table->dropColumn('ulid');
            });
        }
    }
};

