<?php

declare(strict_types=1);

namespace App\Models;

use App\Saas\RemoteGroupe;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Copie locale d'un groupe tel qu'il existe sur sa plateforme.
 *
 * `Groupe` est le vocabulaire commun à tous nos SAAS ; l'interface, elle,
 * parle d'« Entreprises », le mot qu'emploie l'équipe de recouvrement.
 */
#[Fillable([
    'platform_id',
    'external_id',
    'code',
    'name',
    'lang',
    'users_count',
    'is_blocked',
    'blocked_at',
    'block_reason',
    'platform_enabled',
    'synced_at',
])]
class Groupe extends Model
{
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_blocked' => 'boolean',
            'platform_enabled' => 'boolean',
            'blocked_at' => 'datetime',
            'synced_at' => 'datetime',
        ];
    }

    public function platform(): BelongsTo
    {
        return $this->belongsTo(SaasPlatform::class, 'platform_id');
    }

    public function actions(): HasMany
    {
        return $this->hasMany(BlockAction::class)->latest('created_at');
    }

    /**
     * Aligne la copie locale sur ce que dit la plateforme.
     *
     * Unique point d'écriture de l'état de blocage : il n'est jamais déduit de
     * ce que l'équipe a demandé, toujours de ce que la plateforme a confirmé.
     */
    public function applyRemote(RemoteGroupe $remote): void
    {
        $this->fill([
            'code' => $remote->code,
            'name' => $remote->name,
            'lang' => $remote->lang,
            'users_count' => $remote->usersCount,
            'platform_enabled' => $remote->platformEnabled,
            'is_blocked' => $remote->isBlocked,
            'blocked_at' => $remote->blockedAt,
            'block_reason' => $remote->blockReason,
            'synced_at' => now(),
        ])->save();
    }

    /**
     * Le groupe est-il inaccessible pour une raison indépendante du
     * recouvrement ? Sans cette distinction à l'écran, un agent débloque, le
     * client reste dehors, et appelle le support en disant que ça ne marche pas.
     */
    public function isDisabledByPlatform(): bool
    {
        return !$this->platform_enabled;
    }

    /**
     * Les informations affichées datent-elles trop pour être présentées sans
     * réserve ? La donnée vient d'un autre serveur, jamais en temps réel.
     */
    public function isStale(): bool
    {
        if ($this->synced_at === null)
        {
            return true;
        }

        return $this->synced_at->lt(now()->subMinutes(config('regie.stale_after_minutes')));
    }
}
