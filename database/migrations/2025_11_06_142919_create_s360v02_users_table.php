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
        Schema::create('s360v02_users', function (Blueprint $table) {
            $table->decimal('id', 19, 4)->primary();
            $table->string('username', 255)->nullable();
            $table->string('password', 255)->nullable();
            $table->string('email', 255)->nullable();
            $table->string('fullname', 255)->nullable();
            $table->string('groupid', 255)->nullable();
            $table->decimal('active', 19, 4)->nullable();
            $table->string('ext_security_id', 100)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('s360v02_users');
    }
};
