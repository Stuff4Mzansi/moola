<?php

namespace Tests\Feature;

use App\Domain\Identity\Actions\CreateFirstAdministrator;
use App\Domain\Identity\Actions\CreateHouseholdUser;
use App\Domain\Identity\Actions\ResetUserPassword;
use App\Domain\Identity\Enums\Capability;
use App\Domain\Identity\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Tests\TestCase;

final class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_empty_installation_routes_to_one_time_browser_setup(): void
    {
        $this->get('/')->assertRedirect('/setup');
        $this->get('/login')->assertRedirect('/setup');
        $this->get('/setup')
            ->assertOk()
            ->assertSee('Create your household')
            ->assertSee('Create administrator');

        $this->post('/setup', [
            'household_name' => 'Our Household',
            'name' => 'Moola Owner',
            'email' => 'Owner@Example.test',
            'password' => 'Strong-Passphrase-42!',
            'password_confirmation' => 'Strong-Passphrase-42!',
        ])->assertRedirect('/');

        $administrator = User::query()->sole();

        $this->assertAuthenticatedAs($administrator);
        $this->assertTrue($administrator->is_administrator);
        $this->assertTrue($administrator->is_enabled);
        $this->assertFalse($administrator->must_change_password);
        $this->assertSame('argon2id', password_get_info($administrator->password)['algoName']);
        $this->assertDatabaseCount('households', 1);
        $this->assertDatabaseCount('household_memberships', 1);
        $this->assertDatabaseCount('permissions', count(Capability::cases()));
        $this->assertDatabaseHas('installation_settings', ['currency_code' => 'ZAR']);

        $this->post('/setup', [
            'household_name' => 'Another Household',
            'name' => 'Another Owner',
            'email' => 'another@example.test',
            'password' => 'Another-Password-42!',
            'password_confirmation' => 'Another-Password-42!',
        ])->assertRedirect('/');
        $this->assertDatabaseCount('users', 1);

        $this->post('/logout');
        $this->get('/setup')->assertRedirect('/login');
        $this->post('/setup', [])->assertRedirect('/login');
        $this->assertDatabaseCount('users', 1);
    }

    public function test_administrator_created_user_has_no_permissions_and_must_change_password(): void
    {
        $administrator = $this->createAdministrator();

        $user = app(CreateHouseholdUser::class)->handle(
            $administrator,
            'Household Member',
            'MEMBER@EXAMPLE.TEST',
            'Temporary-Password-42!',
        );

        $this->assertSame('member@example.test', $user->email_normalized);
        $this->assertTrue($user->must_change_password);
        $this->assertFalse($user->is_administrator);
        $this->assertTrue(Hash::check('Temporary-Password-42!', $user->password));
        $this->assertDatabaseCount('permissions', count(Capability::cases()));
        $this->assertDatabaseHas('household_memberships', ['user_id' => $user->id, 'removed_at' => null]);
    }

    public function test_first_run_setup_rejects_invalid_details_without_creating_partial_data(): void
    {
        $this->post('/setup', [
            'household_name' => '',
            'name' => '',
            'email' => 'not-an-email',
            'password' => 'weak',
            'password_confirmation' => 'different',
        ])->assertSessionHasErrors(['household_name', 'name', 'email', 'password']);

        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('households', 0);
        $this->get('/setup')->assertOk();
    }

    public function test_user_can_sign_in_with_normalized_email_and_sign_out(): void
    {
        $user = $this->createAdministrator();
        $this->get('/login')->assertOk();
        $anonymousSessionId = session()->getId();

        $this->post('/login', [
            'email' => '  OWNER@EXAMPLE.TEST ',
            'password' => 'Strong-Passphrase-42!',
        ])->assertRedirect('/');

        $this->assertAuthenticatedAs($user);
        $this->assertNotSame($anonymousSessionId, session()->getId());

        $this->post('/logout')->assertRedirect('/login');
        $this->assertGuest();
        $this->assertDatabaseHas('audit_events', ['event_type' => 'authentication.signed_in']);
        $this->assertDatabaseHas('audit_events', ['event_type' => 'authentication.signed_out']);
    }

    public function test_session_cookies_are_encrypted_and_http_only(): void
    {
        $this->assertTrue(config('session.encrypt'));
        $this->assertTrue(config('session.http_only'));
        $this->assertSame('lax', config('session.same_site'));
    }

    public function test_invalid_and_disabled_accounts_receive_the_same_generic_failure(): void
    {
        $user = $this->createAdministrator();
        $genericMessage = 'These credentials do not match our records.';

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'Incorrect-Password-42!',
        ])->assertSessionHasErrors(['email' => $genericMessage]);

        $user->update(['is_enabled' => false]);

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'Strong-Passphrase-42!',
        ])->assertSessionHasErrors(['email' => $genericMessage]);
    }

    public function test_repeated_failed_sign_in_attempts_are_rate_limited(): void
    {
        $this->createAdministrator();
        $email = 'missing@example.test';

        foreach (range(1, 6) as $attempt) {
            $this->post('/login', [
                'email' => $email,
                'password' => 'Incorrect-Password-42!',
            ])->assertSessionHasErrors('email');
        }

        $key = Str::transliterate($email.'|127.0.0.1');
        $this->assertSame(5, RateLimiter::attempts($key));
    }

    public function test_forced_password_change_replaces_the_hash_and_clears_the_flag(): void
    {
        $administrator = $this->createAdministrator();
        $user = app(CreateHouseholdUser::class)->handle(
            $administrator,
            'Household Member',
            'member@example.test',
            'Temporary-Password-42!',
        );

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'Temporary-Password-42!',
        ])->assertRedirect('/password/change');

        $this->get('/')->assertRedirect('/password/change');

        $this->put('/password/change', [
            'current_password' => 'Temporary-Password-42!',
            'password' => 'Replacement-Password-84!',
            'password_confirmation' => 'Replacement-Password-84!',
        ])->assertRedirect('/');

        $user->refresh();
        $this->assertFalse($user->must_change_password);
        $this->assertTrue(Hash::check('Replacement-Password-84!', $user->password));
        $this->assertFalse(Hash::check('Temporary-Password-42!', $user->password));
        $this->assertAuthenticatedAs($user);
        $this->get('/')->assertOk();
        $this->assertDatabaseHas('audit_events', [
            'event_type' => 'user.password_changed',
            'subject_id' => $user->id,
        ]);
    }

    public function test_administrator_password_reset_forces_change_and_invalidates_sessions(): void
    {
        $administrator = $this->createAdministrator();
        $user = app(CreateHouseholdUser::class)->handle(
            $administrator,
            'Household Member',
            'member@example.test',
            'Temporary-Password-42!',
        );

        $this->insertSessionFor($user);
        app(ResetUserPassword::class)->handle($user, 'Reset-Password-84!', $administrator);

        $user->refresh();
        $this->assertTrue($user->must_change_password);
        $this->assertTrue(Hash::check('Reset-Password-84!', $user->password));
        $this->assertDatabaseMissing('sessions', ['user_id' => $user->id]);
        $this->assertDatabaseHas('audit_events', [
            'actor_user_id' => $administrator->id,
            'event_type' => 'user.password_reset',
            'subject_id' => $user->id,
        ]);
    }

    public function test_there_is_no_public_registration_route(): void
    {
        $this->createAdministrator();

        $this->get('/register')->assertNotFound();
        $this->post('/register')->assertNotFound();
    }

    private function createAdministrator(): User
    {
        return app(CreateFirstAdministrator::class)->handle(
            'Moola Owner',
            'owner@example.test',
            'Strong-Passphrase-42!',
            'Our Household',
        );
    }

    private function insertSessionFor(User $user): void
    {
        $user->getConnection()->table('sessions')->insert([
            'id' => Str::random(40),
            'user_id' => $user->id,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'PHPUnit',
            'payload' => 'test',
            'last_activity' => now()->timestamp,
        ]);
    }
}
