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
        Schema::create('applications', function (Blueprint $table) {
            $table->id();
            $table->string('app_id', 64)->unique()->comment('Identifiant unique de l\'application (ex: app_abc123)');
            $table->string('app_name', 50)->unique()->comment('Nom technique unique (ex: ejustice)');
            $table->string('display_name')->comment('Nom d\'affichage de l\'application');
            $table->string('contact_email')->comment('Email de contact du développeur');
            $table->string('website')->nullable()->comment('Site web de l\'application');
            $table->string('master_key', 255)->comment('Master key hashée (pour gérer les clés API)');
            $table->boolean('is_active')->default(true)->comment('Application active ou suspendue');
            $table->timestamp('last_used_at')->nullable()->comment('Dernière utilisation');
            $table->timestamps();
            
            $table->index('app_id');
            $table->index('app_name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('applications');
    }
};
