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
        Schema::create('tenants', function (Blueprint $table) {
            $table->id();
            $table->string('subdomain')->unique();
            $table->string('database_name')->unique();
            $table->string('name'); // Nom de l'organisation/hôpital
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->text('address')->nullable();
            $table->enum('status', ['active', 'suspended', 'inactive'])->default('active');
            $table->string('plan')->nullable(); // Type d'abonnement si applicable
            $table->timestamps();
            $table->softDeletes();
            
            // Index pour améliorer les performances
            $table->index('subdomain');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tenants');
    }
};
