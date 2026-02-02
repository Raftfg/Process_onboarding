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
            $table->string('source_app_name')->nullable()->after('session_id')->index();
            
            // On supprime l'index unique global s'il existe (dépend de l'implémentation précédente, 
            // mais par défaut organization_name n'est pas unique dans la migration create_... fournie plus tôt)
            // Si on veut garantir l'unicité par app:
            $table->unique(['organization_name', 'source_app_name'], 'org_source_unique');
        });
    }

    public function down(): void
    {
        Schema::table('onboarding_sessions', function (Blueprint $table) {
            $table->dropUnique('org_source_unique');
            $table->dropColumn('source_app_name');
        });
    }
};
