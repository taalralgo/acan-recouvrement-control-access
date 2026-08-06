<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\BlockAction;
use App\Models\Groupe;
use App\Models\SaasPlatform;
use App\Models\User;
use App\Services\AccessSuspender;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TeamAdministrationTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->admin()->create();
    }

    public function test_a_collector_cannot_reach_team_administration(): void
    {
        $collector = User::factory()->create();

        $this->actingAs($collector)->getJson('/api/team')->assertForbidden();
        $this->actingAs($collector)->postJson('/api/team', [])->assertForbidden();
    }

    public function test_creating_an_account_returns_a_temporary_password_once(): void
    {
        $response = $this->actingAs($this->admin())
            ->postJson('/api/team', [
                'name' => 'Nouvelle Recrue',
                'email' => 'recrue@acan.email',
                'role' => User::ROLE_COLLECTOR,
            ])
            ->assertCreated();

        $password = $response->json('temporary_password');

        $this->assertNotEmpty($password);

        $created = User::firstWhere('email', 'recrue@acan.email');

        $this->assertTrue(Hash::check($password, $created->password));
        // Le mot de passe est connu de l'admin qui l'a transmis : il doit être
        // remplacé pour que le journal nominatif ait un sens.
        $this->assertTrue($created->must_change_password);

        // Il n'est réaffiché nulle part ensuite.
        $this->actingAs($this->admin())
            ->getJson('/api/team')
            ->assertOk()
            ->assertJsonMissing(['temporary_password' => $password]);
    }

    public function test_an_account_outside_the_company_domain_is_refused(): void
    {
        $this->actingAs($this->admin())
            ->postJson('/api/team', [
                'name' => 'Adresse Perso',
                'email' => 'quelquun@gmail.com',
                'role' => User::ROLE_COLLECTOR,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('email');

        $this->assertDatabaseCount('users', 1);
    }

    public function test_an_admin_can_delete_another_admin_when_others_remain(): void
    {
        $admin = $this->admin();
        $other = $this->admin();

        $this->actingAs($admin)->deleteJson("/api/team/{$other->id}")->assertOk();

        $this->assertDatabaseMissing('users', ['id' => $other->id]);
    }

    /**
     * La suppression du dernier administrateur n'est pas atteignable par
     * l'API : seul un administrateur peut supprimer, donc si la cible est un
     * autre administrateur il en reste forcément un, et l'auto-suppression est
     * refusée en amont. La règle est vérifiée ici au niveau du modèle, où elle
     * sert de garde-fou à la rétrogradation.
     */
    public function test_the_last_admin_is_identified_as_such(): void
    {
        $admin = $this->admin();
        User::factory()->create();

        $this->assertTrue($admin->isLastAdmin());

        $second = User::factory()->admin()->create();

        $this->assertFalse($admin->refresh()->isLastAdmin());
        $this->assertFalse($second->isLastAdmin());
    }

    public function test_the_last_admin_cannot_be_demoted(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->putJson("/api/team/{$admin->id}", [
                'name' => $admin->name,
                'email' => $admin->email,
                'role' => User::ROLE_COLLECTOR,
            ])
            ->assertStatus(409);

        $this->assertTrue($admin->refresh()->isAdmin());
    }

    public function test_an_admin_cannot_delete_their_own_account(): void
    {
        $admin = $this->admin();
        User::factory()->admin()->create();

        // Techniquement possible puisqu'un autre admin existe, mais cela
        // déconnecterait la personne en pleine action sans bénéfice.
        $this->actingAs($admin)->deleteJson("/api/team/{$admin->id}")->assertStatus(409);

        $this->assertDatabaseHas('users', ['id' => $admin->id]);
    }

    public function test_deleting_an_account_preserves_its_decisions_in_the_journal(): void
    {
        $admin = $this->admin();
        $agent = User::factory()->create(['name' => 'Agent Parti', 'email' => 'parti@acan.email']);

        $platform = SaasPlatform::factory()->create(['base_url' => 'https://tve.test', 'name' => 'TVe']);
        $groupe = Groupe::factory()->create([
            'platform_id' => $platform->id,
            'external_id' => 7,
            'name' => 'Groupe Alpha',
        ]);

        Http::fake(['tve.test/*' => Http::response(['data' => [
            'id' => 7, 'code' => 'GRP1', 'name' => 'Groupe Alpha', 'lang' => 'fr',
            'users_count' => 4, 'enabled' => true,
            'access_blocked_at' => '2026-08-06T09:00:00+00:00',
            'access_block_reason' => 'Facture impayée.',
        ]])]);

        app(AccessSuspender::class)->block($groupe, $agent, 'Facture impayée.');

        $this->actingAs($admin)->deleteJson("/api/team/{$agent->id}")->assertOk();

        // Le cas qui justifie les instantanés : répondre à un client en litige
        // des mois après le départ de l'agent.
        $action = BlockAction::first();

        $this->assertSame('Agent Parti', $action->actor_name);
        $this->assertSame('parti@acan.email', $action->actor_email);
    }

    /**
     * Ce bouton dépanne un collègue. Appliqué à soi-même, il repose
     * `must_change_password` et referme l'accès dans la seconde : un
     * administrateur unique se retrouverait enfermé dehors, sans recours si la
     * fenêtre affichant le mot de passe a été fermée trop vite.
     */
    public function test_an_admin_cannot_reset_their_own_password(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->postJson("/api/team/{$admin->id}/reset-password")
            ->assertStatus(409);

        $admin->refresh();

        $this->assertFalse($admin->must_change_password);
        $this->assertTrue(Hash::check('password', $admin->password));
    }

    public function test_a_locked_out_account_can_be_rescued_from_the_console(): void
    {
        $admin = $this->admin();

        $this->artisan('regie:reset-password', ['email' => $admin->email])
            ->assertSuccessful();

        $this->assertTrue($admin->refresh()->must_change_password);
    }

    public function test_the_rescue_command_reports_an_unknown_account(): void
    {
        $this->artisan('regie:reset-password', ['email' => 'inconnu@acan.email'])
            ->assertFailed();
    }

    public function test_an_admin_can_issue_a_new_temporary_password(): void
    {
        $admin = $this->admin();
        $agent = User::factory()->create();

        // Il n'y a pas de « mot de passe oublié » en autonomie.
        $response = $this->actingAs($admin)
            ->postJson("/api/team/{$agent->id}/reset-password")
            ->assertOk();

        $password = $response->json('temporary_password');

        $agent->refresh();

        $this->assertTrue(Hash::check($password, $agent->password));
        $this->assertTrue($agent->must_change_password);
    }
}
