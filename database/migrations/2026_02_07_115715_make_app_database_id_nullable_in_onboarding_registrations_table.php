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
        Schema::table('onboarding_registrations', function (Blueprint $table) {
            // Supprimer la contrainte de clé étrangère existante
            $table->dropForeign(['app_database_id']);
            
            // Modifier la colonne pour la rendre nullable
            $table->unsignedBigInteger('app_database_id')->nullable()->change();
            
            // Recréer la contrainte de clé étrangère avec onDelete('set null') au lieu de 'cascade'
            $table->foreign('app_database_id')
                  ->references('id')
                  ->on('app_databases')
                  ->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('onboarding_registrations', function (Blueprint $table) {
            // Supprimer la contrainte de clé étrangère
            $table->dropForeign(['app_database_id']);
            
            // Remettre la colonne comme non nullable
            $table->unsignedBigInteger('app_database_id')->nullable(false)->change();
            
            // Recréer la contrainte de clé étrangère originale
            $table->foreign('app_database_id')
                  ->references('id')
                  ->on('app_databases')
                  ->onDelete('cascade');
        });
    }
};
