<?php

namespace App\Services;

use App\Models\Webhook;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class WebhookService
{
    /**
     * Déclencher un webhook pour un événement
     */
    public function trigger(string $event, array $data): void
    {
        try {
            // Vérifier si la table webhooks existe avant d'essayer d'y accéder
            if (!Schema::hasTable('webhooks')) {
                Log::debug('Table webhooks n\'existe pas, webhooks ignorés', [
                    'event' => $event
                ]);
                return;
            }

            // Récupérer tous les webhooks actifs pour cet événement
            $webhooks = Webhook::where('is_active', true)
                ->get()
                ->filter(function ($webhook) use ($event) {
                    return $webhook->shouldTrigger($event);
                });

            foreach ($webhooks as $webhook) {
                $this->sendWebhook($webhook, $event, $data);
            }
        } catch (\Exception $e) {
            // Logger l'erreur mais ne pas faire échouer le processus d'onboarding
            Log::warning('Erreur lors du déclenchement des webhooks', [
                'event' => $event,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            // Ne pas propager l'exception pour ne pas interrompre l'onboarding
        }
    }

    /**
     * Envoyer un webhook
     */
    protected function sendWebhook(Webhook $webhook, string $event, array $data): void
    {
        $payload = [
            'event' => $event,
            'timestamp' => now()->toIso8601String(),
            'data' => $data,
        ];

        // Générer la signature
        $signature = $webhook->generateSignature($payload);

        try {
            $response = Http::timeout($webhook->timeout)
                ->withHeaders([
                    'X-Webhook-Signature' => $signature,
                    'X-Webhook-Event' => $event,
                    'Content-Type' => 'application/json',
                ])
                ->post($webhook->url, $payload);

            // Mettre à jour la dernière exécution
            $webhook->update(['last_triggered_at' => now()]);

            if ($response->successful()) {
                Log::info("Webhook envoyé avec succès", [
                    'webhook_id' => $webhook->id,
                    'url' => $webhook->url,
                    'event' => $event,
                ]);
            } else {
                Log::warning("Webhook échoué", [
                    'webhook_id' => $webhook->id,
                    'url' => $webhook->url,
                    'event' => $event,
                    'status' => $response->status(),
                ]);
            }
        } catch (\Exception $e) {
            Log::error("Erreur lors de l'envoi du webhook", [
                'webhook_id' => $webhook->id,
                'url' => $webhook->url,
                'event' => $event,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Créer un nouveau webhook
     */
    public function create(array $data): Webhook
    {
        try {
            // Vérifier si la table webhooks existe avant d'essayer d'y accéder
            if (!Schema::hasTable('webhooks')) {
                throw new \Exception('La table webhooks n\'existe pas. Veuillez exécuter les migrations.');
            }

            return Webhook::create([
                'api_key_id' => $data['api_key_id'] ?? null,
                'url' => $data['url'],
                'events' => $data['events'] ?? [],
                'is_active' => $data['is_active'] ?? true,
                'secret' => $data['secret'] ?? Str::random(32),
                'timeout' => $data['timeout'] ?? 30,
                'retry_attempts' => $data['retry_attempts'] ?? 3,
            ]);
        } catch (\Exception $e) {
            Log::error('Erreur lors de la création du webhook', [
                'error' => $e->getMessage(),
                'data' => $data
            ]);
            // Propager l'exception pour create() car c'est une opération explicite
            throw $e;
        }
    }
}
