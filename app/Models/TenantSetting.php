<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class TenantSetting extends Model
{
    use HasFactory;

    protected $fillable = [
        'key',
        'value',
        'group',
    ];

    protected $casts = [
        'value' => 'array',
    ];

    /**
     * Récupérer un setting par sa clé
     */
    public static function get(string $key, $default = null)
    {
        // Vérifier que la table existe avant d'essayer de l'utiliser
        try {
            $connection = \Illuminate\Support\Facades\DB::connection();
            $databaseName = $connection->getDatabaseName();
            
            // Si on est sur la base principale, retourner la valeur par défaut
            $mainDatabase = config('database.connections.mysql.database');
            if ($databaseName === $mainDatabase) {
                return $default;
            }
            
            // Vérifier que la table existe
            if (!\Illuminate\Support\Facades\Schema::connection($connection->getName())->hasTable('tenant_settings')) {
                return $default;
            }
        } catch (\Exception $e) {
            return $default;
        }
        
        $cacheKey = "tenant_setting_{$key}_{$databaseName}";
        $connectionName = $connection->getName();
        
        // Utiliser un cache plus court (5 minutes) pour permettre des mises à jour plus rapides
        return Cache::remember($cacheKey, 300, function () use ($key, $default, $connectionName) {
            try {
                $model = new static();
                $model->setConnection($connectionName);
                $setting = $model->where('key', $key)->first();
                return $setting ? $setting->value : $default;
            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::error("Erreur lors de la récupération du setting {$key}: " . $e->getMessage());
                return $default;
            }
        });
    }

    /**
     * Définir un setting
     */
    public static function set(string $key, $value, string $group = 'general'): void
    {
        // Vérifier que la table existe avant d'essayer d'insérer
        try {
            $connection = \Illuminate\Support\Facades\DB::connection();
            $databaseName = $connection->getDatabaseName();
            $mainDatabase = config('database.connections.mysql.database');
            
            // Si on est sur la base principale, ne pas enregistrer
            if ($databaseName === $mainDatabase) {
                \Illuminate\Support\Facades\Log::warning("Tentative d'enregistrement dans la base principale pour la clé: {$key}");
                return;
            }
            
            // Vérifier que la table existe
            if (!\Illuminate\Support\Facades\Schema::connection($connection->getName())->hasTable('tenant_settings')) {
                \Illuminate\Support\Facades\Log::error("La table tenant_settings n'existe pas dans la base: {$databaseName}");
                throw new \Exception("La table tenant_settings n'existe pas dans la base de données tenant");
            }
            
            // Utiliser la connexion actuelle pour le modèle
            $model = new static();
            $model->setConnection($connection->getName());
            
            // Stocker directement la valeur - Laravel gérera automatiquement le JSON
            // Pour les chaînes simples (comme les couleurs), elles seront stockées comme des chaînes JSON
            $setting = $model->updateOrCreate(
                ['key' => $key],
                [
                    'value' => $value,
                    'group' => $group,
                ]
            );
            
            \Illuminate\Support\Facades\Log::info("Setting enregistré: {$key} = " . (is_string($value) ? $value : json_encode($value)) . " dans la base: {$databaseName}", [
                'raw_value' => $setting->getAttributes()['value'] ?? null,
                'casted_value' => $setting->value
            ]);

            // Invalider le cache avec le nom de la base de données
            Cache::forget("tenant_setting_{$key}_{$databaseName}");
            Cache::forget("tenant_settings_group_{$group}_{$databaseName}");
            Cache::forget("tenant_settings_all_{$databaseName}");
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error("Erreur lors de l'enregistrement du setting {$key}: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Récupérer tous les settings d'un groupe
     */
    public static function getGroup(string $group): array
    {
        // Vérifier que la table existe avant d'essayer de l'utiliser
        try {
            $connection = \Illuminate\Support\Facades\DB::connection();
            $databaseName = $connection->getDatabaseName();
            
            // Si on est sur la base principale, retourner un tableau vide
            $mainDatabase = config('database.connections.mysql.database');
            if ($databaseName === $mainDatabase) {
                return [];
            }
            
            // Vérifier que la table existe
            if (!\Illuminate\Support\Facades\Schema::connection($connection->getName())->hasTable('tenant_settings')) {
                return [];
            }
        } catch (\Exception $e) {
            return [];
        }
        
        $cacheKey = "tenant_settings_group_{$group}_{$databaseName}";
        $connectionName = $connection->getName();
        
        // Utiliser un cache plus court (5 minutes) pour permettre des mises à jour plus rapides
        return Cache::remember($cacheKey, 300, function () use ($group, $connectionName, $databaseName) {
            try {
                $model = new static();
                $model->setConnection($connectionName);
                $settings = $model->where('group', $group)->get();
                
                \Illuminate\Support\Facades\Log::info("Récupération du groupe {$group} depuis la base {$databaseName}", [
                    'count' => $settings->count(),
                    'settings' => $settings->toArray()
                ]);
                
                // Construire le tableau manuellement pour gérer correctement les valeurs
                $result = [];
                foreach ($settings as $setting) {
                    // Récupérer la valeur brute depuis la base (avant le cast)
                    $rawValue = $setting->getAttributes()['value'] ?? null;
                    
                    // Si la valeur brute est une chaîne JSON, la décoder
                    if (is_string($rawValue)) {
                        $decoded = json_decode($rawValue, true);
                        // Si le décodage a réussi et que ce n'est pas null ET que ce n'est pas une chaîne simple, utiliser la valeur décodée
                        // Sinon, utiliser la chaîne originale (cas des couleurs simples comme "#00286f")
                        if (json_last_error() === JSON_ERROR_NONE && $decoded !== null && !is_string($decoded)) {
                            $value = $decoded;
                        } else {
                            // C'est une chaîne simple (comme une couleur), utiliser la valeur brute décodée ou la chaîne
                            $value = $decoded !== null && is_string($decoded) ? $decoded : $rawValue;
                        }
                    } else {
                        // Si c'est déjà un tableau (cast appliqué), vérifier si c'est une chaîne dans un tableau
                        $castedValue = $setting->value;
                        if (is_array($castedValue) && count($castedValue) === 1 && isset($castedValue[0])) {
                            // Cas spécial : tableau avec une seule valeur (peut être une chaîne mal castée)
                            $value = $castedValue[0];
                        } else {
                            $value = $castedValue;
                        }
                    }
                    
                    // S'assurer que les couleurs sont des chaînes
                    if (in_array($setting->key, ['primary_color', 'secondary_color', 'accent_color', 'background_color']) && !is_string($value)) {
                        $value = is_array($value) && isset($value[0]) ? $value[0] : (string) $value;
                    }
                    
                    $result[$setting->key] = $value;
                }
                
                \Illuminate\Support\Facades\Log::info("Résultat après traitement pour le groupe {$group}", ['result' => $result]);
                
                return $result;
            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::error("Erreur lors de la récupération du groupe {$group}: " . $e->getMessage(), [
                    'trace' => $e->getTraceAsString()
                ]);
                return [];
            }
        });
    }

    /**
     * Récupérer tous les settings
     */
    public static function getAll(): array
    {
        // Vérifier que la table existe avant d'essayer de l'utiliser
        try {
            $connection = \Illuminate\Support\Facades\DB::connection();
            $databaseName = $connection->getDatabaseName();
            
            // Si on est sur la base principale, retourner un tableau vide
            $mainDatabase = config('database.connections.mysql.database');
            if ($databaseName === $mainDatabase) {
                return [];
            }
            
            // Vérifier que la table existe
            if (!\Illuminate\Support\Facades\Schema::connection($connection->getName())->hasTable('tenant_settings')) {
                return [];
            }
        } catch (\Exception $e) {
            return [];
        }
        
        $cacheKey = "tenant_settings_all_{$databaseName}";
        $connectionName = $connection->getName();
        
        // Utiliser un cache plus court (5 minutes) pour permettre des mises à jour plus rapides
        return Cache::remember($cacheKey, 300, function () use ($connectionName) {
            try {
                $model = new static();
                $model->setConnection($connectionName);
                return $model->all()
                    ->groupBy('group')
                    ->map(function ($items) {
                        return $items->pluck('value', 'key')->toArray();
                    })
                    ->toArray();
            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::error("Erreur lors de la récupération de tous les settings: " . $e->getMessage());
                return [];
            }
        });
    }

    /**
     * Supprimer un setting
     */
    public static function remove(string $key): void
    {
        static::where('key', $key)->delete();
        
        // Invalider tous les caches possibles avec le nom de la base de données
        try {
            $connection = \Illuminate\Support\Facades\DB::connection();
            $databaseName = $connection->getDatabaseName();
            
            Cache::forget("tenant_setting_{$key}_{$databaseName}");
            Cache::forget("tenant_settings_all_{$databaseName}");
            
            // Invalider aussi tous les groupes (on ne connaît pas le groupe ici)
            // On vide tout le cache pour être sûr
            Cache::flush();
        } catch (\Exception $e) {
            Cache::flush();
        }
    }

    /**
     * Vider le cache des settings
     */
    public static function clearCache(): void
    {
        Cache::flush();
    }
}
