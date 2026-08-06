<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Plateforme raccordée (TVe, puis les suivantes).
 *
 * base_url est modifiable depuis l'interface : l'infrastructure est gérée par
 * une équipe extérieure qui déplace les projets en cas d'incident, et l'URL
 * d'un SAAS n'est donc pas une constante.
 */
#[Fillable(['name', 'base_url', 'api_token', 'active'])]
#[Hidden(['api_token'])]
class SaasPlatform extends Model
{
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            // Chiffré au repos : une copie de la base ne livre pas de quoi
            // couper l'accès des clients de toutes les plateformes.
            'api_token' => 'encrypted',
            'active' => 'boolean',
            'last_reachable_at' => 'datetime',
        ];
    }

    public function groupes(): HasMany
    {
        return $this->hasMany(Groupe::class, 'platform_id');
    }

    public function endpoint(string $path): string
    {
        return rtrim($this->base_url, '/') . '/api/access/' . ltrim($path, '/');
    }
}
