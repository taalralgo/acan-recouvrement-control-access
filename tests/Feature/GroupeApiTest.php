<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\BlockReasonTemplate;
use App\Models\Groupe;
use App\Models\SaasPlatform;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Contrat consommé par l'interface.
 */
class GroupeApiTest extends TestCase
{
    use RefreshDatabase;

    private function actor(): User
    {
        return User::factory()->create();
    }

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
            'users_count' => 14,
        ], $overrides));
    }

    private function remoteResponse(array $overrides = []): array
    {
        return ['data' => array_merge([
            'id' => 7,
            'code' => 'GRP1',
            'name' => 'Groupe Alpha',
            'lang' => 'fr',
            'users_count' => 14,
            'enabled' => true,
            'access_blocked_at' => '2026-08-06T09:00:00+00:00',
            'access_block_reason' => 'Facture impayée.',
        ], $overrides)];
    }

    public function test_the_list_requires_authentication(): void
    {
        $this->getJson('/api/groupes')->assertUnauthorized();
    }

    public function test_the_list_exposes_both_locks_separately(): void
    {
        $groupe = $this->groupe(['platform_enabled' => false]);

        $response = $this->actingAs($this->actor())->getJson('/api/groupes')->assertOk();

        $payload = $response->json('data.0');

        $this->assertFalse($payload['is_blocked']);
        // Les deux verrous doivent rester distincts à l'écran, sinon un
        // déblocage semble sans effet.
        $this->assertTrue($payload['disabled_by_platform']);
        $this->assertSame($groupe->name, $payload['name']);
    }

    public function test_the_list_can_be_searched_and_filtered(): void
    {
        $platform = SaasPlatform::factory()->create();
        Groupe::factory()->create(['platform_id' => $platform->id, 'name' => 'Radio Soleil']);
        Groupe::factory()->blocked()->create(['platform_id' => $platform->id, 'name' => 'Radio Lune']);

        $actor = $this->actor();

        $this->actingAs($actor)->getJson('/api/groupes?search=Soleil')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Radio Soleil');

        $this->actingAs($actor)->getJson('/api/groupes?status=blocked')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Radio Lune');
    }

    /**
     * La synchronisation étant manuelle en v1, l'écran doit dire de lui-même
     * si la liste a vieilli depuis la dernière visite.
     */
    public function test_the_list_reports_how_fresh_it_is(): void
    {
        $this->groupe(['synced_at' => now()->subMinutes(5)]);

        $this->actingAs($this->actor())
            ->getJson('/api/groupes')
            ->assertOk()
            ->assertJsonPath('sync.is_stale', false)
            ->assertJsonStructure(['sync' => ['last_at', 'is_stale']]);
    }

    public function test_an_old_list_is_flagged_as_stale(): void
    {
        $this->groupe(['synced_at' => now()->subDay()]);

        $this->actingAs($this->actor())
            ->getJson('/api/groupes')
            ->assertOk()
            ->assertJsonPath('sync.is_stale', true);
    }

    public function test_the_oldest_platform_determines_the_freshness(): void
    {
        $this->groupe(['synced_at' => now()]);
        Groupe::factory()->create(['synced_at' => now()->subDay()]);

        // Si une plateforme n'a pas répondu, c'est elle qui doit décider du
        // message : annoncer « à jour » masquerait son échec.
        $this->actingAs($this->actor())
            ->getJson('/api/groupes')
            ->assertOk()
            ->assertJsonPath('sync.is_stale', true);
    }

    public function test_blocking_returns_the_updated_row(): void
    {
        $groupe = $this->groupe();

        Http::fake(['tve.test/*' => Http::response($this->remoteResponse())]);

        $this->actingAs($this->actor())
            ->postJson("/api/groupes/{$groupe->id}/block", ['reason' => 'Facture impayée.'])
            ->assertOk()
            ->assertJsonPath('data.is_blocked', true)
            ->assertJsonPath('data.block_reason', 'Facture impayée.');
    }

    public function test_blocking_requires_a_reason(): void
    {
        $groupe = $this->groupe();

        Http::fake();

        $this->actingAs($this->actor())
            ->postJson("/api/groupes/{$groupe->id}/block", ['reason' => ''])
            ->assertStatus(422);

        Http::assertNothingSent();
    }

    public function test_a_platform_refusal_is_reported_and_changes_nothing(): void
    {
        $groupe = $this->groupe();

        Http::fake(['tve.test/*' => Http::response('', 429)]);

        $response = $this->actingAs($this->actor())
            ->postJson("/api/groupes/{$groupe->id}/block", ['reason' => 'Facture impayée.'])
            ->assertStatus(409);

        // L'agent doit comprendre ce qui s'est passé sans lire de trace
        // technique, et surtout savoir que rien n'a été appliqué.
        $this->assertSame('rate_limited', $response->json('failure'));
        $this->assertStringContainsString('Réessayez plus tard', $response->json('message'));
        $this->assertFalse($groupe->refresh()->is_blocked);
        $this->assertDatabaseCount('block_actions', 0);
    }

    public function test_unblocking_returns_the_updated_row(): void
    {
        $groupe = $this->groupe(['is_blocked' => true, 'blocked_at' => now()]);

        Http::fake(['tve.test/*' => Http::response($this->remoteResponse([
            'access_blocked_at' => null,
            'access_block_reason' => null,
        ]))]);

        $this->actingAs($this->actor())
            ->postJson("/api/groupes/{$groupe->id}/unblock")
            ->assertOk()
            ->assertJsonPath('data.is_blocked', false);
    }

    public function test_the_history_lists_decisions_newest_first(): void
    {
        $groupe = $this->groupe();

        Http::fake(['tve.test/*' => Http::response($this->remoteResponse())]);
        $actor = $this->actor();

        $this->actingAs($actor)->postJson("/api/groupes/{$groupe->id}/block", ['reason' => 'Impayé.']);

        Http::fake(['tve.test/*' => Http::response($this->remoteResponse([
            'access_blocked_at' => null,
            'access_block_reason' => null,
        ]))]);

        $this->actingAs($actor)->postJson("/api/groupes/{$groupe->id}/unblock");

        $response = $this->actingAs($actor)
            ->getJson("/api/groupes/{$groupe->id}/actions")
            ->assertOk()
            ->assertJsonCount(2, 'data');

        $this->assertSame('unblock', $response->json('data.0.action'));
        $this->assertSame($actor->name, $response->json('data.0.actor_name'));
    }

    public function test_reason_templates_are_available_for_the_dialog(): void
    {
        BlockReasonTemplate::create([
            'label' => 'Facture impayée',
            'body_fr' => 'Motif en français.',
            'body_en' => 'Reason in English.',
            'position' => 1,
        ]);

        $this->actingAs($this->actor())
            ->getJson('/api/reason-templates')
            ->assertOk()
            ->assertJsonPath('data.0.body_fr', 'Motif en français.')
            ->assertJsonPath('data.0.body_en', 'Reason in English.');
    }

    public function test_sync_reports_platform_by_platform(): void
    {
        SaasPlatform::factory()->create(['base_url' => 'https://casse.test', 'name' => 'Cassée']);

        Http::fake(['casse.test/*' => Http::response('', 500)]);

        $this->actingAs($this->actor())
            ->postJson('/api/sync')
            ->assertOk()
            ->assertJsonPath('all_succeeded', false)
            ->assertJsonPath('data.0.platform', 'Cassée')
            ->assertJsonPath('data.0.success', false);
    }

    public function test_a_temporary_password_blocks_access_to_the_interface(): void
    {
        $user = User::factory()->pendingPasswordChange()->create();

        $this->actingAs($user)->getJson('/api/groupes')->assertForbidden();
    }
}
