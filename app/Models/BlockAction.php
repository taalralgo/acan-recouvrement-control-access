<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Une décision inscrite au journal. Écrit une fois, jamais modifié.
 *
 * Les champs recopiés (nom du groupe, nom et email de l'agent) ne sont pas une
 * duplication accidentelle : ce sont des instantanés, seule façon de relire
 * l'historique après la suppression d'un compte ou la disparition d'un groupe.
 */
#[Fillable([
    'groupe_id',
    'groupe_name',
    'platform_name',
    'action',
    'reason',
    'actor_name',
    'actor_email',
])]
class BlockAction extends Model
{
    use HasFactory;

    public const ACTION_BLOCK = 'block';

    public const ACTION_UNBLOCK = 'unblock';

    public const UPDATED_AT = null;

    public function groupe(): BelongsTo
    {
        return $this->belongsTo(Groupe::class);
    }

    /**
     * Inscrit une décision au journal.
     */
    public static function record(string $action, Groupe $groupe, User $actor, ?string $reason = null): self
    {
        return self::create([
            'groupe_id' => $groupe->id,
            'groupe_name' => $groupe->name,
            'platform_name' => $groupe->platform?->name ?? '—',
            'action' => $action,
            'reason' => $reason,
            ...$actor->toActorSnapshot(),
        ]);
    }
}
