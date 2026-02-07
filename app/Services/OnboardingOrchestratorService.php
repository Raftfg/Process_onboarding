<?php

namespace App\Services;

use App\Models\Application;
use App\Models\ApiKey;
use App\Models\OnboardingRegistration;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

/**
 * Service d'orchestration d'onboarding stateless.
 *
 * Responsabilités :
 * - start : enregistre email + organisation + sous-domaine dans la base centrale
 * - provision : configure DNS/SSL, génère éventuellement une API key
 * - status : retourne l'état courant d'un onboarding
 *
 * Ne crée PAS de tenant métier, ni de base de données de tenant,
 * n'envoie PAS d'emails, ne gère PAS de sessions.
 */
class OnboardingOrchestratorService
{
    public function __construct(
        protected SubdomainService $subdomainService,
        protected OrganizationNameGenerator $organizationNameGenerator
    ) {
    }

    /**
     * Démarre un onboarding (création de l'enregistrement central + sous-domaine).
     */
    public function start(Application $application, string $email, ?string $organizationName = null): OnboardingRegistration
    {
        // Vérifier que l'application possède bien une base centrale associée
        if (!$application->hasDatabase()) {
            throw new \RuntimeException('Aucune base de données associée à cette application. Enregistrez l\'application avant de démarrer un onboarding.');
        }

        $appDatabase = $application->appDatabase;

        // Générer le nom d'organisation si non fourni
        if (empty($organizationName)) {
            $organizationName = $this->organizationNameGenerator->generate('auto', [
                'email' => $email,
            ]);
        }

        // Générer un sous-domaine unique
        $subdomain = $this->subdomainService->generateUniqueSubdomain($organizationName, $email);

        // Créer l'enregistrement central (statut pending, infra non configurée)
        $registration = OnboardingRegistration::create([
            'application_id'   => $application->id,
            'app_database_id'  => $appDatabase->id,
            'email'            => $email,
            'organization_name'=> $organizationName,
            'subdomain'        => $subdomain,
            'status'           => 'pending',
            'api_key'          => null,
            'api_secret'       => null,
            'metadata'         => [],
            'dns_configured'   => false,
            'ssl_configured'   => false,
        ]);

        Log::info('Onboarding démarré', [
            'application_id' => $application->id,
            'uuid'           => $registration->uuid,
            'email'          => $email,
            'subdomain'      => $subdomain,
        ]);

        return $registration;
    }

    /**
     * Provisionne l'infrastructure (DNS/SSL) et génère éventuellement une clé API.
     *
     * Retourne :
     * - registration (mise à jour)
     * - api_key_plain / api_secret_plain si générés pour la première fois
     */
    public function provision(Application $application, string $uuid, bool $generateApiKey = false): array
    {
        $registration = OnboardingRegistration::where('uuid', $uuid)
            ->where('application_id', $application->id)
            ->first();

        if (!$registration) {
            throw new \RuntimeException('Onboarding introuvable pour cette application.');
        }

        $apiKeyPlain = null;
        $isIdempotent = false;

        // Si déjà prêt et infra configurée, rendre l'appel idempotent
        if ($registration->isActivated() && $registration->isInfrastructureReady()) {
            $isIdempotent = true;
            // Ne pas incrémenter les tentatives si déjà provisionné
            return [
                'registration'    => $registration->fresh(),
                'api_key_plain'   => null,
                'api_secret_plain'=> null,
                'is_idempotent'   => true,
            ];
        }

        // Incrémenter le compteur de tentatives
        $registration->provisioning_attempts = ($registration->provisioning_attempts ?? 0) + 1;

        // Configurer DNS et SSL (implémentation réelle à brancher plus tard)
        $dnsOk = $this->subdomainService->configureDNS($registration->subdomain);
        $sslOk = $this->subdomainService->configureSSL($registration->subdomain);

        $status = ($dnsOk && $sslOk) ? 'activated' : 'cancelled';

        // Génération optionnelle de clé API (seulement si pas déjà générée)
        if ($generateApiKey && !$registration->api_key) {
            $apiKeyResult = ApiKey::generate('Onboarding - '.$registration->subdomain, [
                'app_name'       => $application->app_name,
                'application_id' => $application->id,
            ]);

            $apiKeyPlain = $apiKeyResult['key'];

            $registration->api_key    = $apiKeyResult['key_prefix']; // on stocke le préfixe en clair pour debug éventuel
            $registration->api_secret = Hash::make($apiKeyPlain);
        }

        $registration->status         = $status;
        $registration->dns_configured = $dnsOk;
        $registration->ssl_configured = $sslOk;
        $registration->save();

        Log::info('Onboarding provisionné', [
            'application_id' => $application->id,
            'uuid'           => $registration->uuid,
            'subdomain'      => $registration->subdomain,
            'dns_ok'         => $dnsOk,
            'ssl_ok'         => $sslOk,
            'status'         => $status,
            'api_generated'  => (bool) $apiKeyPlain,
        ]);

        return [
            'registration'     => $registration->fresh(),
            'api_key_plain'    => $apiKeyPlain,
            'api_secret_plain' => $apiKeyPlain, // même valeur, exposée une seule fois
            'is_idempotent'    => $isIdempotent,
        ];
    }

    /**
     * Récupère le statut d'un onboarding.
     */
    public function getStatus(Application $application, string $uuid): ?OnboardingRegistration
    {
        return OnboardingRegistration::where('uuid', $uuid)
            ->where('application_id', $application->id)
            ->first();
    }
}

