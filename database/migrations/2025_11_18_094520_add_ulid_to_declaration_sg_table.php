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
        Schema::table('declaration_sg', function (Blueprint $table) {
            if (!Schema::hasColumn('declaration_sg', 'ulid')) {
                $table->string('ulid', 26)->nullable()->unique()->after('id');
            }
        });

        DB::table('declaration_sg')
            ->whereNull('ulid')
            ->orderBy('id')
            ->chunkById(100, function ($records) {
                foreach ($records as $record) {
                    DB::table('declaration_sg')
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
        Schema::table('declaration_sg', function (Blueprint $table) {
            if (Schema::hasColumn('declaration_sg', 'ulid')) {
                $table->dropUnique('declaration_sg_ulid_unique');
                $table->dropColumn('ulid');
            }
        });
    }
};
