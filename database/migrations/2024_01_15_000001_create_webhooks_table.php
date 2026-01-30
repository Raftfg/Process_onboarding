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
        Schema::create('webhooks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('api_key_id')->nullable()->constrained('api_keys')->onDelete('cascade');
            $table->string('url');
            $table->json('events')->comment('Liste des événements à écouter');
            $table->boolean('is_active')->default(true);
            $table->string('secret', 64)->comment('Secret pour signer les webhooks');
            $table->integer('timeout')->default(30)->comment('Timeout en secondes');
            $table->integer('retry_attempts')->default(3)->comment('Nombre de tentatives en cas d\'échec');
            $table->timestamp('last_triggered_at')->nullable();
            $table->timestamps();

            $table->index('api_key_id');
            $table->index('is_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('webhooks');
    }
};
