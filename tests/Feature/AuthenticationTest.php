<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Http\Controllers\Auth\PasswordChangeController;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_member_can_log_in(): void
    {
        $user = User::factory()->create();

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertRedirect(route('groupes.index'));

        $this->assertAuthenticatedAs($user);
    }

    public function test_wrong_credentials_are_rejected(): void
    {
        $user = User::factory()->create();

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'mauvais',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    /**
     * Le cas qui motive toute la gestion des comptes : un employé parti ne
     * doit plus pouvoir entrer, y compris avec une session encore ouverte.
     */
    public function test_a_deleted_account_loses_access_immediately(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/')->assertOk();

        $user->delete();
        $this->app['auth']->forgetGuards();

        $this->get('/')->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_a_temporary_password_must_be_replaced_before_anything_else(): void
    {
        $user = User::factory()->pendingPasswordChange()->create();

        $this->actingAs($user)->get('/')->assertRedirect(route('password.change.edit'));

        // L'écran de changement reste évidemment accessible.
        $this->actingAs($user)->get(route('password.change.edit'))->assertOk();
    }

    public function test_replacing_the_temporary_password_unlocks_the_application(): void
    {
        $user = User::factory()->pendingPasswordChange()->create();

        $this->actingAs($user)->put(route('password.change.update'), [
            'current_password' => 'password',
            'password' => 'un-mot-de-passe-solide',
            'password_confirmation' => 'un-mot-de-passe-solide',
        ])->assertRedirect(route('groupes.index'));

        $user->refresh();

        $this->assertFalse($user->must_change_password);
        $this->assertTrue(Hash::check('un-mot-de-passe-solide', $user->password));

        $this->actingAs($user)->get('/')->assertOk();
    }

    public function test_a_password_of_exactly_the_minimum_length_is_accepted(): void
    {
        $user = User::factory()->pendingPasswordChange()->create();
        $minimum = str_repeat('a', PasswordChangeController::MIN_LENGTH);

        $this->actingAs($user)->put(route('password.change.update'), [
            'current_password' => 'password',
            'password' => $minimum,
            'password_confirmation' => $minimum,
        ])->assertSessionHasNoErrors();

        $this->assertFalse($user->refresh()->must_change_password);
    }

    public function test_a_password_one_character_too_short_is_refused(): void
    {
        $user = User::factory()->pendingPasswordChange()->create();
        $tooShort = str_repeat('a', PasswordChangeController::MIN_LENGTH - 1);

        $this->actingAs($user)->put(route('password.change.update'), [
            'current_password' => 'password',
            'password' => $tooShort,
            'password_confirmation' => $tooShort,
        ])->assertSessionHasErrors('password');

        $this->assertTrue($user->refresh()->must_change_password);
    }

    public function test_the_new_password_cannot_be_the_temporary_one(): void
    {
        $user = User::factory()->pendingPasswordChange()->create();

        // Le mot de passe temporaire est connu de l'administrateur qui l'a
        // transmis : le conserver viderait de son sens le journal nominatif.
        $this->actingAs($user)->put(route('password.change.update'), [
            'current_password' => 'password',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])->assertSessionHasErrors('password');

        $this->assertTrue($user->refresh()->must_change_password);
    }

    public function test_a_collector_cannot_reach_administration(): void
    {
        $collector = User::factory()->create();
        $admin = User::factory()->admin()->create();

        $this->assertFalse($collector->isAdmin());
        $this->assertTrue($admin->isAdmin());
    }

    public function test_guests_are_sent_to_the_login_screen(): void
    {
        $this->get('/')->assertRedirect(route('login'));
    }
}
