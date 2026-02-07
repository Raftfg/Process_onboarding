<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Log;
use App\Models\Application;

class MasterKeyAuth
{
    /**
     * Handle an incoming request.
     * 
     * Vérifie que la requête contient une master key valide
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Récupérer la master key depuis le header
        $masterKey = $request->header('X-Master-Key');

        if (!$masterKey) {
            return response()->json([
                'success' => false,
                'message' => 'Master key manquante. Veuillez fournir votre master key via le header X-Master-Key.'
            ], 401);
        }

        // Valider la master key
        $application = Application::validateMasterKey($masterKey);

        if (!$application) {
            Log::warning('Tentative d\'accès avec une master key invalide', [
                'ip' => $request->ip(),
                'key_prefix' => substr($masterKey, 0, 8) . '...'
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Master key invalide ou application inactive.'
            ], 401);
        }

        // Vérifier que l'application est active
        if (!$application->is_active) {
            return response()->json([
                'success' => false,
                'message' => 'Votre application a été suspendue. Contactez le support.'
            ], 403);
        }

        // Vérifier que l'app_id dans l'URL correspond à l'application
        $appIdFromUrl = $request->route('app_id');
        if ($appIdFromUrl && $appIdFromUrl !== $application->app_id) {
            return response()->json([
                'success' => false,
                'message' => 'L\'app_id dans l\'URL ne correspond pas à votre application.'
            ], 403);
        }

        // Ajouter l'application à la requête pour utilisation ultérieure
        $request->merge(['application' => $application]);

        return $next($request);
    }
}
