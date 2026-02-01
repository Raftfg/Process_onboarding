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

        // Retirer la contrainte unique sur admin_email
        $adminEmailIndexes = DB::connection('mysql')->select("SHOW INDEXES FROM onboarding_sessions WHERE Key_name = 'onboarding_sessions_admin_email_unique'");
        if (!empty($adminEmailIndexes)) {
            Schema::connection('mysql')->table('onboarding_sessions', function (Blueprint $table) {
                $table->dropUnique('onboarding_sessions_admin_email_unique');
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

        try {
            Schema::connection('mysql')->table('onboarding_sessions', function (Blueprint $table) {
                $table->unique('admin_email', 'onboarding_sessions_admin_email_unique');
            });
        } catch (\Exception $e) {
            // Ignorer si doublons déjà présents ou déjà existant
        }
    }
};
