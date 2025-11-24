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
        Schema::create('banque_sad', function (Blueprint $table) {
            $table->id();
            $table->string('id_sad', 20)->nullable();
            $table->string('ref_ddu', 255)->nullable();
            $table->string('num_man', 255)->nullable();
            $table->string('bl_ddu', 255)->nullable();
            $table->string('num_ddu', 255)->nullable();
            $table->timestamp('date_ddu')->nullable();
            $table->string('type_dec', 255)->nullable();
            $table->string('sous_regime', 20)->nullable();
            $table->string('code_add', 255)->nullable();
            $table->string('num_cc', 255)->nullable();
            $table->decimal('dt_ddu', 19, 4)->nullable();
            $table->string('code_declarant', 255)->nullable();
            $table->decimal('valeur_fob_ddu', 19, 4)->nullable();
            $table->decimal('valeur_caf_ddu', 19, 4)->nullable();
            $table->decimal('valeur_fret_ddu', 19, 4)->nullable();
            $table->decimal('val_ass_ddu', 19, 4)->nullable();
            $table->string('num_demande_ac', 255)->nullable();
            $table->timestamp('date_dem_ac')->nullable();
            $table->string('statut_ac', 255)->nullable();
            $table->string('base_sur', 255)->nullable();
            $table->string('num_dom', 255)->nullable();
            $table->timestamp('date_dom')->nullable();
            $table->string('bank_dom', 255)->nullable();
            $table->string('banq_enreg', 255)->nullable();
            $table->string('num_enreg_banq', 255)->nullable();
            $table->timestamp('date_aprob_bank')->nullable();
            $table->timestamp('date_aprob_bank2')->nullable();
            $table->string('pays_exp', 255)->nullable();
            $table->text('adr_exp')->nullable();
            $table->text('autorise_par')->nullable();
            $table->string('cda', 255)->nullable();
            $table->string('code_cda', 255)->nullable();
            $table->text('benef_fonds')->nullable();
            $table->string('type_op', 255)->nullable();
            $table->string('dev_trans', 255)->nullable();
            $table->string('dev_paiem', 255)->nullable();
            $table->decimal('mont_ac_dev', 19, 4)->nullable();
            $table->decimal('mont_ac_xof', 19, 4)->nullable();
            $table->string('mont_fact_xof', 20)->nullable();
            $table->decimal('solde_dev', 19, 4)->nullable();
            $table->string('mont_fact_dev', 20)->nullable();
            $table->string('mont_tot_march_xof', 20)->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('banque_sad');
    }
};
