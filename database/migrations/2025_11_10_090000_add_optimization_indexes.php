<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->addIndexIfMissing('manifeste_sg', 'manifeste_sg_num_manifeste_index', ['num_manifeste']);
        $this->addIndexIfMissing('manifeste_sg', 'manifeste_sg_code_bureau_index', ['code_bureau']);

        $this->addIndexIfMissing('fdi_sg', 'fdi_sg_numero_fdi_index', ['numero_fdi']);
        $this->addIndexIfMissing('fdi_sg', 'fdi_sg_banque_index', ['banque']);

        $this->addIndexIfMissing('declaration_sg', 'declaration_sg_declaration_index', ['declaration']);
        $this->addIndexIfMissing('declaration_sg', 'declaration_sg_num_manifeste_index', ['num_manifeste']);
        $this->addIndexIfMissing('declaration_sg', 'declaration_sg_num_fdi_index', ['num_fdi']);

        $this->addIndexIfMissing('banque_sad', 'banque_sad_num_dom_index', ['num_dom']);
        $this->addIndexIfMissing('banque_sad', 'banque_sad_statut_ac_index', ['statut_ac']);
    }

    public function down(): void
    {
        $this->dropIndexIfExists('manifeste_sg', 'manifeste_sg_num_manifeste_index');
        $this->dropIndexIfExists('manifeste_sg', 'manifeste_sg_code_bureau_index');

        $this->dropIndexIfExists('fdi_sg', 'fdi_sg_numero_fdi_index');
        $this->dropIndexIfExists('fdi_sg', 'fdi_sg_banque_index');

        $this->dropIndexIfExists('declaration_sg', 'declaration_sg_declaration_index');
        $this->dropIndexIfExists('declaration_sg', 'declaration_sg_num_manifeste_index');
        $this->dropIndexIfExists('declaration_sg', 'declaration_sg_num_fdi_index');

        $this->dropIndexIfExists('banque_sad', 'banque_sad_num_dom_index');
        $this->dropIndexIfExists('banque_sad', 'banque_sad_statut_ac_index');
    }

    private function addIndexIfMissing(string $table, string $indexName, array $columns): void
    {
        if (! Schema::hasTable($table)) {
            return;
        }

        Schema::table($table, function (Blueprint $table) use ($indexName, $columns) {
            $table->index($columns, $indexName);
        });
    }

    private function dropIndexIfExists(string $table, string $indexName): void
    {
        if (! Schema::hasTable($table)) {
            return;
        }

        Schema::table($table, function (Blueprint $table) use ($indexName) {
            $table->dropIndex($indexName);
        });
    }
};



