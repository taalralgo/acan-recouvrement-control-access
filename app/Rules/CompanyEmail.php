<?php

declare(strict_types=1);

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * N'autorise que les adresses professionnelles.
 *
 * Un compte ouvert sur une adresse personnelle survivrait au départ de son
 * titulaire : c'est précisément ce que la gestion des comptes doit empêcher.
 */
final class CompanyEmail implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $domain = config('blockaccess.email_domain');

        if (!is_string($value) || !str_ends_with(strtolower($value), '@' . strtolower($domain)))
        {
            $fail("L'adresse doit appartenir au domaine @{$domain}.");
        }
    }
}
