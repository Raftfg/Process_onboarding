<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Corrige les contraintes de la table onboarding_sessions :
     * - Retire la contrainte unique de session_id (plusieurs onboarding peuvent avoir lieu)
     * - Ajoute une contrainte unique à subdomain (chaque tenant doit avoir un sous-domaine unique)
     */
    public function up(): void
    {
        if (!Schema::hasTable('onboarding_sessions')) {
            return;
        }

        // Vérifier si la contrainte unique sur session_id existe
        $indexes = DB::select("SHOW INDEXES FROM onboarding_sessions WHERE Key_name = 'onboarding_sessions_session_id_unique'");
        
        if (!empty($indexes)) {
            // Retirer la contrainte unique de session_id
            Schema::table('onboarding_sessions', function (Blueprint $table) {
                $table->dropUnique(['session_id']);
            });
        }

        // Vérifier si la contrainte unique sur subdomain existe
        $subdomainIndexes = DB::select("SHOW INDEXES FROM onboarding_sessions WHERE Key_name = 'onboarding_sessions_subdomain_unique'");
        
        if (empty($subdomainIndexes)) {
            // Ajouter une contrainte unique à subdomain (seulement pour les enregistrements non-null)
            // Note: MySQL ne permet pas de contrainte unique partielle directement, 
            // donc on ajoute un index unique sur subdomain
            Schema::table('onboarding_sessions', function (Blueprint $table) {
                // D'abord, s'assurer que tous les subdomain NULL sont uniques en les remplaçant temporairement
                // Puis ajouter la contrainte unique
                $table->unique('subdomain');
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

        // Retirer la contrainte unique de subdomain
        $subdomainIndexes = DB::select("SHOW INDEXES FROM onboarding_sessions WHERE Key_name = 'onboarding_sessions_subdomain_unique'");
        if (!empty($subdomainIndexes)) {
            Schema::table('onboarding_sessions', function (Blueprint $table) {
                $table->dropUnique(['subdomain']);
            });
        }

        // Remettre la contrainte unique sur session_id
        $indexes = DB::select("SHOW INDEXES FROM onboarding_sessions WHERE Key_name = 'onboarding_sessions_session_id_unique'");
        if (empty($indexes)) {
            Schema::table('onboarding_sessions', function (Blueprint $table) {
                $table->unique('session_id');
            });
        }
    }
};
