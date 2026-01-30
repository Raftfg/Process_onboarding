<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class RecaptchaService
{
    /**
     * Valider le token reCAPTCHA
     */
    public function verify(?string $token, ?string $ip = null): bool
    {
        // Vérifier si reCAPTCHA est désactivé
        if (!config('recaptcha.enabled', true)) {
            Log::info('reCAPTCHA désactivé via configuration');
            return true;
        }
        
        $secretKey = config('recaptcha.secret_key');
        
        // En développement local, si pas de token ou clé vide, accepter
        if (config('app.env') === 'local') {
            if (empty($token) || empty($secretKey)) {
                Log::info('reCAPTCHA ignoré en local (token ou clé vide)');
                return true;
            }
        }
        
        if (empty($secretKey)) {
            Log::warning('reCAPTCHA secret_key non configuré');
            // En local, accepter si pas de clé
            if (config('app.env') === 'local') {
                return true;
            }
            return false;
        }

        if (empty($token)) {
            Log::warning('reCAPTCHA token vide');
            // En local, accepter si pas de token
            if (config('app.env') === 'local') {
                return true;
            }
            return false;
        }

        try {
            $response = Http::asForm()->post('https://www.google.com/recaptcha/api/siteverify', [
                'secret' => $secretKey,
                'response' => $token,
                'remoteip' => $ip ?? request()->ip(),
            ]);

            $result = $response->json();
            
            Log::info('reCAPTCHA validation response', [
                'success' => $result['success'] ?? false,
                'error-codes' => $result['error-codes'] ?? [],
                'score' => $result['score'] ?? null,
                'hostname' => $result['hostname'] ?? null,
            ]);

            if ($result['success'] === true) {
                // Pour reCAPTCHA v3, vérifier aussi le score (optionnel)
                if (config('recaptcha.version') === 'v3') {
                    $score = $result['score'] ?? 0;
                    // Score minimum recommandé: 0.5
                    if ($score < 0.5) {
                        Log::warning('reCAPTCHA v3 score trop bas', ['score' => $score]);
                        return false;
                    }
                }
                
                return true;
            }

            Log::warning('reCAPTCHA validation échouée', [
                'errors' => $result['error-codes'] ?? [],
                'ip' => $ip ?? request()->ip(),
                'hostname' => $result['hostname'] ?? null,
            ]);

            return false;
        } catch (\Exception $e) {
            Log::error('Erreur lors de la validation reCAPTCHA: ' . $e->getMessage(), [
                'exception' => get_class($e),
                'trace' => $e->getTraceAsString()
            ]);
            // En local, en cas d'erreur, accepter pour faciliter le développement
            if (config('app.env') === 'local') {
                Log::warning('reCAPTCHA validation échouée mais acceptée en local');
                return true;
            }
            return false;
        }
    }
}
