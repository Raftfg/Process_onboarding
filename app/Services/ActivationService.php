<?php

namespace App\Services;

use App\Models\OnboardingActivation;
use App\Models\OnboardingSession;
use App\Models\User;
use App\Services\TenantService;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Carbon\Carbon;

class ActivationService
{
    protected $tenantService;

    public function __construct(TenantService $tenantService)
    {
        $this->tenantService = $tenantService;
    }

    /**
     * Crée un token d'activation et le stocke
     */
    public function createActivationToken(string $email, string $organizationName, string $subdomain = null, string $databaseName = null): string
    {
        // Vérifier que l'email n'a pas déjà un token d'activation actif pour CE sous-domaine
        $existingActivation = OnboardingActivation::where('email', $email)
            ->where('subdomain', $subdomain)
            ->whereNull('activated_at')
            ->where('expires_at', '>', now())
            ->first();
        
        if ($existingActivation) {
            Log::info('Remplacement du token d\'activation existant pour ce sous-domaine', [
                'email' => $email,
                'subdomain' => $subdomain,
            ]);
            $existingActivation->delete();
        }
        
        // Supprimer TOUS les tokens existants pour ce sous-domaine (actifs, expirés ou activés)
        // pour éviter les violations de contrainte d'unicité
        if ($subdomain) {
            $existingBySubdomain = OnboardingActivation::where('subdomain', $subdomain)->get();
            
            if ($existingBySubdomain->isNotEmpty()) {
                $count = $existingBySubdomain->count();
                Log::warning('Suppression des tokens d\'activation existants pour le sous-domaine', [
                    'subdomain' => $subdomain,
                    'count' => $count,
                    'token_ids' => $existingBySubdomain->pluck('id')->toArray(),
                ]);
                
                // Supprimer tous les tokens existants pour ce sous-domaine
                OnboardingActivation::where('subdomain', $subdomain)->delete();
            }
        }
        
        // Générer un token unique
        $token = Str::random(64);
        
        // Vérifier que le token n'existe pas déjà (très peu probable)
        while (OnboardingActivation::where('token', $token)->exists()) {
            $token = Str::random(64);
        }

        // Récupérer la durée d'expiration en jours depuis la config
        $expiresDays = config('app.activation_token_expires_days', 7);
        $expiresAt = Carbon::now()->addDays($expiresDays);
        
        // Créer l'enregistrement d'activation
        OnboardingActivation::create([
            'email' => $email,
            'organization_name' => $organizationName,
            'token' => $token,
            'subdomain' => $subdomain,
            'database_name' => $databaseName,
            'expires_at' => $expiresAt,
        ]);

        Log::info('Token d\'activation créé', [
            'email' => $email,
            'organization' => $organizationName,
            'expires_at' => $expiresAt->toDateTimeString(),
            'expires_in_days' => $expiresDays,
        ]);

        return $token;
    }

    /**
     * Valide un token d'activation
     */
    public function validateToken(string $token): bool
    {
        $activation = $this->getActivationByToken($token);
        
        if (!$activation) {
            return false;
        }

        return $activation->isValid();
    }

    /**
     * Récupère une activation par son token
     */
    public function getActivationByToken(string $token): ?OnboardingActivation
    {
        return OnboardingActivation::where('token', $token)->first();
    }

    /**
     * Active le compte en créant l'utilisateur admin
     */
    public function activateAccount(string $token, string $password): array
    {
        $activation = $this->getActivationByToken($token);

        if (!$activation) {
            throw new \Exception('Token d\'activation invalide');
        }

        if (!$activation->isValid()) {
            if ($activation->isExpired()) {
                $expiresInDays = config('app.activation_token_expires_days', 7);
                throw new \Exception("Le lien d'activation a expiré (valable {$expiresInDays} " . ($expiresInDays > 1 ? 'jours' : 'jour') . "). Veuillez demander un nouveau lien.");
            }
            if ($activation->isActivated()) {
                throw new \Exception('Ce compte a déjà été activé.');
            }
            throw new \Exception('Le lien d\'activation n\'est plus valide.');
        }

        // Basculer vers la base de données du tenant
        if ($activation->database_name) {
            $this->tenantService->switchToTenantDatabase($activation->database_name);
        } else {
            throw new \Exception('Base de données du tenant non trouvée.');
        }

        try {
            // Vérifier si l'utilisateur existe déjà
            $existingUser = User::where('email', $activation->email)->first();
            
            if ($existingUser) {
                throw new \Exception('Un utilisateur avec cet email existe déjà.');
            }

            // Tenter de récupérer les informations de nom depuis la session d'onboarding
            $adminName = $activation->organization_name;
            try {
                $onboardingSession = OnboardingSession::on('mysql')
                    ->where('subdomain', $activation->subdomain)
                    ->where('admin_email', $activation->email)
                    ->first();
                
                if ($onboardingSession && (!empty($onboardingSession->admin_first_name) || !empty($onboardingSession->admin_last_name))) {
                    $firstName = $onboardingSession->admin_first_name ?? '';
                    $lastName = $onboardingSession->admin_last_name ?? '';
                    $adminName = trim($firstName . ' ' . $lastName);
                    
                    if (empty($adminName)) {
                        $adminName = $activation->organization_name;
                    }
                }
            } catch (\Exception $e) {
                Log::warning("Impossible de récupérer le nom admin depuis la session: " . $e->getMessage());
            }

            // Créer l'utilisateur administrateur
            $user = User::create([
                'name' => $adminName,
                'email' => $activation->email,
                'password' => Hash::make($password),
                'email_verified_at' => now(),
                'password_changed_at' => now(),
                'role' => 'admin',
                'status' => 'active',
            ]);

            // Marquer l'activation comme complétée
            $activation->markAsActivated();

            // Mettre à jour le statut dans onboarding_sessions
            try {
                Log::info("Recherche OnboardingSession", [
                    'subdomain' => $activation->subdomain,
                    'email' => $activation->email
                ]);

                $onboardingSession = OnboardingSession::on('mysql')
                    ->where('subdomain', $activation->subdomain)
                    ->where('admin_email', $activation->email)
                    ->first();
                
                if ($onboardingSession) {
                    $onboardingSession->update([
                        'status' => 'completed',
                        'completed_at' => now(),
                    ]);
                    
                    // Nettoyer le cache pour forcer la mise à jour
                    $this->tenantService->clearTenantCache($activation->subdomain);
                    
                    Log::info('Statut onboarding_sessions mis à jour', [
                        'subdomain' => $activation->subdomain,
                        'session_id' => $onboardingSession->id,
                        'status' => 'completed',
                    ]);
                } else {
                    // Essayer de trouver par sous-domaine uniquement pour voir si l'email diffère
                    $sessionBySubdomain = OnboardingSession::on('mysql')
                        ->where('subdomain', $activation->subdomain)
                        ->first();
                        
                    Log::warning('OnboardingSession non trouvée pour mise à jour', [
                        'subdomain' => $activation->subdomain,
                        'search_email' => $activation->email,
                        'found_by_subdomain' => $sessionBySubdomain ? 'yes' : 'no',
                        'session_email' => $sessionBySubdomain ? $sessionBySubdomain->admin_email : 'N/A'
                    ]);
                }
            } catch (\Exception $e) {
                Log::warning('Erreur lors de la mise à jour de onboarding_sessions', [
                    'error' => $e->getMessage(),
                ]);
                // Ne pas faire échouer l'activation si cette mise à jour échoue
            }

            Log::info('Compte activé avec succès - Utilisateur créé', [
                'email' => $activation->email,
                'user_id' => $user->id,
                'subdomain' => $activation->subdomain,
                'database' => $activation->database_name,
            ]);

            // Retourner les informations nécessaires pour la connexion
            // Note: La base de données du tenant est déjà active à ce stade
            return [
                'user' => $user,
                'subdomain' => $activation->subdomain,
                'email' => $activation->email,
                'database_name' => $activation->database_name,
            ];
        } catch (\Exception $e) {
            Log::error('Erreur lors de l\'activation du compte', [
                'token' => $token,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }
}
