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
        Schema::create('onboarding_registrations', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique()->comment('UUID unique pour cet enregistrement');
            $table->foreignId('application_id')->constrained('applications')->onDelete('cascade')->comment('Application qui a initié cet onboarding');
            $table->foreignId('app_database_id')->constrained('app_databases')->onDelete('cascade')->comment('Base de données de l\'application');
            $table->string('email', 255)->comment('Email de l\'administrateur du tenant');
            $table->string('organization_name', 255)->nullable()->comment('Nom de l\'organisation (optionnel)');
            $table->string('subdomain', 255)->unique()->comment('Sous-domaine généré (ex: client-abc123)');
            $table->enum('status', ['pending', 'activated', 'cancelled'])->default('pending')->comment('Statut de l\'onboarding');
            $table->string('api_key', 64)->nullable()->comment('Clé API générée (si nécessaire)');
            $table->string('api_secret', 255)->nullable()->comment('Secret API hashé (si généré)');
            $table->json('metadata')->nullable()->comment('Métadonnées flexibles');
            $table->boolean('dns_configured')->default(false)->comment('DNS configuré pour le sous-domaine');
            $table->boolean('ssl_configured')->default(false)->comment('SSL configuré pour le sous-domaine');
            $table->timestamps();
            
            $table->index('uuid');
            $table->index('application_id');
            $table->index('app_database_id');
            $table->index('subdomain');
            $table->index('email');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('onboarding_registrations');
    }
};
