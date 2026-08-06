<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * Membre de l'équipe interne.
 *
 * Deux rôles seulement : un `collector` suspend et rétablit les accès, un
 * `admin` fait de même et gère en plus les comptes, les plateformes et les
 * modèles de motifs. Toute action reste nominative via le journal.
 */
#[Fillable(['name', 'email', 'password', 'role', 'must_change_password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    public const ROLE_ADMIN = 'admin';

    public const ROLE_COLLECTOR = 'collector';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'must_change_password' => 'boolean',
        ];
    }

    public function isAdmin(): bool
    {
        return $this->role === self::ROLE_ADMIN;
    }

    /**
     * Dernier administrateur en poste ?
     *
     * Le supprimer ou le rétrograder laisserait l'équipe sans personne pour
     * gérer les comptes et les plateformes, sans recours depuis l'interface.
     */
    public function isLastAdmin(): bool
    {
        return $this->isAdmin()
            && self::query()->where('role', self::ROLE_ADMIN)->count() <= 1;
    }

    /**
     * Instantané conservé dans le journal, pour que l'historique reste lisible
     * après la suppression du compte.
     *
     * @return array{actor_name: string, actor_email: string}
     */
    public function toActorSnapshot(): array
    {
        return [
            'actor_name' => $this->name,
            'actor_email' => $this->email,
        ];
    }
}
