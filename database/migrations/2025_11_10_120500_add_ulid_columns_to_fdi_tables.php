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
        $tables = [
            'fdi_sg',
            'fdi_article',
            'fdi_sg_comp_1',
            'fdi_sg_comp_2',
            'fdi_rech_comp',
        ];

        foreach ($tables as $tableName) {
            if (! Schema::hasColumn($tableName, 'ulid')) {
                Schema::table($tableName, function (Blueprint $table) {
                    $table->string('ulid', 26)->nullable()->unique()->after('id');
                });
            }

            DB::table($tableName)
                ->whereNull('ulid')
                ->orderBy('id')
                ->chunkById(200, function ($records) use ($tableName) {
                    foreach ($records as $record) {
                        DB::table($tableName)
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
        $tables = [
            'fdi_sg',
            'fdi_article',
            'fdi_sg_comp_1',
            'fdi_sg_comp_2',
            'fdi_rech_comp',
        ];

        foreach ($tables as $tableName) {
            if (Schema::hasColumn($tableName, 'ulid')) {
                Schema::table($tableName, function (Blueprint $table) {
                    $table->dropColumn('ulid');
                });
            }
        }
    }
};

