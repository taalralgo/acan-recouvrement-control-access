<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Groupe;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Groupe
 */
class GroupeResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'code' => $this->code,
            'lang' => $this->lang,
            'platform' => $this->platform->name,
            'users_count' => $this->users_count,
            'is_blocked' => $this->is_blocked,
            'blocked_at' => $this->blocked_at?->toIso8601String(),
            'block_reason' => $this->block_reason,
            // Second verrou, décidé par les admins de la plateforme. Exposé
            // séparément : sans lui, un déblocage peut sembler sans effet.
            'disabled_by_platform' => $this->isDisabledByPlatform(),
            'synced_at' => $this->synced_at?->toIso8601String(),
            'is_stale' => $this->isStale(),
        ];
    }
}
