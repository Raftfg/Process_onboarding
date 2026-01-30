<?php

namespace App\Console\Commands;

use App\Models\ApiKey;
use Illuminate\Console\Command;

class GenerateApiKey extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'api:generate-key 
                            {name : Nom de la clé API}
                            {--expires= : Date d\'expiration (format: Y-m-d H:i:s)}
                            {--limit=100 : Limite de requêtes par minute}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Génère une nouvelle clé API pour l\'intégration externe';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $name = $this->argument('name');
        $expiresAt = $this->option('expires') ? \Carbon\Carbon::parse($this->option('expires')) : null;
        $rateLimit = (int) $this->option('limit');

        $result = ApiKey::generate($name, [
            'expires_at' => $expiresAt,
            'rate_limit' => $rateLimit,
        ]);

        $this->info('Clé API générée avec succès !');
        $this->newLine();
        $this->line('ID: ' . $result['id']);
        $this->line('Nom: ' . $result['name']);
        $this->line('Préfixe: ' . $result['key_prefix']);
        $this->newLine();
        $this->warn('⚠️  IMPORTANT: Sauvegardez cette clé immédiatement, elle ne sera plus affichée !');
        $this->newLine();
        $this->line('Clé API: ' . $result['key']);
        $this->newLine();
        $this->info('Utilisez cette clé dans vos requêtes avec le header:');
        $this->line('Authorization: Bearer ' . $result['key']);

        return Command::SUCCESS;
    }
}
