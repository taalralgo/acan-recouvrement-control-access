<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * L'application peut être servie depuis un sous-dossier (https://…/regie).
 * Le préfixe doit atteindre la SPA, sinon sa navigation et ses appels visent
 * la racine du domaine.
 */
class SubfolderDeploymentTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_spa_receives_the_subfolder_prefix(): void
    {
        config()->set('app.url', 'https://dev.acan.group/regie');

        $this->actingAs(User::factory()->create())
            ->get('/')
            ->assertOk()
            ->assertSee('data-base-path="/regie"', false);
    }

    public function test_the_prefix_is_empty_at_the_domain_root(): void
    {
        config()->set('app.url', 'https://regie.acan.group');

        $this->actingAs(User::factory()->create())
            ->get('/')
            ->assertOk()
            ->assertSee('data-base-path=""', false);
    }

    public function test_a_trailing_slash_does_not_double_the_separator(): void
    {
        config()->set('app.url', 'https://dev.acan.group/regie/');

        $this->actingAs(User::factory()->create())
            ->get('/')
            ->assertOk()
            ->assertSee('data-base-path="/regie"', false);
    }
}
