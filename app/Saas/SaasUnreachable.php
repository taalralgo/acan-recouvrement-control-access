<?php

declare(strict_types=1);

namespace App\Saas;

use RuntimeException;
use Throwable;

/**
 * Une opération n'a pas abouti sur la plateforme.
 *
 * Tant que cette exception est levée, rien n'a été confirmé côté SAAS : l'état
 * local ne doit surtout pas être modifié. Croire un client suspendu alors qu'il
 * ne l'est pas est le scénario le plus dangereux du système.
 */
class SaasUnreachable extends RuntimeException
{
    public function __construct(
        public readonly SaasFailure $failure,
        string $message = '',
        ?Throwable $previous = null,
    ) {
        parent::__construct($message !== '' ? $message : $failure->message(), 0, $previous);
    }

    public static function of(SaasFailure $failure, ?Throwable $previous = null): self
    {
        return new self($failure, previous: $previous);
    }

    /**
     * Message destiné à l'équipe : jamais une trace technique.
     */
    public function forHumans(): string
    {
        return $this->failure->message();
    }
}
