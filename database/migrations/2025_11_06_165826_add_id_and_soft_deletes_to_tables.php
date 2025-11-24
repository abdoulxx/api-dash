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
        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            return;
        }

        // Tables sans ID auto-incrémenté - Ajouter id et soft deletes
        $tablesWithoutAutoIncrementId = [
            'fcvr_sg',
            'fcvr_sg_comp_1',
            'fcvr_sg_comp_2',
            'fcvr_article',
            'declaration_sg',
            'declaration_tc',
            'declaration_article',
            'banque_sad',
        ];

        foreach ($tablesWithoutAutoIncrementId as $tableName) {
            if (! Schema::hasColumn($tableName, 'id')) {
                Schema::table($tableName, function (Blueprint $table) {
                    $table->bigIncrements('id')->first();
                });
            }

            if (! Schema::hasColumn($tableName, 'deleted_at')) {
                Schema::table($tableName, function (Blueprint $table) {
                    $table->softDeletes();
                });
            }
        }

        // Tables banque_tvf qui ont déjà un champ 'id' (string nullable) - Renommer en old_id
        $banqueTvfTables = ['banque_tvf', 'banque_tvf_comp_1', 'banque_tvf_comp_2'];
        foreach ($banqueTvfTables as $tableName) {
            if (Schema::hasColumn($tableName, 'id') && ! Schema::hasColumn($tableName, 'old_id')) {
                Schema::table($tableName, function (Blueprint $table) {
                    $table->renameColumn('id', 'old_id');
                });
            }

            if (! Schema::hasColumn($tableName, 'id')) {
                Schema::table($tableName, function (Blueprint $table) {
                    $table->bigIncrements('id')->first();
                });
            }

            if (! Schema::hasColumn($tableName, 'deleted_at')) {
                Schema::table($tableName, function (Blueprint $table) {
                    $table->softDeletes();
                });
            }
        }

        // Tables avec clé primaire non auto-incrémentée - Ajouter id auto-incrémenté et soft deletes
        // On garde l'ancienne clé primaire comme index unique
        $tablesWithNonAutoIncrementPrimary = [
            'manifeste_sg' => 'instance_id',
            'manifeste_tc' => 'instance_id',
            'manifeste_tt' => 'instance_id',
            'fdi_sg' => 'instance_id',
            'fdi_sg_comp_1' => 'instance_id',
            'fdi_sg_comp_2' => 'instance_id',
            'fdi_article' => 'instance_id',
            'bon_provisoire_sg' => 'instance_id',
            'bon_provisoire_article' => 'instance_id',
        ];

        foreach ($tablesWithNonAutoIncrementPrimary as $tableName => $oldPrimaryKey) {
            if (! Schema::hasColumn($tableName, 'id')) {
                Schema::table($tableName, function (Blueprint $table) use ($oldPrimaryKey) {
                    $table->dropPrimary([$oldPrimaryKey]);
                    $table->bigIncrements('id')->first();
                    $table->unique($oldPrimaryKey);
                });
            }

            if (! Schema::hasColumn($tableName, 'deleted_at')) {
                Schema::table($tableName, function (Blueprint $table) {
                    $table->softDeletes();
                });
            }
        }

        // Tables avec clé primaire decimal - Ajouter id auto-incrémenté et soft deletes
        // Pour s360v02_users, renommer l'ancien 'id' en 'old_id' car il y a conflit
        if (! Schema::hasColumn('s360v02_users', 'old_id') && Schema::hasColumn('s360v02_users', 'id')) {
            Schema::table('s360v02_users', function (Blueprint $table) {
                $table->dropPrimary(['id']);
                $table->renameColumn('id', 'old_id');
            });
        }

        if (! Schema::hasColumn('s360v02_users', 'id')) {
            Schema::table('s360v02_users', function (Blueprint $table) {
                $table->bigIncrements('id')->first();
                $table->unique('old_id');
            });
        }

        if (! Schema::hasColumn('s360v02_users', 'deleted_at')) {
            Schema::table('s360v02_users', function (Blueprint $table) {
                $table->softDeletes();
            });
        }

        $tablesWithDecimalPrimary = [
            'fdi_rech_comp' => 'id_fdi_comp',
            's360v02_uggroups' => 'groupid',
            's360v02_ugrights' => 'groupid',
        ];

        foreach ($tablesWithDecimalPrimary as $tableName => $oldPrimaryKey) {
            if (! Schema::hasColumn($tableName, 'id')) {
                Schema::table($tableName, function (Blueprint $table) use ($oldPrimaryKey) {
                    $table->dropPrimary([$oldPrimaryKey]);
                    $table->bigIncrements('id')->first();
                    $table->unique($oldPrimaryKey);
                });
            }

            if (! Schema::hasColumn($tableName, 'deleted_at')) {
                Schema::table($tableName, function (Blueprint $table) {
                    $table->softDeletes();
                });
            }
        }

        // Table avec clé primaire composite - Ajouter id auto-incrémenté et soft deletes
        if (! Schema::hasColumn('s360v02_ugmembers', 'id')) {
            Schema::table('s360v02_ugmembers', function (Blueprint $table) {
                $table->dropPrimary(['username', 'groupid']);
                $table->bigIncrements('id')->first();
                $table->unique(['username', 'groupid']);
            });
        }

        if (! Schema::hasColumn('s360v02_ugmembers', 'deleted_at')) {
            Schema::table('s360v02_ugmembers', function (Blueprint $table) {
                $table->softDeletes();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            return;
        }

        // Tables sans ID auto-incrémenté
        $tablesWithoutAutoIncrementId = [
            'fcvr_sg',
            'fcvr_sg_comp_1',
            'fcvr_sg_comp_2',
            'fcvr_article',
            'declaration_sg',
            'declaration_tc',
            'declaration_article',
            'banque_sad',
        ];

        foreach ($tablesWithoutAutoIncrementId as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->dropSoftDeletes();
                $table->dropColumn('id');
            });
        }

        // Tables banque_tvf - Restaurer l'ancien id
        $banqueTvfTables = ['banque_tvf', 'banque_tvf_comp_1', 'banque_tvf_comp_2'];
        foreach ($banqueTvfTables as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->dropSoftDeletes();
                $table->dropColumn('id');
                $table->renameColumn('old_id', 'id');
            });
        }

        // Tables avec clé primaire non auto-incrémentée
        $tablesWithNonAutoIncrementPrimary = [
            'manifeste_sg' => 'instance_id',
            'manifeste_tc' => 'instance_id',
            'manifeste_tt' => 'instance_id',
            'fdi_sg' => 'instance_id',
            'fdi_sg_comp_1' => 'instance_id',
            'fdi_sg_comp_2' => 'instance_id',
            'fdi_article' => 'instance_id',
            'bon_provisoire_sg' => 'instance_id',
            'bon_provisoire_article' => 'instance_id',
        ];

        foreach ($tablesWithNonAutoIncrementPrimary as $tableName => $oldPrimaryKey) {
            Schema::table($tableName, function (Blueprint $table) use ($oldPrimaryKey) {
                $table->dropColumn('id');
                $table->dropUnique([$oldPrimaryKey]);
                $table->dropSoftDeletes();
                $table->primary($oldPrimaryKey);
            });
        }

        // Tables avec clé primaire decimal
        // Pour s360v02_users, restaurer l'ancien id
        Schema::table('s360v02_users', function (Blueprint $table) {
            $table->dropColumn('id');
            $table->dropUnique(['old_id']);
            $table->dropSoftDeletes();
            $table->renameColumn('old_id', 'id');
            $table->primary('id');
        });

        $tablesWithDecimalPrimary = [
            'fdi_rech_comp' => 'id_fdi_comp',
            's360v02_uggroups' => 'groupid',
            's360v02_ugrights' => 'groupid',
        ];

        foreach ($tablesWithDecimalPrimary as $tableName => $oldPrimaryKey) {
            Schema::table($tableName, function (Blueprint $table) use ($oldPrimaryKey) {
                $table->dropColumn('id');
                $table->dropUnique([$oldPrimaryKey]);
                $table->dropSoftDeletes();
                $table->primary($oldPrimaryKey);
            });
        }

        // Table avec clé primaire composite
        Schema::table('s360v02_ugmembers', function (Blueprint $table) {
            $table->dropColumn('id');
            $table->dropUnique(['username', 'groupid']);
            $table->dropSoftDeletes();
            $table->primary(['username', 'groupid']);
        });
    }
};
