<?php

use App\Models\User;
use App\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->owner = User::factory()->superAdmin()->create();
});

test('administrators can view user management and add user screens', function (UserRole $role) {
    $admin = User::factory()->create(['role' => $role]);

    $this->actingAs($admin)->get(route('admin.users.index'))->assertOk()->assertSee('Household users');
    $this->get(route('admin.users.create'))->assertOk()->assertSee('Add a household user');
})->with([UserRole::Admin, UserRole::SuperAdmin]);

test('members cannot use any user management endpoint', function () {
    $member = User::factory()->create();
    $target = User::factory()->create();
    $this->actingAs($member);

    $this->get(route('admin.users.index'))->assertForbidden();
    $this->get(route('admin.users.create'))->assertForbidden();
    $this->post(route('admin.users.store'), [])->assertForbidden();
    $this->patch(route('admin.users.update', $target), ['role' => 'admin'])->assertForbidden();
    $this->delete(route('admin.users.destroy', $target))->assertForbidden();

    expect($target->fresh()->role)->toBe(UserRole::Member);
    $this->assertDatabaseCount('users', 3);
    $this->get(route('dashboard'))->assertOk()->assertDontSee('Manage users')->assertDontSee('Household users');
});

test('administrators can create members and admins with hashed passwords', function (UserRole $actorRole, string $assignedRole) {
    $admin = User::factory()->create(['role' => $actorRole]);

    $this->actingAs($admin)->post(route('admin.users.store'), [
        'name' => 'Household member',
        'email' => 'member@example.com',
        'password' => 'a-long-member-password',
        'password_confirmation' => 'a-long-member-password',
        'role' => $assignedRole,
    ])->assertRedirect(route('admin.users.index'))->assertSessionHas('status');

    $user = User::query()->where('email', 'member@example.com')->sole();
    expect($user->role)->toBe(UserRole::from($assignedRole))
        ->and(Hash::check('a-long-member-password', $user->password))->toBeTrue();
})->with([UserRole::Admin, UserRole::SuperAdmin])->with(['member', 'admin']);

test('user creation rejects invalid details and duplicate emails', function () {
    $this->actingAs($this->owner)->post(route('admin.users.store'), [
        'name' => '',
        'email' => $this->owner->email,
        'password' => 'short',
        'password_confirmation' => 'different',
        'role' => 'unknown',
    ])->assertSessionHasErrors(['name', 'email', 'password', 'role']);

    $this->assertDatabaseCount('users', 1);
});

test('administrators cannot create additional super admins', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->post(route('admin.users.store'), [
        'name' => 'Another owner',
        'email' => 'another@example.com',
        'password' => 'another-long-password',
        'password_confirmation' => 'another-long-password',
        'role' => 'super_admin',
    ])->assertSessionHasErrors('role');

    $this->assertDatabaseCount('users', 2);
});

test('administrators can promote and demote other users', function (UserRole $actorRole) {
    $admin = User::factory()->create(['role' => $actorRole]);
    $member = User::factory()->create();

    $this->actingAs($admin)->patch(route('admin.users.update', $member), ['role' => 'admin'])
        ->assertRedirect(route('admin.users.index'));
    expect($member->fresh()->role)->toBe(UserRole::Admin);

    $this->patch(route('admin.users.update', $member), ['role' => 'member'])
        ->assertRedirect(route('admin.users.index'));
    expect($member->fresh()->role)->toBe(UserRole::Member);
})->with([UserRole::Admin, UserRole::SuperAdmin]);

test('role changes cannot assign the super admin role or update unrelated account fields', function () {
    $member = User::factory()->create();
    $originalEmail = $member->email;
    $originalPassword = $member->password;

    $this->actingAs($this->owner)->patch(route('admin.users.update', $member), ['role' => 'super_admin'])
        ->assertSessionHasErrors('role');
    expect($member->fresh()->role)->toBe(UserRole::Member);

    $this->patch(route('admin.users.update', $member), [
        'role' => 'admin',
        'email' => 'changed@example.com',
        'password' => 'changed-password',
    ])->assertRedirect(route('admin.users.index'));

    expect($member->fresh()->email)->toBe($originalEmail)
        ->and($member->fresh()->password)->toBe($originalPassword);
});

test('super admin cannot be deleted or demoted by other users', function (UserRole $role) {
    $actor = User::factory()->create(['role' => $role]);

    $this->actingAs($actor)->delete(route('admin.users.destroy', $this->owner))->assertForbidden();
    $this->patch(route('admin.users.update', $this->owner), ['role' => 'member'])->assertForbidden();

    expect($this->owner->fresh()->role)->toBe(UserRole::SuperAdmin);
})->with([UserRole::Admin, UserRole::Member]);

test('administrators cannot delete or demote their own account', function (UserRole $role) {
    $actor = User::factory()->create(['role' => $role]);

    $this->actingAs($actor)->delete(route('admin.users.destroy', $actor))->assertForbidden();
    $this->patch(route('admin.users.update', $actor), ['role' => 'member'])->assertForbidden();

    expect($actor->fresh()->role)->toBe($role);
})->with([UserRole::Admin, UserRole::SuperAdmin]);

test('administrators can delete other members and admins', function (UserRole $role) {
    $admin = User::factory()->admin()->create();
    $target = User::factory()->create(['role' => $role]);

    $this->actingAs($admin)->delete(route('admin.users.destroy', $target))
        ->assertRedirect(route('admin.users.index'));

    $this->assertModelMissing($target);
})->with([UserRole::Member, UserRole::Admin]);

test('a demoted administrator loses management access', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($this->owner)->patch(route('admin.users.update', $admin), ['role' => 'member'])
        ->assertRedirect(route('admin.users.index'));

    $this->actingAs($admin->fresh())->get(route('admin.users.index'))->assertForbidden();
});
