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
        Schema::create('s360v02_ugmembers', function (Blueprint $table) {
            $table->string('username', 300);
            $table->decimal('groupid', 19, 4);

            $table->primary(['username', 'groupid']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('s360v02_ugmembers');
    }
};
