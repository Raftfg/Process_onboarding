<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Cette migration sera exécutée dans chaque base de données tenant
     */
    public function up(): void
    {
        Schema::create('configuration_dashboard', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->onDelete('cascade');
            $table->enum('theme', ['light', 'dark', 'auto'])->default('light');
            $table->string('langue', 5)->default('fr');
            $table->json('widgets_config')->nullable(); // Configuration des widgets
            $table->json('preferences')->nullable(); // Autres préférences
            $table->timestamps();
            
            // Index pour améliorer les performances
            $table->index('user_id');
            $table->unique('user_id'); // Un seul dashboard par utilisateur
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('configuration_dashboard');
    }
};

