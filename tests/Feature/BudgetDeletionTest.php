<?php

use App\Models\Budget;
use App\Models\BudgetCategory;
use App\Models\BudgetCommitment;
use App\Models\BudgetGroup;
use App\Models\BudgetIncome;
use App\Models\BudgetPeriod;
use App\Models\BudgetTransaction;
use App\Models\Subscription;
use App\Models\User;
use App\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->superAdmin = User::factory()->superAdmin()->create();
    $this->owner = User::factory()->create();
    $this->budget = Budget::factory()->create(['user_id' => $this->owner->id, 'name' => 'Private plan']);
    $this->period = BudgetPeriod::factory()->create(['budget_id' => $this->budget->id]);
});

test('administrators can delete any budget and its children without deleting subscriptions or other budgets', function (UserRole $role, string $scope) {
    $actor = $role === UserRole::SuperAdmin ? $this->superAdmin : User::factory()->create(['role' => $role]);
    $this->budget->update(['scope' => $scope]);
    $otherBudget = Budget::factory()->create(['user_id' => $this->owner->id]);
    $subscription = Subscription::factory()->for($this->owner)->create();
    $group = BudgetGroup::factory()->create(['budget_period_id' => $this->period->id]);
    $category = BudgetCategory::factory()->create(['budget_period_id' => $this->period->id, 'budget_group_id' => $group->id]);
    $income = BudgetIncome::factory()->create(['budget_period_id' => $this->period->id]);
    $commitment = BudgetCommitment::factory()->create(['budget_period_id' => $this->period->id, 'subscription_id' => $subscription->id]);
    $transaction = BudgetTransaction::factory()->create(['budget_period_id' => $this->period->id, 'budget_category_id' => $category->id, 'budget_commitment_id' => $commitment->id]);
    $transaction->delete();
    $this->budget->members()->attach($actor, ['role' => 'viewer']);
    $this->actingAs($actor)->delete(route('admin.budgets.destroy', $this->budget))->assertRedirect(route('admin.budgets.index'));
    foreach ([$this->budget, $this->period, $group, $category, $income, $commitment, $transaction] as $model) {
        $this->assertDatabaseMissing($model->getTable(), ['id' => $model->id]);
    }
    $this->assertDatabaseMissing('budget_members', ['budget_id' => $this->budget->id]);
    $this->assertModelExists($subscription);
    $this->assertModelExists($otherBudget);
    $this->assertModelExists($this->owner);
})->with([UserRole::Admin, UserRole::SuperAdmin])->with(['personal', 'household']);

test('owners and household editors cannot use administrator budget deletion', function (string $access) {
    $actor = $this->owner;
    if ($access !== 'owner') {
        $actor = User::factory()->create();
        $this->budget->update(['scope' => 'household']);
        $this->budget->members()->attach($actor, ['role' => $access]);
    }
    $this->actingAs($actor)->get(route('admin.budgets.index'))->assertForbidden();
    $this->delete(route('admin.budgets.destroy', $this->budget))->assertForbidden();
    $this->assertModelExists($this->budget);
})->with(['owner', 'viewer', 'editor']);

test('guests cannot manage or delete budgets', function () {
    $this->get(route('admin.budgets.index'))->assertRedirect(route('login'));
    $this->delete(route('admin.budgets.destroy', $this->budget))->assertRedirect(route('login'));
    $this->assertModelExists($this->budget);
});

test('budget administration exposes deletion metadata without financial details or financial access', function () {
    BudgetIncome::factory()->create(['budget_period_id' => $this->period->id, 'name' => 'Confidential income source', 'expected_cents' => 12345678]);
    BudgetTransaction::factory()->create(['budget_period_id' => $this->period->id, 'description' => 'Confidential expense']);
    $response = $this->actingAs($this->superAdmin)->get(route('admin.budgets.index'))->assertOk()->assertSee('Private plan')->assertSee($this->owner->name)->assertDontSee('Confidential income source')->assertDontSee('Confidential expense');
    expect($response->viewData('budgets')->first()->getAttributes())->not->toHaveKey('include_subscriptions');
    $this->get(route('budgets.index', ['period' => $this->period->id]))->assertForbidden();
    $document = new DOMDocument;
    @$document->loadHTML($response->getContent());
    $xpath = new DOMXPath($document);
    expect($xpath->query('//form[input[@name="_method" and @value="DELETE"] and @data-confirm]')->length)->toBe(1);
    expect($xpath->query('//dialog[@id="confirm-action"]')->length)->toBe(1);
});

test('destructive budget actions all have modal confirmation metadata', function () {
    $this->budget->update(['scope' => 'household']);
    $this->budget->members()->attach($this->superAdmin, ['role' => 'viewer']);
    BudgetGroup::factory()->create(['budget_period_id' => $this->period->id]);
    $category = BudgetCategory::factory()->create(['budget_period_id' => $this->period->id]);
    BudgetIncome::factory()->create(['budget_period_id' => $this->period->id]);
    BudgetTransaction::factory()->create(['budget_period_id' => $this->period->id, 'budget_category_id' => $category->id]);
    $response = $this->actingAs($this->owner)->get(route('budgets.index', ['period' => $this->period->id]))->assertOk();
    $document = new DOMDocument;
    @$document->loadHTML($response->getContent());
    $xpath = new DOMXPath($document);
    foreach (['group-remove', 'category-remove', 'income-remove', 'expense-remove'] as $action) {
        $forms = $xpath->query('//form[contains(@action,"/'.$action.'")]');
        expect($forms->length)->toBeGreaterThan(0);
        foreach ($forms as $form) {
            expect($form->getAttribute('data-confirm'))->not->toBe('');
        }
    }
    expect($xpath->query('//form[input[@name="role" and @value="remove"] and @data-confirm]')->length)->toBe(1);
    expect($xpath->query('//dialog[@id="confirm-action"]')->length)->toBe(1);
    $response = $this->actingAs($this->superAdmin)->get(route('budgets.index', ['period' => $this->period->id]))->assertOk()->assertSee('Delete budget');
    expect($response->getContent())->toContain(route('admin.budgets.destroy', $this->budget));
});

test('user deletion uses the shared confirmation modal and super admin protection remains intact', function () {
    $response = $this->actingAs($this->superAdmin)->get(route('admin.users.index'))->assertOk();
    $document = new DOMDocument;
    @$document->loadHTML($response->getContent());
    $xpath = new DOMXPath($document);
    $forms = $xpath->query('//form[input[@name="_method" and @value="DELETE"]]');
    expect($forms->length)->toBeGreaterThan(0);
    foreach ($forms as $form) {
        expect($form->getAttribute('data-confirm'))->not->toBe('');
    }
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    $this->actingAs($admin)->delete(route('admin.users.destroy', $this->superAdmin))->assertForbidden();
});
