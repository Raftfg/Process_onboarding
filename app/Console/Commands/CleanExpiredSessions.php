<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CleanExpiredSessions extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sessions:clean {--force : Force le nettoyage sans confirmation}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Nettoie les sessions expirées de la base de données';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $lifetime = config('session.lifetime', 120); // en minutes
        $expiredTime = now()->subMinutes($lifetime)->timestamp;

        $this->info("Nettoyage des sessions expirées (durée de vie: {$lifetime} minutes)...");

        // Compter les sessions expirées
        $expiredCount = DB::table('sessions')
            ->where('last_activity', '<', $expiredTime)
            ->count();

        if ($expiredCount === 0) {
            $this->info('Aucune session expirée à nettoyer.');
            return 0;
        }

        if (!$this->option('force')) {
            if (!$this->confirm("Voulez-vous supprimer {$expiredCount} session(s) expirée(s) ?", true)) {
                $this->info('Opération annulée.');
                return 0;
            }
        }

        // Supprimer les sessions expirées
        $deleted = DB::table('sessions')
            ->where('last_activity', '<', $expiredTime)
            ->delete();

        $this->info("✓ {$deleted} session(s) expirée(s) supprimée(s) avec succès.");

        return 0;
    }
}
