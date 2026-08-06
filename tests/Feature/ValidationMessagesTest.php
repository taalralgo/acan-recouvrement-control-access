<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Laravel ne fournit aucune traduction française : sans `lang/fr/validation.php`,
 * les formulaires affichent la clé brute (« validation.min.string »).
 * Ces tests garantissent qu'aucune clé ne fuit jusqu'à l'écran.
 */
class ValidationMessagesTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @param array<int, string> $messages
     */
    private function assertAllTranslated(array $messages): void
    {
        $this->assertNotEmpty($messages, 'Aucun message de validation à vérifier.');

        foreach ($messages as $message)
        {
            $this->assertStringNotContainsString(
                'validation.',
                $message,
                "Message non traduit affiché à l'utilisateur : « {$message} »"
            );
        }
    }

    /**
     * @return array<int, string>
     */
    private function errorsFrom($response): array
    {
        return collect($response->json('errors'))->flatten()->all();
    }

    public function test_the_password_form_shows_readable_messages(): void
    {
        $user = User::factory()->pendingPasswordChange()->create();

        $response = $this->actingAs($user)->putJson(route('password.change.update'), [
            'current_password' => '',
            'password' => 'court',
            'password_confirmation' => 'different',
        ]);

        $this->assertAllTranslated($this->errorsFrom($response));
    }

    public function test_the_login_form_shows_readable_messages(): void
    {
        $response = $this->postJson('/login', ['email' => 'pas-une-adresse', 'password' => '']);

        $this->assertAllTranslated($this->errorsFrom($response));
    }

    public function test_the_account_form_shows_readable_messages(): void
    {
        $response = $this->actingAs(User::factory()->admin()->create())
            ->postJson('/api/team', ['name' => '', 'email' => 'perso@gmail.com', 'role' => 'inconnu'])
            ->assertStatus(422);

        $this->assertAllTranslated(collect($response->json('errors'))->flatten()->all());
    }

    public function test_the_platform_form_shows_readable_messages(): void
    {
        $response = $this->actingAs(User::factory()->admin()->create())
            ->postJson('/api/platforms', ['name' => '', 'base_url' => 'pas-une-url', 'api_token' => ''])
            ->assertStatus(422);

        $this->assertAllTranslated(collect($response->json('errors'))->flatten()->all());
    }

    public function test_the_reason_template_form_shows_readable_messages(): void
    {
        $response = $this->actingAs(User::factory()->admin()->create())
            ->postJson('/api/reason-templates', ['label' => '', 'body_fr' => ''])
            ->assertStatus(422);

        $this->assertAllTranslated(collect($response->json('errors'))->flatten()->all());
    }

    public function test_field_names_appear_in_french(): void
    {
        $response = $this->actingAs(User::factory()->admin()->create())
            ->postJson('/api/team', ['name' => 'X', 'email' => '', 'role' => 'admin'])
            ->assertStatus(422);

        // « Le champ email est obligatoire » n'a pas le même confort de
        // lecture que « Le champ adresse email est obligatoire ».
        $this->assertStringContainsString('adresse email', $response->json('errors.email.0'));
    }
}
