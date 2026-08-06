<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Groupe;
use App\Models\SaasPlatform;
use App\Services\GroupeSynchronizer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SynchronizationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @param array<int, array<string, mixed>> $groupes
     */
    private function payload(array $groupes): array
    {
        return ['data' => $groupes];
    }

    /**
     * @param array<string, mixed> $overrides
     */
    private function remote(array $overrides = []): array
    {
        return array_merge([
            'id' => 7,
            'code' => 'GRP1',
            'name' => 'Groupe Alpha',
            'lang' => 'fr',
            'users_count' => 12,
            'enabled' => true,
            'access_blocked_at' => null,
            'access_block_reason' => null,
        ], $overrides);
    }

    public function test_synchronisation_fills_the_local_mirror(): void
    {
        $platform = SaasPlatform::factory()->create(['base_url' => 'https://tve.test']);

        Http::fake([
            'tve.test/api/access/groupes' => Http::response($this->payload([
                $this->remote(),
                $this->remote(['id' => 8, 'name' => 'Groupe Beta', 'users_count' => 3]),
            ])),
        ]);

        $result = app(GroupeSynchronizer::class)->sync($platform);

        $this->assertTrue($result->success);
        $this->assertSame(2, $result->synced);
        $this->assertDatabaseHas('groupes', [
            'platform_id' => $platform->id,
            'external_id' => 7,
            'name' => 'Groupe Alpha',
            'users_count' => 12,
        ]);
    }

    public function test_synchronisation_reflects_a_suspension_decided_elsewhere(): void
    {
        $platform = SaasPlatform::factory()->create(['base_url' => 'https://tve.test']);

        Http::fake([
            'tve.test/api/access/groupes' => Http::response($this->payload([
                $this->remote([
                    'access_blocked_at' => '2026-08-01T10:00:00+00:00',
                    'access_block_reason' => 'Facture impayée.',
                ]),
            ])),
        ]);

        app(GroupeSynchronizer::class)->sync($platform);

        $groupe = Groupe::firstWhere('external_id', 7);

        $this->assertTrue($groupe->is_blocked);
        $this->assertSame('Facture impayée.', $groupe->block_reason);
    }

    public function test_the_second_flag_of_the_platform_is_mirrored_separately(): void
    {
        $platform = SaasPlatform::factory()->create(['base_url' => 'https://tve.test']);

        Http::fake([
            'tve.test/api/access/groupes' => Http::response($this->payload([
                $this->remote(['enabled' => false]),
            ])),
        ]);

        app(GroupeSynchronizer::class)->sync($platform);

        // Un groupe désactivé par les admins de la plateforme et non suspendu
        // par le recouvrement : les deux états doivent rester distincts.
        $groupe = Groupe::firstWhere('external_id', 7);

        $this->assertFalse($groupe->is_blocked);
        $this->assertTrue($groupe->isDisabledByPlatform());
    }

    public function test_groupes_removed_upstream_leave_the_mirror(): void
    {
        $platform = SaasPlatform::factory()->create(['base_url' => 'https://tve.test']);
        Groupe::factory()->create(['platform_id' => $platform->id, 'external_id' => 99]);

        Http::fake([
            'tve.test/api/access/groupes' => Http::response($this->payload([$this->remote()])),
        ]);

        $result = app(GroupeSynchronizer::class)->sync($platform);

        $this->assertSame(1, $result->removed);
        $this->assertDatabaseMissing('groupes', ['platform_id' => $platform->id, 'external_id' => 99]);
    }

    public function test_an_unreachable_platform_keeps_its_existing_mirror(): void
    {
        $platform = SaasPlatform::factory()->create(['base_url' => 'https://tve.test']);
        Groupe::factory()->create([
            'platform_id' => $platform->id,
            'external_id' => 7,
            'name' => 'Connu hier',
        ]);

        Http::fake(['tve.test/*' => Http::response('', 500)]);

        $result = app(GroupeSynchronizer::class)->sync($platform);

        $this->assertFalse($result->success);
        // Mieux vaut une liste d'hier qu'un écran vide : l'équipe doit pouvoir
        // continuer à consulter, avec la mention que l'information est datée.
        $this->assertDatabaseHas('groupes', ['external_id' => 7, 'name' => 'Connu hier']);
    }

    public function test_one_failing_platform_does_not_stop_the_others(): void
    {
        $broken = SaasPlatform::factory()->create(['base_url' => 'https://casse.test']);
        $healthy = SaasPlatform::factory()->create(['base_url' => 'https://ok.test']);

        Http::fake([
            'casse.test/*' => Http::response('', 500),
            'ok.test/api/access/groupes' => Http::response($this->payload([$this->remote()])),
        ]);

        $results = app(GroupeSynchronizer::class)->syncAll();

        $this->assertFalse($results->firstWhere('platform.id', $broken->id)->success);
        $this->assertTrue($results->firstWhere('platform.id', $healthy->id)->success);
        $this->assertDatabaseHas('groupes', ['platform_id' => $healthy->id, 'external_id' => 7]);
    }

    public function test_inactive_platforms_are_skipped(): void
    {
        SaasPlatform::factory()->inactive()->create();

        Http::fake();

        $this->assertTrue(app(GroupeSynchronizer::class)->syncAll()->isEmpty());
        Http::assertNothingSent();
    }

    public function test_a_successful_sync_records_the_platform_as_reachable(): void
    {
        $platform = SaasPlatform::factory()->create([
            'base_url' => 'https://tve.test',
            'last_reachable_at' => null,
        ]);

        Http::fake([
            'tve.test/api/access/groupes' => Http::response($this->payload([$this->remote()])),
        ]);

        app(GroupeSynchronizer::class)->sync($platform);

        $this->assertNotNull($platform->refresh()->last_reachable_at);
    }

    public function test_the_command_reports_a_failure_through_its_exit_code(): void
    {
        SaasPlatform::factory()->create(['base_url' => 'https://casse.test']);

        Http::fake(['casse.test/*' => Http::response('', 500)]);

        // Un cron doit pouvoir détecter qu'une plateforme n'a pas répondu.
        $this->artisan('regie:sync')->assertFailed();
    }
}
