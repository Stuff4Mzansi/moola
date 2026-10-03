<?php

use App\Models\User;
use App\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

test('first visit requires admin setup before any authenticated functionality', function (string $path) {
    $this->get($path)->assertRedirect(route('setup.create'));
})->with(['/', '/login', '/admin/users', '/admin/users/create']);

test('setup renders when no administrator exists even if members exist', function () {
    User::factory()->create();

    $this->get(route('setup.create'))->assertOk()->assertSee('Create super admin account');
});

test('setup creates and authenticates the super admin with a hashed password', function () {
    $this->post(route('setup.store'), [
        'name' => 'Household owner',
        'email' => 'owner@example.com',
        'password' => 'a-long-owner-password',
        'password_confirmation' => 'a-long-owner-password',
        'role' => 'member',
    ])->assertRedirect(route('dashboard'));

    $owner = User::query()->sole();
    expect($owner->role)->toBe(UserRole::SuperAdmin)
        ->and(Hash::check('a-long-owner-password', $owner->password))->toBeTrue();
    $this->assertAuthenticatedAs($owner);
    $this->get(route('dashboard'))->assertOk()->assertSee('Manage users');
});

test('setup is closed when either kind of administrator exists', function (UserRole $role) {
    User::factory()->create(['role' => $role]);

    $this->get(route('setup.create'))->assertRedirect(route('login'));
    $this->post(route('setup.store'), [
        'name' => 'Another owner',
        'email' => 'another@example.com',
        'password' => 'another-long-password',
        'password_confirmation' => 'another-long-password',
    ])->assertForbidden();
    $this->assertDatabaseCount('users', 1);
})->with([UserRole::Admin, UserRole::SuperAdmin]);

test('invalid setup cannot create a user', function () {
    $this->post(route('setup.store'), [
        'name' => '',
        'email' => 'invalid',
        'password' => 'short',
        'password_confirmation' => 'different',
    ])->assertSessionHasErrors(['name', 'email', 'password']);

    $this->assertDatabaseCount('users', 0);
    $this->assertGuest();
});

test('public registration is unavailable', function () {
    User::factory()->superAdmin()->create();

    $this->get('/register')->assertNotFound();
    $this->post('/register')->assertNotFound();
});

test('guests must sign in once setup is complete', function () {
    User::factory()->superAdmin()->create();

    $this->get(route('login'))->assertOk()->assertSee('Sign in to Moola');
    $this->get(route('dashboard'))->assertRedirect(route('login'));
    $this->get(route('admin.users.index'))->assertRedirect(route('login'));
});

test('users can sign in and are returned to their intended page', function () {
    $admin = User::factory()->superAdmin()->create();
    $this->get(route('admin.users.index'))->assertRedirect(route('login'));

    $this->post(route('login.store'), [
        'email' => $admin->email,
        'password' => 'password',
        'remember' => '1',
    ])->assertRedirect(route('admin.users.index'));

    $this->assertAuthenticatedAs($admin);
});

test('incorrect credentials do not authenticate a user', function () {
    $admin = User::factory()->superAdmin()->create();

    $this->post(route('login.store'), [
        'email' => $admin->email,
        'password' => 'incorrect',
    ])->assertSessionHasErrors('email');

    $this->assertGuest();
});

test('sign in is throttled after repeated failures', function () {
    $admin = User::factory()->superAdmin()->create();
    $credentials = ['email' => $admin->email, 'password' => 'incorrect'];

    for ($attempt = 0; $attempt < 5; $attempt++) {
        $this->post(route('login.store'), $credentials)->assertSessionHasErrors('email');
    }

    $this->post(route('login.store'), ['email' => $admin->email, 'password' => 'password'])
        ->assertSessionHasErrors('email');
    expect(session('errors')->first('email'))->toContain('Too many sign-in attempts');
    $this->assertGuest();
});

test('sign out invalidates authentication and uses a post request', function () {
    $admin = User::factory()->superAdmin()->create();

    $this->actingAs($admin)->get('/logout')->assertMethodNotAllowed();
    $this->post(route('logout'))->assertRedirect(route('login'));
    $this->assertGuest();
    $this->get(route('dashboard'))->assertRedirect(route('login'));
});
