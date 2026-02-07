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
        // Cette migration est redondante avec 2026_02_04_110001_add_application_id_to_api_keys_table.
        // On la garde pour l'historique, mais on évite l'erreur de colonne dupliquée
        // en vérifiant d'abord si la colonne existe.

        if (!Schema::hasColumn('api_keys', 'application_id')) {
            Schema::table('api_keys', function (Blueprint $table) {
                $table->foreignId('application_id')->nullable()->after('id')
                      ->constrained('applications')
                      ->onDelete('cascade')
                      ->comment('ID de l\'application propriétaire (null = créée par admin)');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Ne supprimer la colonne que si elle existe
        if (Schema::hasColumn('api_keys', 'application_id')) {
            Schema::table('api_keys', function (Blueprint $table) {
                $table->dropForeign(['application_id']);
                $table->dropColumn('application_id');
            });
        }
    }
};
