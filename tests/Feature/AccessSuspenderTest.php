<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\BlockAction;
use App\Models\Groupe;
use App\Models\SaasPlatform;
use App\Models\User;
use App\Saas\SaasFailure;
use App\Saas\SaasUnreachable;
use App\Services\AccessSuspender;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * La règle la plus sensible de l'outil : l'état local ne reflète que ce que la
 * plateforme a confirmé, jamais ce que l'équipe a demandé.
 */
class AccessSuspenderTest extends TestCase
{
    use RefreshDatabase;

    private function groupe(array $overrides = []): Groupe
    {
        $platform = SaasPlatform::factory()->create([
            'base_url' => 'https://tve.test',
            'name' => 'TVe',
        ]);

        return Groupe::factory()->create(array_merge([
            'platform_id' => $platform->id,
            'external_id' => 7,
            'name' => 'Groupe Alpha',
        ], $overrides));
    }

    private function remoteResponse(array $overrides = []): array
    {
        return ['data' => array_merge([
            'id' => 7,
            'code' => 'GRP1',
            'name' => 'Groupe Alpha',
            'lang' => 'fr',
            'users_count' => 12,
            'enabled' => true,
            'access_blocked_at' => '2026-08-06T09:00:00+00:00',
            'access_block_reason' => 'Facture impayée depuis 3 mois.',
        ], $overrides)];
    }

    public function test_a_confirmed_suspension_updates_the_mirror_and_the_journal(): void
    {
        $groupe = $this->groupe();
        $actor = User::factory()->create(['name' => 'Agent Test', 'email' => 'agent@acan.email']);

        Http::fake([
            'tve.test/api/access/groupes/7/block' => Http::response($this->remoteResponse()),
        ]);

        app(AccessSuspender::class)->block($groupe, $actor, 'Facture impayée depuis 3 mois.');

        $groupe->refresh();

        $this->assertTrue($groupe->is_blocked);
        $this->assertSame('Facture impayée depuis 3 mois.', $groupe->block_reason);

        $this->assertDatabaseHas('block_actions', [
            'groupe_id' => $groupe->id,
            'action' => BlockAction::ACTION_BLOCK,
            'actor_email' => 'agent@acan.email',
            'reason' => 'Facture impayée depuis 3 mois.',
        ]);
    }

    public function test_a_failed_call_leaves_the_mirror_and_the_journal_untouched(): void
    {
        $groupe = $this->groupe();
        $actor = User::factory()->create();

        Http::fake(['tve.test/*' => Http::response('', 500)]);

        try
        {
            app(AccessSuspender::class)->block($groupe, $actor, 'Facture impayée.');
            $this->fail('Une plateforme en échec doit interrompre la suspension.');
        }
        catch (SaasUnreachable $exception)
        {
            $this->assertSame(SaasFailure::Rejected, $exception->failure);
        }

        // Rien ne doit laisser croire que ce client est suspendu.
        $this->assertFalse($groupe->refresh()->is_blocked);
        $this->assertDatabaseCount('block_actions', 0);
    }

    public function test_a_platform_rate_limit_is_reported_as_such(): void
    {
        $groupe = $this->groupe();
        $actor = User::factory()->create();

        Http::fake(['tve.test/*' => Http::response('', 429)]);

        try
        {
            app(AccessSuspender::class)->block($groupe, $actor, 'Facture impayée.');
            $this->fail('Le plafond de la plateforme doit interrompre la suspension.');
        }
        catch (SaasUnreachable $exception)
        {
            // « Trop de suspensions cette heure-ci » n'est pas une panne :
            // l'équipe doit pouvoir comprendre qu'il suffit d'attendre.
            $this->assertSame(SaasFailure::RateLimited, $exception->failure);
            $this->assertStringContainsString('Réessayez plus tard', $exception->forHumans());
        }

        $this->assertFalse($groupe->refresh()->is_blocked);
    }

    public function test_an_expired_token_is_reported_as_a_configuration_problem(): void
    {
        $groupe = $this->groupe();
        $actor = User::factory()->create();

        Http::fake(['tve.test/*' => Http::response('', 401)]);

        try
        {
            app(AccessSuspender::class)->block($groupe, $actor, 'Facture impayée.');
            $this->fail('Un jeton refusé doit interrompre la suspension.');
        }
        catch (SaasUnreachable $exception)
        {
            $this->assertSame(SaasFailure::Unauthorized, $exception->failure);
            $this->assertStringContainsString('jeton', $exception->forHumans());
        }
    }

    public function test_lifting_a_suspension_updates_the_mirror_and_the_journal(): void
    {
        $groupe = $this->groupe(['is_blocked' => true, 'blocked_at' => now(), 'block_reason' => 'Impayé.']);
        $actor = User::factory()->create();

        Http::fake([
            'tve.test/api/access/groupes/7/unblock' => Http::response($this->remoteResponse([
                'access_blocked_at' => null,
                'access_block_reason' => null,
            ])),
        ]);

        app(AccessSuspender::class)->unblock($groupe, $actor);

        $groupe->refresh();

        $this->assertFalse($groupe->is_blocked);
        $this->assertNull($groupe->block_reason);
        $this->assertDatabaseHas('block_actions', [
            'groupe_id' => $groupe->id,
            'action' => BlockAction::ACTION_UNBLOCK,
        ]);
    }

    public function test_the_agent_is_transmitted_to_the_platform_for_its_audit_trail(): void
    {
        $groupe = $this->groupe();
        $actor = User::factory()->create(['email' => 'agent@acan.email']);

        Http::fake(['tve.test/*' => Http::response($this->remoteResponse())]);

        app(AccessSuspender::class)->block($groupe, $actor, 'Facture impayée.');

        Http::assertSent(fn ($request) => $request['actor'] === 'agent@acan.email');
    }

    public function test_the_journal_survives_the_deletion_of_the_account_that_acted(): void
    {
        $groupe = $this->groupe();
        $actor = User::factory()->create(['name' => 'Agent Parti', 'email' => 'parti@acan.email']);

        Http::fake(['tve.test/*' => Http::response($this->remoteResponse())]);

        app(AccessSuspender::class)->block($groupe, $actor, 'Facture impayée.');

        // Le cas qui justifie les instantanés : répondre à un client en litige
        // des mois après le départ de l'agent qui a pris la décision.
        $actor->delete();

        $action = BlockAction::first();

        $this->assertSame('Agent Parti', $action->actor_name);
        $this->assertSame('parti@acan.email', $action->actor_email);
        $this->assertSame('Groupe Alpha', $action->groupe_name);
        $this->assertSame('TVe', $action->platform_name);
    }

    public function test_the_journal_survives_the_disappearance_of_the_groupe(): void
    {
        $groupe = $this->groupe();
        $actor = User::factory()->create();

        Http::fake(['tve.test/*' => Http::response($this->remoteResponse())]);

        app(AccessSuspender::class)->block($groupe, $actor, 'Facture impayée.');

        $groupe->delete();

        $action = BlockAction::first();

        $this->assertNull($action->groupe_id);
        $this->assertSame('Groupe Alpha', $action->groupe_name);
    }
}
