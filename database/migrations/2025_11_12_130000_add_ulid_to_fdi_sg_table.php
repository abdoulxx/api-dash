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
            // Skip if table doesn't exist
            if (!Schema::hasTable($tableName)) {
                continue;
            }

            // Add ulid column if it doesn't exist
            if (!Schema::hasColumn($tableName, 'ulid')) {
                try {
                    Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                        // PostgreSQL doesn't support 'after', so we'll add it at the end
                        // and then create an index
                        $table->string('ulid', 26)->nullable()->unique();
                    });
                } catch (\Exception $e) {
                    // If unique constraint fails, try without unique first
                    Schema::table($tableName, function (Blueprint $table) {
                        $table->string('ulid', 26)->nullable();
                    });
                    
                    // Add unique index separately
                    try {
                        DB::statement("CREATE UNIQUE INDEX {$tableName}_ulid_unique ON {$tableName}(ulid) WHERE ulid IS NOT NULL");
                    } catch (\Exception $e2) {
                        // Index might already exist, ignore
                    }
                }
            }

            // Generate ULIDs for existing records that don't have one
            try {
                DB::table($tableName)
                    ->whereNull('ulid')
                    ->orderBy('id')
                    ->chunkById(200, function ($records) use ($tableName) {
                        foreach ($records as $record) {
                            $ulid = (string) Str::ulid();
                            // Ensure uniqueness
                            while (DB::table($tableName)->where('ulid', $ulid)->exists()) {
                                $ulid = (string) Str::ulid();
                            }
                            DB::table($tableName)
                                ->where('id', $record->id)
                                ->update(['ulid' => $ulid]);
                        }
                    });
            } catch (\Exception $e) {
                // Continue even if there's an error
            }
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
                try {
                    // Drop unique index first
                    DB::statement("DROP INDEX IF EXISTS {$tableName}_ulid_unique");
                } catch (\Exception $e) {
                    // Ignore errors
                }
                
                Schema::table($tableName, function (Blueprint $table) {
                    $table->dropColumn('ulid');
                });
            }
        }
    }
};


