<?php

declare(strict_types=1);

namespace App\Saas;

/**
 * Un groupe tel que le décrit une plateforme.
 *
 * Traduit la réponse HTTP en une forme stable, pour que le reste de
 * l'application ne dépende jamais du format brut d'un SAAS.
 */
final readonly class RemoteGroupe
{
    public function __construct(
        public int $externalId,
        public ?string $code,
        public string $name,
        public string $lang,
        public int $usersCount,
        public bool $platformEnabled,
        public bool $isBlocked,
        public ?string $blockedAt,
        public ?string $blockReason,
    ) {
    }

    /**
     * @param array<string, mixed> $payload
     */
    public static function fromPayload(array $payload): self
    {
        return new self(
            externalId: (int) $payload['id'],
            code: $payload['code'] ?? null,
            name: (string) ($payload['name'] ?? ''),
            lang: (string) ($payload['lang'] ?? 'fr'),
            usersCount: (int) ($payload['users_count'] ?? 0),
            platformEnabled: (bool) ($payload['enabled'] ?? true),
            isBlocked: ($payload['access_blocked_at'] ?? null) !== null,
            blockedAt: $payload['access_blocked_at'] ?? null,
            blockReason: $payload['access_block_reason'] ?? null,
        );
    }
}
