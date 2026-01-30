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

        // Vérifier si la colonne slug existe déjà
        if (!Schema::hasColumn('onboarding_sessions', 'slug')) {
            Schema::table('onboarding_sessions', function (Blueprint $table) {
                $table->string('slug')->nullable()->after('hospital_name');
            });
        }

        // Vérifier et ajouter la contrainte unique sur slug
        $slugIndexes = DB::select("SHOW INDEXES FROM onboarding_sessions WHERE Key_name = 'onboarding_sessions_slug_unique'");
        if (empty($slugIndexes)) {
            Schema::table('onboarding_sessions', function (Blueprint $table) {
                $table->unique('slug');
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

        // Retirer la contrainte unique sur slug
        $slugIndexes = DB::select("SHOW INDEXES FROM onboarding_sessions WHERE Key_name = 'onboarding_sessions_slug_unique'");
        if (!empty($slugIndexes)) {
            Schema::table('onboarding_sessions', function (Blueprint $table) {
                $table->dropUnique(['slug']);
            });
        }

        // Retirer la colonne slug
        if (Schema::hasColumn('onboarding_sessions', 'slug')) {
            Schema::table('onboarding_sessions', function (Blueprint $table) {
                $table->dropColumn('slug');
            });
        }
    }
};
