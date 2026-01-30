<?php

namespace App\Console\Commands;

use App\Models\ApiKey;
use Illuminate\Console\Command;

class ListApiKeys extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'api:list-keys';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Liste toutes les clés API';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $keys = ApiKey::all();

        if ($keys->isEmpty()) {
            $this->info('Aucune clé API trouvée.');
            return Command::SUCCESS;
        }

        $headers = ['ID', 'Nom', 'Préfixe', 'Active', 'Expire le', 'Dernière utilisation', 'Limite/min'];
        $rows = [];

        foreach ($keys as $key) {
            $rows[] = [
                $key->id,
                $key->name,
                $key->key_prefix,
                $key->is_active ? '✓' : '✗',
                $key->expires_at ? $key->expires_at->format('Y-m-d H:i') : 'Jamais',
                $key->last_used_at ? $key->last_used_at->format('Y-m-d H:i') : 'Jamais',
                $key->rate_limit,
            ];
        }

        $this->table($headers, $rows);

        return Command::SUCCESS;
    }
}
