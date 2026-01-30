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
        Schema::create('api_keys', function (Blueprint $table) {
            $table->id();
            $table->string('name')->comment('Nom de la clé API (pour identification)');
            $table->string('key', 64)->unique()->comment('Clé API (hashée)');
            $table->string('key_prefix', 8)->comment('Préfixe de la clé pour affichage');
            $table->boolean('is_active')->default(true);
            $table->timestamp('expires_at')->nullable()->comment('Date d\'expiration (null = jamais)');
            $table->timestamp('last_used_at')->nullable();
            $table->json('allowed_ips')->nullable()->comment('IPs autorisées (null = toutes)');
            $table->integer('rate_limit')->default(100)->comment('Limite de requêtes par minute');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('api_keys');
    }
};
