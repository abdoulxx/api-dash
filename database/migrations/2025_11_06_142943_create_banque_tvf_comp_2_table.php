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
        Schema::create('banque_tvf_comp_2', function (Blueprint $table) {
            $table->id();
            $table->string('old_id', 20)->nullable();
            $table->string('annee_fdi', 20)->nullable();
            $table->decimal('num_fdi', 19, 4)->nullable();
            $table->timestamp('date_fdi')->nullable();
            $table->string('status_fdi', 255)->nullable();
            $table->timestamp('date_autorisation_fdi')->nullable();
            $table->timestamp('date_expiration_fdi')->nullable();
            $table->string('statut_ac', 255)->nullable();
            $table->string('base_sur_ac', 255)->nullable();
            $table->string('num_demande_ac', 255)->nullable();
            $table->timestamp('date_dem_ac')->nullable();
            $table->string('ref_ddu', 255)->nullable();
            $table->string('bank_dom', 255)->nullable();
            $table->timestamp('date_dom')->nullable();
            $table->string('num_dom', 255)->nullable();
            $table->string('banq_enreg', 255)->nullable();
            $table->string('num_enreg_banq', 255)->nullable();
            $table->timestamp('date_aprob_bank')->nullable();
            $table->timestamp('date_aprob_bank2')->nullable();
            $table->string('pays_exp', 255)->nullable();
            $table->string('autorise_par', 255)->nullable();
            $table->text('adr_exp')->nullable();
            $table->string('code_cda_ac', 255)->nullable();
            $table->string('cda_ac', 255)->nullable();
            $table->text('benef_fonds')->nullable();
            $table->text('adr_benef')->nullable();
            $table->string('type_op', 255)->nullable();
            $table->string('dev_trans', 255)->nullable();
            $table->string('dev_paiem', 255)->nullable();
            $table->decimal('mont_fact_dev', 19, 4)->nullable();
            $table->decimal('mont_fact_xof', 19, 4)->nullable();
            $table->decimal('mont_ac_dev', 19, 4)->nullable();
            $table->decimal('mont_ac_xof', 19, 4)->nullable();
            $table->string('mont_tot_march_xof', 20)->nullable();
            $table->decimal('solde_dev', 19, 4)->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('banque_tvf_comp_2');
    }
};
