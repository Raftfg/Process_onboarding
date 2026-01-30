<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Corrige la colonne id pour qu'elle ait l'auto-increment activé
     */
    public function up(): void
    {
        // Vérifier si la table existe
        if (!Schema::hasTable('onboarding_sessions')) {
            return;
        }

        // Vérifier si la colonne id existe et si elle a l'auto-increment
        $columns = DB::select("SHOW COLUMNS FROM onboarding_sessions WHERE Field = 'id'");
        
        if (empty($columns)) {
            // Si la colonne id n'existe pas, la créer avec auto-increment
            DB::statement("ALTER TABLE onboarding_sessions ADD COLUMN id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY FIRST");
        } else {
            $column = $columns[0];
            $extra = $column->Extra ?? '';
            
            // Si l'auto-increment n'est pas activé, le corriger
            if (strpos($extra, 'auto_increment') === false) {
                // Récupérer le type de la colonne
                $type = $column->Type ?? 'bigint(20) unsigned';
                
                // Modifier la colonne pour activer l'auto-increment
                DB::statement("ALTER TABLE onboarding_sessions MODIFY COLUMN id {$type} NOT NULL AUTO_INCREMENT");
                
                // S'assurer que la colonne est bien la clé primaire
                $primaryKeys = DB::select("SHOW KEYS FROM onboarding_sessions WHERE Key_name = 'PRIMARY' AND Column_name = 'id'");
                if (empty($primaryKeys)) {
                    DB::statement("ALTER TABLE onboarding_sessions ADD PRIMARY KEY (id)");
                }
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Cette migration ne peut pas être inversée de manière sûre
        // car on ne peut pas désactiver l'auto-increment sans risquer de perdre des données
    }
};
