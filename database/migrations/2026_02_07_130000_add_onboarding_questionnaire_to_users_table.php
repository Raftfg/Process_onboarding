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
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'onboarding_questionnaire')) {
                $table->json('onboarding_questionnaire')->nullable()->after('bio')->comment('Réponses du questionnaire d\'onboarding');
            }
            if (!Schema::hasColumn('users', 'onboarding_completed_at')) {
                $table->timestamp('onboarding_completed_at')->nullable()->after('onboarding_questionnaire')->comment('Date de complétion du questionnaire');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'onboarding_questionnaire')) {
                $table->dropColumn('onboarding_questionnaire');
            }
            if (Schema::hasColumn('users', 'onboarding_completed_at')) {
                $table->dropColumn('onboarding_completed_at');
            }
        });
    }
};
