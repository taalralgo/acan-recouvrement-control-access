<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Groupe;
use App\Models\SaasPlatform;
use App\Saas\RemoteGroupe;
use App\Saas\SaasConnector;
use App\Saas\SaasUnreachable;
use Illuminate\Support\Collection;

/**
 * Rafraîchit le miroir local à partir des plateformes.
 *
 * Chaque plateforme est traitée indépendamment : l'une injoignable ne doit pas
 * priver l'équipe des informations des autres.
 */
final readonly class GroupeSynchronizer
{
    public function __construct(private SaasConnector $connector)
    {
    }

    /**
     * @return Collection<int, SyncResult>
     */
    public function syncAll(): Collection
    {
        return SaasPlatform::query()
            ->where('active', true)
            ->get()
            ->map(fn (SaasPlatform $platform): SyncResult => $this->sync($platform));
    }

    public function sync(SaasPlatform $platform): SyncResult
    {
        try
        {
            $remotes = $this->connector->fetchGroupes($platform);
        }
        catch (SaasUnreachable $exception)
        {
            // La copie existante reste en place et continue d'être affichée,
            // signalée comme datée. Mieux vaut une liste d'hier qu'un écran vide.
            return SyncResult::failed($platform, $exception);
        }

        $seen = $remotes->map(fn (RemoteGroupe $remote): int => $remote->externalId)->all();

        foreach ($remotes as $remote)
        {
            $groupe = Groupe::firstOrNew([
                'platform_id' => $platform->id,
                'external_id' => $remote->externalId,
            ]);

            $groupe->applyRemote($remote);
        }

        // Groupes disparus côté plateforme : retirés du miroir. Le journal
        // reste lisible grâce aux instantanés qu'il conserve.
        $removed = Groupe::query()
            ->where('platform_id', $platform->id)
            ->when($seen !== [], fn ($query) => $query->whereNotIn('external_id', $seen))
            ->delete();

        $platform->forceFill(['last_reachable_at' => now()])->save();

        return SyncResult::succeeded($platform, $remotes->count(), $removed);
    }
}
