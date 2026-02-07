<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Ajouter la colonne completed_at
        Schema::table('onboarding_registrations', function (Blueprint $table) {
            $table->timestamp('completed_at')->nullable()->after('updated_at')->comment('Date de complétion par l\'application cliente');
        });

        // Modifier l'enum pour inclure 'completed'
        DB::statement("ALTER TABLE onboarding_registrations MODIFY COLUMN status ENUM('pending', 'activated', 'cancelled', 'completed') DEFAULT 'pending'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('onboarding_registrations', function (Blueprint $table) {
            $table->dropColumn('completed_at');
        });

        // Revenir à l'enum original
        DB::statement("ALTER TABLE onboarding_registrations MODIFY COLUMN status ENUM('pending', 'activated', 'cancelled') DEFAULT 'pending'");
    }
};
