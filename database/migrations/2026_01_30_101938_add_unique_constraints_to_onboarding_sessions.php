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
        if (!Schema::hasTable('onboarding_sessions')) {
            return;
        }

        // Vérifier et ajouter la contrainte unique sur hospital_name
        $hospitalNameIndexes = DB::select("SHOW INDEXES FROM onboarding_sessions WHERE Key_name = 'onboarding_sessions_hospital_name_unique'");
        if (empty($hospitalNameIndexes)) {
            Schema::table('onboarding_sessions', function (Blueprint $table) {
                $table->unique('hospital_name');
            });
        }

        // Vérifier et ajouter la contrainte unique sur database_name
        $databaseNameIndexes = DB::select("SHOW INDEXES FROM onboarding_sessions WHERE Key_name = 'onboarding_sessions_database_name_unique'");
        if (empty($databaseNameIndexes)) {
            Schema::table('onboarding_sessions', function (Blueprint $table) {
                $table->unique('database_name');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (!Schema::hasTable('onboarding_sessions')) {
            return;
        }

        // Retirer la contrainte unique sur database_name
        $databaseNameIndexes = DB::select("SHOW INDEXES FROM onboarding_sessions WHERE Key_name = 'onboarding_sessions_database_name_unique'");
        if (!empty($databaseNameIndexes)) {
            Schema::table('onboarding_sessions', function (Blueprint $table) {
                $table->dropUnique(['database_name']);
            });
        }

        // Retirer la contrainte unique sur hospital_name
        $hospitalNameIndexes = DB::select("SHOW INDEXES FROM onboarding_sessions WHERE Key_name = 'onboarding_sessions_hospital_name_unique'");
        if (!empty($hospitalNameIndexes)) {
            Schema::table('onboarding_sessions', function (Blueprint $table) {
                $table->dropUnique(['hospital_name']);
            });
        }
    }
};
