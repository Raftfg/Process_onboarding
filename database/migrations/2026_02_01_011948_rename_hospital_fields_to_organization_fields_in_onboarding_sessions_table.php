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
        Schema::table('onboarding_sessions', function (Blueprint $table) {
            // Utiliser dropColumn et ajouter les nouveaux pour éviter les erreurs de syntaxe RENAME sur les vieilles versions de MariaDB
            $table->string('organization_name')->after('session_id')->nullable();
            $table->string('organization_address')->after('slug')->nullable();
            $table->string('organization_phone')->after('organization_address')->nullable();
            $table->string('organization_email')->after('organization_phone')->nullable();
        });

        // Transférer les données si elles existent
        DB::statement('UPDATE onboarding_sessions SET organization_name = hospital_name, organization_address = hospital_address, organization_phone = hospital_phone, organization_email = hospital_email');

        Schema::table('onboarding_sessions', function (Blueprint $table) {
            $table->dropColumn(['hospital_name', 'hospital_address', 'hospital_phone', 'hospital_email']);
        });
    }

    public function down(): void
    {
        Schema::table('onboarding_sessions', function (Blueprint $table) {
            $table->string('hospital_name')->after('session_id')->nullable();
            $table->string('hospital_address')->after('slug')->nullable();
            $table->string('hospital_phone')->after('hospital_address')->nullable();
            $table->string('hospital_email')->after('hospital_phone')->nullable();
        });

        DB::statement('UPDATE onboarding_sessions SET hospital_name = organization_name, hospital_address = organization_address, hospital_phone = organization_phone, hospital_email = organization_email');

        Schema::table('onboarding_sessions', function (Blueprint $table) {
            $table->dropColumn(['organization_name', 'organization_address', 'organization_phone', 'organization_email']);
        });
    }
};
