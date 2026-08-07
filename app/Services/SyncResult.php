<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\SaasPlatform;
use App\Saas\SaasUnreachable;

/**
 * Issue de la synchronisation d'une plateforme.
 *
 * Un échec est une information à afficher, pas une exception à propager : les
 * autres plateformes doivent continuer d'être synchronisées.
 */
final readonly class SyncResult
{
    private function __construct(
        public SaasPlatform $platform,
        public bool $success,
        public int $synced = 0,
        public int $removed = 0,
        public ?SaasUnreachable $error = null,
    ) {
    }

    public static function succeeded(SaasPlatform $platform, int $synced, int $removed): self
    {
        return new self($platform, true, $synced, $removed);
    }

    public static function failed(SaasPlatform $platform, SaasUnreachable $error): self
    {
        return new self($platform, false, error: $error);
    }

    public function message(): string
    {
        if (!$this->success)
        {
            return $this->error?->forHumans() ?? 'Échec inconnu.';
        }

        return sprintf('%d groupe(s) à jour, %d retiré(s).', $this->synced, $this->removed);
    }
}
