<?php

use App\Models\Budget;
use App\Models\BudgetPeriod;
use App\Models\Subscription;
use App\Models\User;
use App\UserRole;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->travelTo(Carbon::parse('2026-10-03 12:00:00'));
    User::factory()->superAdmin()->create();
    $this->owner = User::factory()->create();
    $this->budget = Budget::factory()->household()->create(['user_id' => $this->owner->id, 'name' => 'Private household finances']);
    $this->period = BudgetPeriod::factory()->create(['budget_id' => $this->budget->id]);
    $this->period->categories()->create(['name' => 'Subscriptions', 'kind' => 'subscriptions', 'allocated_cents' => null]);
    $this->period->categories()->create(['name' => 'Other', 'kind' => 'other', 'allocated_cents' => 0]);
});

test('app roles alone do not grant access to household or personal financial data', function (UserRole $role) {
    $actor = User::factory()->create(['role' => $role]);
    $this->actingAs($actor)->get(route('budgets.index'))->assertOk()->assertDontSee('Private household finances');
    $this->get(route('budgets.index', ['period' => $this->period->id]))->assertForbidden();
    $this->postJson(route('budgets.action', ['period' => $this->period, 'action' => 'category-save']), ['name' => 'No access', 'amount' => '10', 'version' => 1])->assertForbidden();
})->with([UserRole::Member, UserRole::Admin, UserRole::SuperAdmin]);

test('viewers can read the workspace but cannot mutate it or copy its plan', function () {
    $viewer = User::factory()->create();
    $this->budget->members()->attach($viewer->id, ['role' => 'viewer']);
    $this->actingAs($viewer)->get(route('budgets.index', ['period' => $this->period->id]))->assertOk()->assertSee('View only')->assertDontSee('data-budget-autosave', false);
    $this->postJson(route('budgets.action', ['period' => $this->period, 'action' => 'income-save']), [])->assertForbidden();
    $this->postJson(route('budgets.periods.store', $this->budget), [])->assertForbidden();
});

test('editors can manage spending but cannot grant access or change owner settings', function () {
    $editor = User::factory()->create();
    $this->budget->members()->attach($editor->id, ['role' => 'editor']);
    $this->actingAs($editor)->postJson(route('budgets.action', ['period' => $this->period, 'action' => 'category-save']), ['name' => 'Shared food', 'amount' => '200', 'version' => 1])->assertOk();
    $this->postJson(route('budgets.action', ['period' => $this->period, 'action' => 'member-save']), [])->assertForbidden();
    $this->postJson(route('budgets.action', ['period' => $this->period, 'action' => 'settings-save']), [])->assertForbidden();
    expect($editor->fresh()->role)->toBe(UserRole::Member);
});

test('owners can assign viewer and editor access and revoke it without changing app roles', function () {
    $member = User::factory()->create();
    $url = route('budgets.action', ['period' => $this->period, 'action' => 'member-save']);
    $this->actingAs($this->owner)->postJson($url, ['user_id' => $member->id, 'role' => 'viewer', 'version' => $this->period->fresh()->version])->assertOk();
    expect($this->budget->memberRole($member))->toBe('viewer');
    $this->postJson($url, ['user_id' => $member->id, 'role' => 'editor', 'version' => $this->period->fresh()->version])->assertOk();
    expect($this->budget->memberRole($member))->toBe('editor')->and($member->fresh()->role)->toBe(UserRole::Member);
    $this->postJson($url, ['user_id' => $member->id, 'role' => 'remove', 'version' => $this->period->fresh()->version])->assertOk();
    expect($this->budget->memberRole($member))->toBeNull();
});

test('personal budgets cannot be shared even through direct requests or malformed memberships', function () {
    $this->budget->update(['scope' => 'personal']);
    $member = User::factory()->create();
    $this->budget->members()->attach($member->id, ['role' => 'editor']);
    $this->actingAs($member)->get(route('budgets.index', ['period' => $this->period->id]))->assertForbidden();
    $this->actingAs($this->owner)->postJson(route('budgets.action', ['period' => $this->period, 'action' => 'member-save']), ['user_id' => $member->id, 'role' => 'viewer', 'version' => 1])->assertUnprocessable();
});

test('household subscription sharing requires explicit inclusion and never includes a members private subscriptions', function () {
    $viewer = User::factory()->create();
    $this->budget->members()->attach($viewer->id, ['role' => 'viewer']);
    Subscription::factory()->for($this->owner)->create(['name' => 'Owner private subscription', 'next_billing_date' => '2026-10-10']);
    Subscription::factory()->for($viewer)->create(['name' => 'Viewer private subscription', 'next_billing_date' => '2026-10-10']);
    $this->actingAs($viewer)->get(route('budgets.index', ['period' => $this->period->id]))->assertOk()->assertDontSee('Owner private subscription')->assertDontSee('Viewer private subscription');
    $this->actingAs($this->owner)->postJson(route('budgets.action', ['period' => $this->period, 'action' => 'settings-save']), ['include_subscriptions' => 1, 'repeat_cycle' => 'none', 'version' => $this->period->fresh()->version])->assertOk();
    $this->actingAs($viewer)->get(route('budgets.index', ['period' => $this->period->id]))->assertOk()->assertSee('Owner private subscription')->assertDontSee('Viewer private subscription');
});

test('guests cannot access budgets or submit budget actions', function () {
    $this->get(route('budgets.index'))->assertRedirect(route('login'));
    $this->post(route('budgets.store'), [])->assertRedirect(route('login'));
});

test('only owners and household editors can rename a budget', function () {
    $viewer = User::factory()->create();
    $editor = User::factory()->create();
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    $this->budget->members()->attach($viewer, ['role' => 'viewer']);
    $this->budget->members()->attach($editor, ['role' => 'editor']);
    $url = route('budgets.action', ['period' => $this->period, 'action' => 'budget-rename']);
    foreach ([$viewer, $admin] as $actor) {
        $this->actingAs($actor)->postJson($url, ['name' => 'Forbidden rename', 'version' => $this->period->fresh()->version])->assertForbidden();
    }
    $this->actingAs($editor)->get(route('budgets.index'))->assertOk()->assertSee('Rename budget');
    $this->postJson($url, ['name' => 'Our household plan', 'version' => $this->period->fresh()->version])->assertOk();
    expect($this->budget->fresh()->name)->toBe('Our household plan');
});
