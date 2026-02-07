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
            $table->integer('provisioning_attempts')->default(0)->after('ssl_configured')->comment('Nombre de tentatives de provisioning');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('onboarding_registrations', function (Blueprint $table) {
            $table->dropColumn('provisioning_attempts');
        });
    }
};
