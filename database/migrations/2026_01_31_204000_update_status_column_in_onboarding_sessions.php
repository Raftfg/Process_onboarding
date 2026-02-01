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
            // Change status from ENUM to VARCHAR to allow 'pending_activation' and other statuses
            $table->string('status')->default('pending')->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('onboarding_sessions', function (Blueprint $table) {
            // Revert back to ENUM
            $table->enum('status', ['pending', 'processing', 'completed', 'failed'])->default('pending')->change();
        });
    }
};
