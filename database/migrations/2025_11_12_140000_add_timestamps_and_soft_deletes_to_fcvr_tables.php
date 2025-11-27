<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $tables = [
            'fcvr_sg',
            'fcvr_sg_comp_1',
            'fcvr_sg_comp_2',
            'fcvr_article',
        ];

        foreach ($tables as $tableName) {
            if (!Schema::hasTable($tableName)) {
                continue;
            }

            // Add timestamps if they don't exist
            if (!Schema::hasColumn($tableName, 'created_at')) {
                Schema::table($tableName, function (Blueprint $table) {
                    $table->timestamps();
                });
            }

            // Add soft deletes if it doesn't exist
            if (!Schema::hasColumn($tableName, 'deleted_at')) {
                Schema::table($tableName, function (Blueprint $table) {
                    $table->softDeletes();
                });
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $tables = [
            'fcvr_sg',
            'fcvr_sg_comp_1',
            'fcvr_sg_comp_2',
            'fcvr_article',
        ];

        foreach ($tables as $tableName) {
            if (Schema::hasColumn($tableName, 'deleted_at')) {
                Schema::table($tableName, function (Blueprint $table) {
                    $table->dropSoftDeletes();
                });
            }

            if (Schema::hasColumn($tableName, 'created_at')) {
                Schema::table($tableName, function (Blueprint $table) {
                    $table->dropTimestamps();
                });
            }
        }
    }
};



