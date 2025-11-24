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
        Schema::table('users', function (Blueprint $table) {
            // Split name into firstname and lastname
            $table->string('firstname')->nullable()->after('name');
            $table->string('lastname')->nullable()->after('firstname');
            
            // Additional administration fields
            $table->string('fonction')->nullable()->after('lastname'); // Job function/position
            $table->string('departement')->nullable()->after('fonction'); // Department
            $table->foreignUlid('manager_id')->nullable()->after('departement')->constrained('users')->nullOnDelete(); // Manager reference
            $table->string('statut')->default('Actif')->after('manager_id'); // Status: Actif, Inactif, etc.
            
            // Track last login
            $table->timestamp('last_login_at')->nullable()->after('statut');
            
            // Add index for common queries
            $table->index('statut');
            $table->index('last_login_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['statut']);
            $table->dropIndex(['last_login_at']);
            $table->dropForeign(['manager_id']);
            $table->dropColumn([
                'firstname',
                'lastname',
                'fonction',
                'departement',
                'manager_id',
                'statut',
                'last_login_at',
            ]);
        });
    }
};
