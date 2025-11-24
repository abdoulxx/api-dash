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
        Schema::table('manifeste_sg', function (Blueprint $table) {
            if (!Schema::hasColumn('manifeste_sg', 'ulid')) {
                $table->string('ulid', 26)->nullable()->unique()->after('instance_id');
            }
        });

        // Populate existing records with ULIDs
        DB::table('manifeste_sg')
            ->whereNull('ulid')
            ->orderBy('instance_id')
            ->chunkById(100, function ($records) {
                foreach ($records as $record) {
                    DB::table('manifeste_sg')
                        ->where('instance_id', $record->instance_id)
                        ->update(['ulid' => (string) Str::ulid()]);
                }
            }, 'instance_id');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('manifeste_sg', function (Blueprint $table) {
            if (Schema::hasColumn('manifeste_sg', 'ulid')) {
                $table->dropUnique('manifeste_sg_ulid_unique');
                $table->dropColumn('ulid');
            }
        });
    }
};
