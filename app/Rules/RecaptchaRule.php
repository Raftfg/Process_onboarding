<?php

namespace App\Rules;

use Illuminate\Contracts\Validation\Rule;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class RecaptchaRule implements Rule
{
    /**
     * Determine if the validation rule passes.
     */
    public function passes($attribute, $value): bool
    {
        $secretKey = config('services.recaptcha.secret_key');
        
        Log::info('RecaptchaRule::passes appelé', [
            'has_secret_key' => !empty($secretKey),
            'has_value' => !empty($value),
            'value_length' => strlen($value ?? ''),
            'env' => config('app.env'),
            'debug' => config('app.debug'),
        ]);
        
        // En développement local, si la clé n'est pas configurée, on accepte sans vérification
        if (empty($secretKey)) {
            if (config('app.env') === 'local' || config('app.debug')) {
                Log::info('reCAPTCHA secret key not configured, skipping validation in development environment');
                return true;
            }
            Log::error('reCAPTCHA secret key not configured in production');
            return false;
        }

        // Si la valeur est vide mais qu'on est en développement, on accepte
        if (empty($value)) {
            if (config('app.env') === 'local' || config('app.debug')) {
                Log::info('reCAPTCHA response empty, skipping validation in development environment');
                return true;
            }
            Log::warning('reCAPTCHA response empty in production');
            return false;
        }

        try {
            $response = Http::timeout(10)->asForm()->post('https://www.google.com/recaptcha/api/siteverify', [
                'secret' => $secretKey,
                'response' => $value,
                'remoteip' => request()->ip(),
            ]);

            if (!$response->successful()) {
                Log::warning('reCAPTCHA API request failed', [
                    'status' => $response->status(),
                ]);
                // En développement, on accepte même si l'API échoue
                if (config('app.env') === 'local' || config('app.debug')) {
                    return true;
                }
                return false;
            }

            $result = $response->json();

            if (!isset($result['success']) || !$result['success']) {
                Log::warning('reCAPTCHA validation failed', [
                    'errors' => $result['error-codes'] ?? [],
                    'result' => $result,
                ]);
                // En développement, on accepte même si la validation échoue
                if (config('app.env') === 'local' || config('app.debug')) {
                    return true;
                }
                return false;
            }

            return true;
        } catch (\Exception $e) {
            Log::error('reCAPTCHA validation error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            // En développement, on accepte même en cas d'erreur
            if (config('app.env') === 'local' || config('app.debug')) {
                return true;
            }
            return false;
        }
    }

    /**
     * Get the validation error message.
     */
    public function message(): string
    {
        return 'La vérification reCAPTCHA a échoué. Veuillez réessayer.';
    }
}
