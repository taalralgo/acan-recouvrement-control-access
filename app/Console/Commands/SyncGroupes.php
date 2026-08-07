<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\GroupeSynchronizer;
use App\Services\SyncResult;
use Illuminate\Console\Command;

class SyncGroupes extends Command
{
    protected $signature = 'regie:sync';

    protected $description = 'Rafraîchit la liste des groupes depuis les plateformes raccordées';

    public function handle(GroupeSynchronizer $synchronizer): int
    {
        $results = $synchronizer->syncAll();

        if ($results->isEmpty())
        {
            $this->warn('Aucune plateforme active.');

            return self::SUCCESS;
        }

        $this->table(
            ['Plateforme', 'Résultat'],
            $results->map(fn (SyncResult $result): array => [
                $result->platform->name,
                ($result->success ? '✓ ' : '✗ ') . $result->message(),
            ])->all()
        );

        // Une plateforme injoignable ne fait pas échouer les autres, mais le
        // code de sortie doit le signaler pour qu'un cron puisse alerter.
        return $results->every(fn (SyncResult $result): bool => $result->success)
            ? self::SUCCESS
            : self::FAILURE;
    }
}
