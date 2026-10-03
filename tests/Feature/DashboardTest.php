<?php

use App\Models\Subscription;
use App\Models\User;
use App\UserRole;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->travelTo(Carbon::parse('2026-10-03 12:00:00'));
    User::factory()->superAdmin()->create();
    $this->user = User::factory()->create();
});

test('dashboard shows subscription estimates chart and correctly ordered renewals', function () {
    $weekly = Subscription::factory()->for($this->user)->create(['name' => 'Weekly service', 'amount_cents' => 1000, 'billing_frequency' => 'weekly', 'next_billing_date' => '2026-10-03']);
    $monthly = Subscription::factory()->for($this->user)->create(['name' => 'Monthly service', 'amount_cents' => 10000, 'next_billing_date' => '2026-10-10']);
    $yearly = Subscription::factory()->for($this->user)->create(['name' => 'Yearly service', 'amount_cents' => 12000, 'billing_frequency' => 'yearly', 'next_billing_date' => '2026-10-04']);
    Subscription::factory()->for($this->user)->paused()->create(['amount_cents' => 999999]);

    $this->actingAs($this->user)->get(route('dashboard'))->assertOk()
        ->assertSee('Subscription overview')->assertSee('dashboard-category-chart-title', false)
        ->assertSee('Largest recurring cost')->assertSee('Explore forecasts and potential savings')
        ->assertViewHas('activeCount', 3)->assertViewHas('monthlyCostCents', 15333)
        ->assertViewHas('analytics', fn (array $analytics): bool => $analytics['annualCostCents'] === 184000 && $analytics['next7DaysCostCents'] === 13000 && $analytics['next7DaysPaymentCount'] === 2)
        ->assertViewHas('renewals', fn (Collection $renewals): bool => $renewals->pluck('subscription.id')->all() === [$weekly->id, $yearly->id, $monthly->id]);
});

test('dashboard analytics stays private even for administrators', function (UserRole $role) {
    $actor = User::factory()->create(['role' => $role]);
    Subscription::factory()->for($this->user)->create(['name' => 'Private service', 'category' => 'Private category']);

    $this->actingAs($actor)->get(route('dashboard'))->assertOk()
        ->assertDontSee('Private service')->assertDontSee('Private category')
        ->assertViewHas('activeCount', 0)->assertViewHas('monthlyCostCents', 0)
        ->assertViewHas('renewals', fn (Collection $renewals): bool => $renewals->isEmpty());
})->with([UserRole::Member, UserRole::Admin, UserRole::SuperAdmin]);

test('dashboard and subscription page use identical analytics', function () {
    Subscription::factory()->count(3)->for($this->user)->create();

    $dashboard = $this->actingAs($this->user)->get(route('dashboard'))->assertOk();
    $subscriptions = $this->get(route('subscriptions.index'))->assertOk()->assertSee('category-chart-title', false);

    expect($dashboard->viewData('analytics'))->toBe($subscriptions->viewData('analytics'));
});

test('dashboard offers adding the first subscription without rendering an empty chart', function () {
    $this->actingAs($this->user)->get(route('dashboard'))->assertOk()->assertSee('Track your first subscription')
        ->assertSee(route('subscriptions.create'), false)->assertDontSee('dashboard-category-chart-title', false);
});

test('dashboard excludes inactive subscriptions and renewals beyond thirty days', function () {
    Subscription::factory()->for($this->user)->paused()->create(['name' => 'Paused service']);
    Subscription::factory()->for($this->user)->cancelled()->create(['name' => 'Cancelled service']);

    $this->actingAs($this->user)->get(route('dashboard'))->assertOk()->assertSee('No active subscriptions')->assertViewHas('activeCount', 0);

    Subscription::factory()->for($this->user)->create(['next_billing_date' => '2026-11-02']);
    $this->get(route('dashboard'))->assertOk()->assertSee('No active subscriptions are due in the next 30 days.')
        ->assertViewHas('activeCount', 1)->assertViewHas('renewals', fn (Collection $renewals): bool => $renewals->isEmpty());
});

test('dashboard limits renewal previews without excluding payments from the totals', function () {
    for ($index = 0; $index < 6; $index++) {
        Subscription::factory()->for($this->user)->create(['amount_cents' => 1000, 'next_billing_date' => now()->addDays($index)->toDateString()]);
    }

    $this->actingAs($this->user)->get(route('dashboard'))->assertOk()
        ->assertViewHas('renewals', fn (Collection $renewals): bool => $renewals->count() === 5)
        ->assertViewHas('analytics', fn (array $analytics): bool => $analytics['next7DaysCostCents'] === 6000 && $analytics['next7DaysPaymentCount'] === 6);
});
