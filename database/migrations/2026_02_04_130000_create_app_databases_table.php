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
        Schema::create('app_databases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('application_id')->constrained('applications')->onDelete('cascade')->comment('Application propriétaire de cette base de données');
            $table->string('database_name', 255)->unique()->comment('Nom de la base de données MySQL (ex: app_ejustice_db)');
            $table->string('db_username', 255)->comment('Nom d\'utilisateur MySQL pour cette base');
            $table->string('db_password', 255)->comment('Mot de passe MySQL (hashé)');
            $table->string('db_host', 255)->default('localhost')->comment('Host MySQL');
            $table->integer('db_port')->default(3306)->comment('Port MySQL');
            $table->enum('status', ['active', 'suspended', 'deleted'])->default('active')->comment('Statut de la base de données');
            $table->timestamps();
            
            $table->index('application_id');
            $table->index('database_name');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('app_databases');
    }
};
