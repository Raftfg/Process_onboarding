<?php

namespace App\Helpers;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Log;

class ErrorFormatter
{
    /**
     * Convertit une exception en message utilisateur compréhensible
     */
    public static function formatException(\Exception $e): string
    {
        // Si c'est une QueryException (erreur SQL), la formater spécifiquement
        if ($e instanceof QueryException) {
            return self::formatQueryException($e);
        }
        
        // Pour les autres exceptions, vérifier si c'est une erreur connue
        $message = $e->getMessage();
        
        // Messages d'erreur déjà formatés pour l'utilisateur
        $userFriendlyMessages = [
            'Token d\'activation invalide' => 'Le lien d\'activation est invalide. Veuillez vérifier votre email ou demander un nouveau lien.',
            'Le lien d\'activation a expiré' => 'Le lien d\'activation a expiré. Veuillez demander un nouveau lien d\'activation.',
            'Ce compte a déjà été activé' => 'Ce compte a déjà été activé. Vous pouvez vous connecter directement.',
            'Base de données du tenant non trouvée' => 'Une erreur technique est survenue. Veuillez contacter le support.',
            'Utilisateur non trouvé' => 'Une erreur est survenue lors de la création de votre compte. Veuillez réessayer.',
            'Un compte avec l\'email' => 'Cet email est déjà enregistré. Vous pouvez toutefois l\'utiliser pour créer un nouvel espace avec un nom d\'organisation différent.',
            'Une organisation avec le nom' => 'Ce nom d\'organisation est déjà utilisé. Veuillez choisir un autre nom.',
            'Le sous-domaine' => 'Ce nom d\'organisation génère un sous-domaine déjà utilisé. Veuillez choisir un autre nom.',
            'Le nom de base de données' => 'Une erreur technique est survenue. Veuillez réessayer avec un autre nom d\'organisation.',
        ];
        
        // Vérifier si le message correspond à un pattern connu
        foreach ($userFriendlyMessages as $pattern => $friendlyMessage) {
            if (str_contains($message, $pattern)) {
                return $friendlyMessage;
            }
        }
        
        // Si c'est une erreur de validation, retourner le message tel quel
        if (str_contains($message, 'validation') || str_contains($message, 'required') || str_contains($message, 'invalid')) {
            return $message;
        }
        
        // Message générique pour les erreurs inconnues
        Log::warning('Erreur non formatée affichée à l\'utilisateur', [
            'message' => $message,
            'exception' => get_class($e),
        ]);
        
        return 'Une erreur est survenue lors de la création de votre espace. Veuillez réessayer ou contacter le support si le problème persiste.';
    }
    
    /**
     * Formate une QueryException en message utilisateur
     */
    protected static function formatQueryException(QueryException $e): string
    {
        $errorCode = $e->getCode();
        $errorMessage = $e->getMessage();
        
        // Erreur 1062 : Duplicate entry (contrainte d'unicité)
        if ($errorCode == 23000 || str_contains($errorMessage, 'Duplicate entry')) {
            if (str_contains($errorMessage, 'subdomain')) {
                return 'Ce nom d\'organisation génère un sous-domaine déjà utilisé. Veuillez choisir un autre nom.';
            }
            if (str_contains($errorMessage, 'email') || str_contains($errorMessage, 'admin_email')) {
                return 'Un compte avec cet email existe déjà. Veuillez utiliser un autre email ou vous connecter.';
            }
            if (str_contains($errorMessage, 'database_name')) {
                return 'Une erreur technique est survenue. Veuillez réessayer avec un autre nom d\'organisation.';
            }
            if (str_contains($errorMessage, 'organization_name') || str_contains($errorMessage, 'hospital_name')) {
                return 'Ce nom d\'organisation est déjà utilisé. Veuillez choisir un autre nom.';
            }
            return 'Cette information est déjà utilisée. Veuillez modifier vos données et réessayer.';
        }
        
        // Erreur 1452 : Cannot add or update a child row (clé étrangère)
        if ($errorCode == 23000 && str_contains($errorMessage, 'foreign key')) {
            return 'Une erreur technique est survenue lors de la création de votre espace. Veuillez réessayer.';
        }
        
        // Erreur 1451 : Cannot delete or update a parent row (clé étrangère)
        if ($errorCode == 23000 && str_contains($errorMessage, 'a foreign key constraint fails')) {
            return 'Une erreur technique est survenue. Veuillez contacter le support.';
        }
        
        // Erreur 1045 : Access denied
        if ($errorCode == 1045) {
            return 'Une erreur de connexion à la base de données est survenue. Veuillez contacter le support.';
        }
        
        // Erreur 2002 : Connection refused
        if ($errorCode == 2002) {
            return 'Une erreur de connexion est survenue. Veuillez réessayer dans quelques instants.';
        }
        
        // Erreur 42S02 : Table doesn't exist
        if (str_contains($errorMessage, "doesn't exist") || str_contains($errorMessage, 'Base table or view not found')) {
            return 'Une erreur technique est survenue. Veuillez contacter le support.';
        }
        
        // Erreur 42S22 : Column doesn't exist
        if (str_contains($errorMessage, "Unknown column")) {
            return 'Une erreur technique est survenue. Veuillez contacter le support.';
        }
        
        // Message générique pour les erreurs SQL non reconnues
        Log::error('Erreur SQL non formatée', [
            'code' => $errorCode,
            'message' => $errorMessage,
            'sql' => $e->getSql() ?? null,
        ]);
        
        return 'Une erreur technique est survenue lors de la création de votre espace. Veuillez réessayer ou contacter le support si le problème persiste.';
    }
}
