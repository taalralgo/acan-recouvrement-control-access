<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\User;
use App\Support\TemporaryPassword;
use Illuminate\Console\Command;

/**
 * Redonne l'accès à un compte depuis le serveur.
 *
 * Aucun email n'étant envoyé, un membre de l'équipe dépend d'un administrateur
 * pour retrouver son mot de passe. Cette commande couvre le cas où il n'y a
 * personne pour le faire : administrateur unique enfermé dehors, ou équipe
 * indisponible.
 */
class ResetPassword extends Command
{
    protected $signature = 'regie:reset-password {email : Adresse du compte à dépanner}';

    protected $description = "Génère un mot de passe temporaire pour un compte et l'affiche";

    public function handle(): int
    {
        $email = (string) $this->argument('email');
        $user = User::firstWhere('email', $email);

        if ($user === null)
        {
            $this->error("Aucun compte pour {$email}.");

            return self::FAILURE;
        }

        $password = TemporaryPassword::generate();

        $user->forceFill([
            'password' => $password,
            'must_change_password' => true,
        ])->save();

        $this->info("Mot de passe temporaire pour {$user->name} :");
        $this->newLine();
        $this->line("  Email        : {$user->email}");
        $this->line("  Mot de passe : {$password}");
        $this->newLine();
        $this->warn('Il devra être remplacé à la prochaine connexion.');

        return self::SUCCESS;
    }
}
