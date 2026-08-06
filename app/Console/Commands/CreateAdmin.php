<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\User;
use App\Rules\CompanyEmail;
use App\Support\TemporaryPassword;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/**
 * Crée le premier administrateur.
 *
 * Sans cette commande, personne ne pourrait créer le premier compte : les
 * comptes suivants se créent depuis l'interface.
 */
class CreateAdmin extends Command
{
    protected $signature = 'regie:create-admin
                            {--name= : Nom affiché}
                            {--email= : Adresse professionnelle}';

    protected $description = "Crée un compte administrateur et affiche son mot de passe temporaire";

    public function handle(): int
    {
        $name = $this->option('name') ?: $this->ask('Nom complet');
        $email = $this->option('email') ?: $this->ask('Adresse email');

        $validator = Validator::make(
            ['name' => $name, 'email' => $email],
            [
                'name' => ['required', 'string', 'max:120'],
                'email' => [
                    'required',
                    'email',
                    Rule::unique('users', 'email'),
                    new CompanyEmail(),
                ],
            ]
        );

        if ($validator->fails())
        {
            foreach ($validator->errors()->all() as $error)
            {
                $this->error($error);
            }

            return self::FAILURE;
        }

        $password = TemporaryPassword::generate();

        User::create([
            'name' => $name,
            'email' => $email,
            'password' => $password,
            'role' => User::ROLE_ADMIN,
            'must_change_password' => true,
        ]);

        $this->info('Compte administrateur créé.');
        $this->newLine();
        $this->line("  Email             : {$email}");
        $this->line("  Mot de passe      : {$password}");
        $this->newLine();
        $this->warn('Ce mot de passe ne sera plus affiché. Il devra être changé à la première connexion.');

        return self::SUCCESS;
    }
}
