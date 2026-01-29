<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('onboarding_sessions', function (Blueprint $table) {
            $table->id();
            $table->string('session_id')->unique();
            $table->string('hospital_name');
            $table->text('hospital_address')->nullable();
            $table->string('hospital_phone')->nullable();
            $table->string('hospital_email')->nullable();
            $table->string('admin_first_name');
            $table->string('admin_last_name');
            $table->string('admin_email');
            $table->string('subdomain')->nullable();
            $table->string('database_name')->nullable();
            $table->enum('status', ['pending', 'processing', 'completed', 'failed'])->default('pending');
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('onboarding_sessions');
    }
};
