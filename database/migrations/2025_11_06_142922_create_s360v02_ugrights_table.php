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
        Schema::create('s360v02_ugrights', function (Blueprint $table) {
            $table->string('tablename', 300);
            $table->decimal('groupid', 19, 4)->primary();
            $table->string('accessmask', 10)->nullable();
            $table->text('page')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('s360v02_ugrights');
    }
};
