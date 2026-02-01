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
        // Ajouter les contraintes d'unicité pour onboarding_sessions
        try {
            Schema::table('onboarding_sessions', function (Blueprint $table) {
                // Email admin unique (un email ne peut créer qu'un seul tenant)
                $table->unique('admin_email', 'onboarding_sessions_admin_email_unique');
            });
        } catch (\Exception $e) {
            // La contrainte existe peut-être déjà, ignorer l'erreur
        }
        
        try {
            Schema::table('onboarding_sessions', function (Blueprint $table) {
                // Sous-domaine unique
                $table->unique('subdomain', 'onboarding_sessions_subdomain_unique');
            });
        } catch (\Exception $e) {
            // La contrainte existe peut-être déjà, ignorer l'erreur
        }
        
        try {
            Schema::table('onboarding_sessions', function (Blueprint $table) {
                // Nom de base de données unique
                $table->unique('database_name', 'onboarding_sessions_database_name_unique');
            });
        } catch (\Exception $e) {
            // La contrainte existe peut-être déjà, ignorer l'erreur
        }

        // Ajouter les contraintes d'unicité pour onboarding_activations
        try {
            Schema::table('onboarding_activations', function (Blueprint $table) {
                // Sous-domaine unique (un sous-domaine ne peut avoir qu'un seul token d'activation)
                $table->unique('subdomain', 'onboarding_activations_subdomain_unique');
            });
        } catch (\Exception $e) {
            // La contrainte existe peut-être déjà, ignorer l'erreur
        }
        
        try {
            Schema::table('onboarding_activations', function (Blueprint $table) {
                // Nom de base de données unique
                $table->unique('database_name', 'onboarding_activations_database_name_unique');
            });
        } catch (\Exception $e) {
            // La contrainte existe peut-être déjà, ignorer l'erreur
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('onboarding_sessions', function (Blueprint $table) {
            $table->dropUnique('onboarding_sessions_admin_email_unique');
            $table->dropUnique('onboarding_sessions_subdomain_unique');
            $table->dropUnique('onboarding_sessions_database_name_unique');
        });

        Schema::table('onboarding_activations', function (Blueprint $table) {
            $table->dropUnique('onboarding_activations_subdomain_unique');
            $table->dropUnique('onboarding_activations_database_name_unique');
        });
    }
};
