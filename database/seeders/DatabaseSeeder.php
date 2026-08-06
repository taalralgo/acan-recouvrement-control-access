<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        // Aucun compte n'est créé ici : le premier administrateur passe par
        // `php artisan blockaccess:create-admin`, seul moyen d'obtenir un mot
        // de passe temporaire affiché une fois.
        $this->call(BlockReasonTemplateSeeder::class);
    }
}
