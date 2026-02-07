<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use OpenApi\Attributes as OA;

#[OA\Info(
    version: "1.0.0",
    title: "Akasi Onboarding API",
    description: "API de gestion de l'onboarding pour les applications du groupe Akasi. Cette API permet de provisionner des tenants, de gérer des webhooks et de suivre l'état de déploiement.",
    contact: new OA\Contact(email: "tech@akasi-group.com")
)]
#[OA\Server(
    url: "http://localhost:8000",
    description: "Serveur de Développement Local"
)]
#[OA\SecurityScheme(
    securityScheme: "MasterKey",
    type: "apiKey",
    in: "header",
    name: "X-Master-Key",
    description: "Master key de votre application cliente, obtenue via /api/v1/applications/register"
)]
#[OA\SecurityScheme(
    securityScheme: "ApiKey",
    type: "apiKey",
    in: "header",
    name: "X-API-Key",
    description: "Clé API générée via /api/v1/applications/{app_id}/api-keys"
)]
class OpenApi extends Controller
{
}

#[OA\Schema(
    schema: "Organization",
    required: ["name", "email"],
    properties: [
        new OA\Property(property: "name", type: "string", example: "Mon Entreprise"),
        new OA\Property(property: "email", type: "string", format: "email", example: "admin@exemple.com"),
        new OA\Property(property: "phone", type: "string", example: "+241 01 23 45 67"),
        new OA\Property(property: "address", type: "string", example: "Libreville, Gabon")
    ]
)]
class OrganizationSchema {}

#[OA\Schema(
    schema: "Admin",
    required: ["first_name", "last_name", "email"],
    properties: [
        new OA\Property(property: "first_name", type: "string", example: "Jean"),
        new OA\Property(property: "last_name", type: "string", example: "Dupont"),
        new OA\Property(property: "email", type: "string", format: "email", example: "j.dupont@exemple.com")
    ]
)]
class AdminSchema {}
