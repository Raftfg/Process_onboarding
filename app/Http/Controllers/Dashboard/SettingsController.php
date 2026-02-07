<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class SettingsController extends Controller
{
    /**
     * Afficher les paramètres
     */
    public function index()
    {
        $user = Auth::user();
        
        if (!$user) {
            // Si l'utilisateur n'est pas authentifié, rediriger vers login
            return redirect()->route('login');
        }
        
        // Vérifier et ajouter les colonnes manquantes si nécessaire
        $this->ensureProfileColumnsExist();
        
        return view('dashboard.settings.index', compact('user'));
    }

    /**
     * Vérifier et ajouter les colonnes de profil manquantes
     */
    protected function ensureProfileColumnsExist(): void
    {
        try {
            $connection = \Illuminate\Support\Facades\DB::connection();
            
            // Vérifier si la table users existe
            $tables = $connection->select("SHOW TABLES LIKE 'users'");
            if (empty($tables)) {
                \Illuminate\Support\Facades\Log::warning("La table users n'existe pas dans la base de données actuelle");
                return;
            }
            
            $columns = $connection->select("SHOW COLUMNS FROM `users`");
            $columnNames = array_column($columns, 'Field');
            
            \Illuminate\Support\Facades\Log::info("Colonnes existantes dans users: " . implode(', ', $columnNames));
            
            if (!in_array('first_name', $columnNames)) {
                $connection->statement("ALTER TABLE `users` ADD COLUMN `first_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL AFTER `name`");
                \Illuminate\Support\Facades\Log::info("Colonne first_name ajoutée");
            }
            if (!in_array('last_name', $columnNames)) {
                $connection->statement("ALTER TABLE `users` ADD COLUMN `last_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL AFTER `first_name`");
                \Illuminate\Support\Facades\Log::info("Colonne last_name ajoutée");
            }
            if (!in_array('company', $columnNames)) {
                // Trouver la position de 'phone' pour placer 'company' après
                $phoneIndex = array_search('phone', $columnNames);
                $afterColumn = $phoneIndex !== false ? 'phone' : 'status';
                $connection->statement("ALTER TABLE `users` ADD COLUMN `company` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL AFTER `{$afterColumn}`");
                \Illuminate\Support\Facades\Log::info("Colonne company ajoutée");
            }
            if (!in_array('address', $columnNames)) {
                $connection->statement("ALTER TABLE `users` ADD COLUMN `address` text COLLATE utf8mb4_unicode_ci DEFAULT NULL AFTER `company`");
                \Illuminate\Support\Facades\Log::info("Colonne address ajoutée");
            }
            if (!in_array('city', $columnNames)) {
                $connection->statement("ALTER TABLE `users` ADD COLUMN `city` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL AFTER `address`");
                \Illuminate\Support\Facades\Log::info("Colonne city ajoutée");
            }
            if (!in_array('postal_code', $columnNames)) {
                $connection->statement("ALTER TABLE `users` ADD COLUMN `postal_code` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL AFTER `city`");
                \Illuminate\Support\Facades\Log::info("Colonne postal_code ajoutée");
            }
            if (!in_array('country', $columnNames)) {
                $connection->statement("ALTER TABLE `users` ADD COLUMN `country` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL AFTER `postal_code`");
                \Illuminate\Support\Facades\Log::info("Colonne country ajoutée");
            }
            if (!in_array('job_title', $columnNames)) {
                $connection->statement("ALTER TABLE `users` ADD COLUMN `job_title` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL AFTER `country`");
                \Illuminate\Support\Facades\Log::info("Colonne job_title ajoutée");
            }
            if (!in_array('bio', $columnNames)) {
                $connection->statement("ALTER TABLE `users` ADD COLUMN `bio` text COLLATE utf8mb4_unicode_ci DEFAULT NULL AFTER `job_title`");
                \Illuminate\Support\Facades\Log::info("Colonne bio ajoutée");
            }
        } catch (\Exception $e) {
            // Logger l'erreur avec plus de détails
            \Illuminate\Support\Facades\Log::error("Erreur lors de la vérification des colonnes de profil: " . $e->getMessage(), [
                'exception' => get_class($e),
                'trace' => $e->getTraceAsString(),
                'database' => \Illuminate\Support\Facades\DB::connection()->getDatabaseName(),
            ]);
            // Ne pas bloquer mais logger l'erreur
        }
    }

    /**
     * Mettre à jour les paramètres
     */
    public function update(Request $request)
    {
        $user = Auth::user();

        // Vérifier et ajouter les colonnes manquantes si nécessaire (AVANT la validation)
        $this->ensureProfileColumnsExist();
        
        // Vérifier à nouveau que les colonnes existent après la création
        $connection = \Illuminate\Support\Facades\DB::connection();
        $columns = $connection->select("SHOW COLUMNS FROM `users`");
        $columnNames = array_column($columns, 'Field');

        $request->validate([
            'name' => 'required|string|max:255',
            'first_name' => 'nullable|string|max:255',
            'last_name' => 'nullable|string|max:255',
            'email' => 'required|string|email|max:255|unique:users,email,' . $user->id,
            'phone' => 'nullable|string|max:20',
            'company' => 'nullable|string|max:255',
            'address' => 'nullable|string|max:500',
            'city' => 'nullable|string|max:255',
            'postal_code' => 'nullable|string|max:20',
            'country' => 'nullable|string|max:255',
            'job_title' => 'nullable|string|max:255',
            'bio' => 'nullable|string|max:1000',
            'password' => ['nullable', 'confirmed', Password::defaults()],
        ]);

        // Auto-remplir le nom complet si prénom et nom sont fournis
        $name = $request->name;
        if ($request->filled('first_name') && $request->filled('last_name')) {
            $name = trim($request->first_name . ' ' . $request->last_name);
        } elseif ($request->filled('first_name')) {
            $name = $request->first_name;
        } elseif ($request->filled('last_name')) {
            $name = $request->last_name;
        }

        // Vérifier quelles colonnes existent avant de les utiliser
        $connection = \Illuminate\Support\Facades\DB::connection();
        $columns = $connection->select("SHOW COLUMNS FROM `users`");
        $columnNames = array_column($columns, 'Field');
        
        $user->name = $name;
        $user->email = $request->email;
        
        // Utiliser seulement les colonnes qui existent
        if (in_array('phone', $columnNames)) {
            $user->phone = $request->phone;
        }
        if (in_array('first_name', $columnNames)) {
            $user->first_name = $request->first_name;
        }
        if (in_array('last_name', $columnNames)) {
            $user->last_name = $request->last_name;
        }
        if (in_array('company', $columnNames)) {
            $user->company = $request->company;
        }
        if (in_array('address', $columnNames)) {
            $user->address = $request->address;
        }
        if (in_array('city', $columnNames)) {
            $user->city = $request->city;
        }
        if (in_array('postal_code', $columnNames)) {
            $user->postal_code = $request->postal_code;
        }
        if (in_array('country', $columnNames)) {
            $user->country = $request->country;
        }
        if (in_array('job_title', $columnNames)) {
            $user->job_title = $request->job_title;
        }
        if (in_array('bio', $columnNames)) {
            $user->bio = $request->bio;
        }

        if ($request->filled('password')) {
            $user->password = Hash::make($request->password);
            $user->password_changed_at = now();
        }

        $user->save();

        // Préserver le token auto_login_token dans la redirection si présent
        $redirect = redirect()->route('dashboard.settings')
            ->with('success', 'Paramètres mis à jour avec succès');
        
        if (request()->has('auto_login_token')) {
            $token = request()->query('auto_login_token');
            $redirect = redirect()->route('dashboard.settings', ['auto_login_token' => $token])
                ->with('success', 'Paramètres mis à jour avec succès');
        }
        
        return $redirect;
    }
}
