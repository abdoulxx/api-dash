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
        Schema::create('fdi_rech_comp', function (Blueprint $table) {
            $table->id();
            $table->decimal('id_fdi_comp', 19, 4)->nullable();
            $table->string('fdi_primaire', 50)->nullable();
            $table->timestamp('date_fdi_primaire')->nullable();
            $table->string('fdi_secondaire', 50)->nullable();
            $table->timestamp('date_fdi_secondaire')->nullable();
            $table->string('observation', 510)->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fdi_rech_comp');
    }
};
