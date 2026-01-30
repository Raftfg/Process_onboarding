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
        Schema::create('information_personnes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->string('prenom');
            $table->string('nom');
            $table->date('date_naissance')->nullable();
            $table->enum('sexe', ['M', 'F', 'Autre'])->nullable();
            $table->string('telephone')->nullable();
            $table->text('adresse')->nullable();
            $table->string('ville')->nullable();
            $table->string('code_postal')->nullable();
            $table->string('pays')->nullable()->default('France');
            $table->string('photo')->nullable(); // Chemin vers l'image
            $table->text('notes')->nullable();
            $table->timestamps();
            
            // Index pour améliorer les performances
            $table->index('user_id');
            $table->index(['nom', 'prenom']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('information_personnes');
    }
};

