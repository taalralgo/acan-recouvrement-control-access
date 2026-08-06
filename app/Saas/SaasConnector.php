<?php

declare(strict_types=1);

namespace App\Saas;

use App\Models\SaasPlatform;
use Illuminate\Support\Collection;

/**
 * Ce qu'aCAN Régie attend d'une plateforme raccordée.
 *
 * Une seule implémentation existe aujourd'hui, HttpSaasConnector : tant qu'un
 * SAAS respecte le contrat REST, il n'y a pas de connecteur à écrire. Cette
 * interface est là pour le jour où une plateforme ne pourra pas l'exposer.
 */
interface SaasConnector
{
    /**
     * @return Collection<int, RemoteGroupe>
     *
     * @throws SaasUnreachable
     */
    public function fetchGroupes(SaasPlatform $platform): Collection;

    /**
     * @throws SaasUnreachable
     */
    public function block(SaasPlatform $platform, int $externalId, string $reason, string $actor): RemoteGroupe;

    /**
     * @throws SaasUnreachable
     */
    public function unblock(SaasPlatform $platform, int $externalId, string $actor): RemoteGroupe;
}
