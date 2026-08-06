<?php

declare(strict_types=1);

namespace App\Saas;

use App\Models\SaasPlatform;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;

/**
 * Parle le contrat REST commun à toutes nos plateformes.
 *
 * Aucun retry sur block/unblock : les plateformes traitent ces appels de façon
 * idempotente, mais réessayer automatiquement masquerait un problème réseau à
 * une équipe qui doit savoir si son action a abouti. Un nouvel essai reste
 * possible manuellement, sans danger.
 */
final class HttpSaasConnector implements SaasConnector
{
    public function fetchGroupes(SaasPlatform $platform): Collection
    {
        $response = $this->send($platform, 'get', 'groupes');

        return collect($response->json('data', []))
            ->map(fn (array $payload): RemoteGroupe => RemoteGroupe::fromPayload($payload));
    }

    public function block(SaasPlatform $platform, int $externalId, string $reason, string $actor): RemoteGroupe
    {
        $response = $this->send($platform, 'post', "groupes/{$externalId}/block", [
            'reason' => $reason,
            'actor' => $actor,
        ]);

        return RemoteGroupe::fromPayload($response->json('data', []));
    }

    public function unblock(SaasPlatform $platform, int $externalId, string $actor): RemoteGroupe
    {
        $response = $this->send($platform, 'post', "groupes/{$externalId}/unblock", [
            'actor' => $actor,
        ]);

        return RemoteGroupe::fromPayload($response->json('data', []));
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @throws SaasUnreachable
     */
    private function send(SaasPlatform $platform, string $method, string $path, array $payload = []): Response
    {
        try
        {
            $response = Http::withToken($platform->api_token)
                ->acceptJson()
                ->timeout(config('regie.http.timeout'))
                ->connectTimeout(config('regie.http.connect_timeout'))
                ->{$method}($platform->endpoint($path), $payload);
        }
        catch (ConnectionException $exception)
        {
            throw SaasUnreachable::of(SaasFailure::Unreachable, $exception);
        }

        if ($response->successful())
        {
            return $response;
        }

        throw SaasUnreachable::of(match ($response->status()) {
            401, 403 => SaasFailure::Unauthorized,
            404 => SaasFailure::NotFound,
            429 => SaasFailure::RateLimited,
            default => SaasFailure::Rejected,
        });
    }
}
