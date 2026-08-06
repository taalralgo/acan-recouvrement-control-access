<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\BlockAction;
use App\Models\Groupe;
use App\Models\User;
use App\Saas\SaasConnector;
use App\Saas\SaasUnreachable;

/**
 * Suspend et rétablit l'accès d'un groupe sur sa plateforme.
 *
 * Règle centrale : l'état local n'est écrit qu'après confirmation de la
 * plateforme. Si l'appel échoue, l'exception remonte et rien n'est enregistré
 * — ni le blocage, ni le journal. Afficher un client comme suspendu alors qu'il
 * ne l'est pas (ou l'inverse) est le pire défaut possible pour cet outil.
 */
final readonly class AccessSuspender
{
    public function __construct(private SaasConnector $connector)
    {
    }

    /**
     * @throws SaasUnreachable
     */
    public function block(Groupe $groupe, User $actor, string $reason): Groupe
    {
        $groupe->loadMissing('platform');

        $remote = $this->connector->block(
            $groupe->platform,
            $groupe->external_id,
            $reason,
            $actor->email,
        );

        $groupe->applyRemote($remote);

        BlockAction::record(BlockAction::ACTION_BLOCK, $groupe, $actor, $reason);

        return $groupe;
    }

    /**
     * @throws SaasUnreachable
     */
    public function unblock(Groupe $groupe, User $actor): Groupe
    {
        $groupe->loadMissing('platform');

        $remote = $this->connector->unblock(
            $groupe->platform,
            $groupe->external_id,
            $actor->email,
        );

        $groupe->applyRemote($remote);

        BlockAction::record(BlockAction::ACTION_UNBLOCK, $groupe, $actor);

        return $groupe;
    }
}
