<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\BlockReasonTemplate;
use App\Models\Groupe;
use App\Models\SaasPlatform;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PlatformAdministrationTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->admin()->create();
    }

    public function test_a_collector_cannot_reach_platform_administration(): void
    {
        $this->actingAs(User::factory()->create())
            ->getJson('/api/platforms')
            ->assertForbidden();
    }

    public function test_a_platform_can_be_registered(): void
    {
        $this->actingAs($this->admin())
            ->postJson('/api/platforms', [
                'name' => 'TVe',
                'base_url' => 'https://tve.example.com',
                'api_token' => str_repeat('a', 64),
            ])
            ->assertCreated()
            ->assertJsonPath('data.name', 'TVe')
            // Le jeton ne repart jamais vers le navigateur.
            ->assertJsonMissingPath('data.api_token');

        $this->assertSame(str_repeat('a', 64), SaasPlatform::first()->api_token);
    }

    public function test_the_token_is_stored_encrypted(): void
    {
        $this->actingAs($this->admin())->postJson('/api/platforms', [
            'name' => 'TVe',
            'base_url' => 'https://tve.example.com',
            'api_token' => 'jeton-en-clair',
        ]);

        $raw = SaasPlatform::first()->getRawOriginal('api_token');

        // Une copie de la base ne doit pas livrer de quoi couper l'accès des
        // clients de toutes les plateformes.
        $this->assertNotSame('jeton-en-clair', $raw);
        $this->assertSame('jeton-en-clair', Crypt::decryptString($raw));
    }

    public function test_the_url_can_be_corrected_without_resupplying_the_token(): void
    {
        $platform = SaasPlatform::factory()->create([
            'name' => 'TVe',
            'base_url' => 'https://ancienne-adresse.test',
            'api_token' => 'jeton-inchange',
        ]);

        // Le cas réel : l'équipe réseau déplace le projet, l'URL change, mais
        // le jeton n'est jamais réaffiché et ne peut donc pas être ressaisi.
        $this->actingAs($this->admin())
            ->putJson("/api/platforms/{$platform->id}", [
                'name' => 'TVe',
                'base_url' => 'https://nouvelle-adresse.test',
                'api_token' => '',
            ])
            ->assertOk()
            ->assertJsonPath('data.base_url', 'https://nouvelle-adresse.test');

        $this->assertSame('jeton-inchange', $platform->refresh()->api_token);
    }

    public function test_connection_test_reports_success(): void
    {
        $platform = SaasPlatform::factory()->create(['base_url' => 'https://tve.test']);

        Http::fake(['tve.test/api/access/groupes' => Http::response(['data' => [
            ['id' => 1, 'name' => 'A', 'lang' => 'fr', 'users_count' => 1, 'enabled' => true, 'access_blocked_at' => null],
            ['id' => 2, 'name' => 'B', 'lang' => 'fr', 'users_count' => 2, 'enabled' => true, 'access_blocked_at' => null],
        ]])]);

        $this->actingAs($this->admin())
            ->postJson("/api/platforms/{$platform->id}/test")
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Connexion établie : 2 groupe(s) visible(s) sur cette plateforme.');

        $this->assertNotNull($platform->refresh()->last_reachable_at);
    }

    public function test_connection_test_distinguishes_a_rejected_token(): void
    {
        $platform = SaasPlatform::factory()->create(['base_url' => 'https://tve.test']);

        Http::fake(['tve.test/*' => Http::response('', 401)]);

        // « Jeton refusé » et « serveur injoignable » appellent deux réactions
        // différentes : l'écran doit les nommer distinctement.
        $this->actingAs($this->admin())
            ->postJson("/api/platforms/{$platform->id}/test")
            ->assertOk()
            ->assertJsonPath('success', false)
            ->assertJsonPath('failure', 'unauthorized');
    }

    public function test_connection_test_distinguishes_an_unreachable_server(): void
    {
        $platform = SaasPlatform::factory()->create(['base_url' => 'https://introuvable.test']);

        Http::fake(['introuvable.test/*' => fn () => throw new \Illuminate\Http\Client\ConnectionException('nope')]);

        $this->actingAs($this->admin())
            ->postJson("/api/platforms/{$platform->id}/test")
            ->assertOk()
            ->assertJsonPath('success', false)
            ->assertJsonPath('failure', 'unreachable');
    }

    public function test_removing_a_platform_removes_its_mirrored_groupes(): void
    {
        $platform = SaasPlatform::factory()->create();
        Groupe::factory()->create(['platform_id' => $platform->id]);

        $this->actingAs($this->admin())
            ->deleteJson("/api/platforms/{$platform->id}")
            ->assertOk();

        $this->assertDatabaseCount('groupes', 0);
    }

    public function test_reason_templates_can_be_managed(): void
    {
        $admin = $this->admin();

        $response = $this->actingAs($admin)->postJson('/api/reason-templates', [
            'label' => 'Mise en demeure',
            'body_fr' => 'Texte français.',
            'body_en' => 'English text.',
            'position' => 4,
        ])->assertCreated();

        $id = $response->json('data.id');

        $this->actingAs($admin)->putJson("/api/reason-templates/{$id}", [
            'label' => 'Mise en demeure',
            'body_fr' => 'Texte corrigé.',
            'body_en' => 'Corrected text.',
            'position' => 4,
        ])->assertOk()->assertJsonPath('data.body_fr', 'Texte corrigé.');

        $this->actingAs($admin)->deleteJson("/api/reason-templates/{$id}")->assertOk();

        $this->assertDatabaseCount('block_reason_templates', 0);
    }

    public function test_a_template_is_rejected_when_a_language_is_missing(): void
    {
        // Un modèle incomplet produirait un message vide pour les clients de
        // la langue manquante.
        $this->actingAs($this->admin())
            ->postJson('/api/reason-templates', [
                'label' => 'Incomplet',
                'body_fr' => 'Seulement en français.',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('body_en');

        $this->assertDatabaseCount('block_reason_templates', 0);
    }

    public function test_deleting_a_template_does_not_alter_suspensions_already_issued(): void
    {
        $admin = $this->admin();
        $template = BlockReasonTemplate::create([
            'label' => 'Facture impayée',
            'body_fr' => 'Motif type.',
            'body_en' => 'Template reason.',
            'position' => 1,
        ]);

        $groupe = Groupe::factory()->blocked('Motif type.')->create();

        $this->actingAs($admin)->deleteJson("/api/reason-templates/{$template->id}")->assertOk();

        // Le motif a été copié au moment de la suspension : il ne dépend pas
        // du modèle qui a servi à le rédiger.
        $this->assertSame('Motif type.', $groupe->refresh()->block_reason);
    }
}
