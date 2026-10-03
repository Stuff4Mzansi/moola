<?php

use App\Models\Subscription;
use App\Models\User;
use App\SubscriptionAnalytics;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->travelTo(Carbon::parse('2026-10-03 12:00:00'));
    User::factory()->superAdmin()->create();
    $this->user = User::factory()->create();
});

test('analytics groups normalized category costs and excludes other users and inactive subscriptions', function () {
    Subscription::factory()->for($this->user)->create(['category' => 'Entertainment', 'amount_cents' => 10000]);
    Subscription::factory()->for($this->user)->create(['category' => 'Entertainment', 'amount_cents' => 12000, 'billing_frequency' => 'yearly']);
    Subscription::factory()->for($this->user)->create(['category' => null, 'amount_cents' => 3000]);
    Subscription::factory()->for($this->user)->paused()->create(['category' => 'Hidden paused', 'amount_cents' => 999999]);
    Subscription::factory()->for($this->user)->cancelled()->create(['category' => 'Hidden cancelled', 'amount_cents' => 999999]);
    Subscription::factory()->create(['category' => 'Private category', 'name' => 'Private service', 'amount_cents' => 999999]);

    $this->actingAs($this->user)->get(route('subscriptions.index'))->assertOk()->assertDontSee('Private service')->assertDontSee('Private category')
        ->assertViewHas('analytics', function (array $analytics): bool {
            expect($analytics['annualCostCents'])->toBe(168000)
                ->and($analytics['categories'][0]['name'])->toBe('Entertainment')
                ->and($analytics['categories'][0]['monthly_cost_cents'])->toBe(11000)
                ->and($analytics['categories'][1]['name'])->toBe('Uncategorised')
                ->and($analytics['statusCounts'])->toBe(['active' => 3, 'paused' => 1, 'cancelled' => 1]);

            return true;
        });
});

test('monthly forecast counts all weekly renewals and excludes earlier payments in the current month', function () {
    Subscription::factory()->for($this->user)->create(['amount_cents' => 1000, 'billing_frequency' => 'weekly', 'next_billing_date' => '2026-10-03']);
    Subscription::factory()->for($this->user)->create(['amount_cents' => 12000, 'billing_frequency' => 'yearly', 'next_billing_date' => '2026-10-01']);

    $this->actingAs($this->user)->get(route('subscriptions.index'))->assertOk()
        ->assertViewHas('analytics', function (array $analytics): bool {
            expect($analytics['monthlyForecast'])->toHaveCount(12)
                ->and($analytics['monthlyForecast'][0])->toBe(['month' => '2026-10', 'label' => 'Oct 2026', 'amount_cents' => 5000, 'payment_count' => 5, 'is_partial' => true])
                ->and($analytics['monthlyForecast'][1]['amount_cents'])->toBe(4000)
                ->and($analytics['monthlyForecast'][11]['month'])->toBe('2027-09')
                ->and($analytics['next7DaysCostCents'])->toBe(1000)
                ->and($analytics['next7DaysPaymentCount'])->toBe(1);

            return true;
        });
});

test('quarterly and yearly payments produce the correct peak forecast month', function () {
    Subscription::factory()->for($this->user)->create(['amount_cents' => 10000, 'next_billing_date' => '2026-10-05']);
    Subscription::factory()->for($this->user)->create(['amount_cents' => 30000, 'billing_frequency' => 'quarterly', 'next_billing_date' => '2026-11-10']);
    Subscription::factory()->for($this->user)->create(['amount_cents' => 120000, 'billing_frequency' => 'yearly', 'next_billing_date' => '2026-12-10']);

    $this->actingAs($this->user)->get(route('subscriptions.index'))->assertOk()
        ->assertViewHas('analytics', function (array $analytics): bool {
            expect($analytics['peakMonth']['month'])->toBe('2026-12')
                ->and($analytics['peakMonth']['amount_cents'])->toBe(130000)
                ->and($analytics['forecastTotalCents'])->toBe(360000)
                ->and($analytics['monthlyForecast'][1]['amount_cents'])->toBe(40000);

            return true;
        });
});

test('largest costs and savings choices compare normalized costs across all active subscriptions', function () {
    $monthly = Subscription::factory()->for($this->user)->create(['name' => 'Monthly service', 'amount_cents' => 5000]);
    $yearly = Subscription::factory()->for($this->user)->create(['name' => 'Annual service', 'amount_cents' => 12000, 'billing_frequency' => 'yearly']);
    Subscription::factory()->count(4)->for($this->user)->create(['amount_cents' => 100]);

    $this->actingAs($this->user)->get(route('subscriptions.index'))->assertOk()
        ->assertSee('What could you save?')->assertSee('Monthly subscription target')
        ->assertViewHas('analytics', function (array $analytics) use ($monthly, $yearly): bool {
            expect($analytics['largestSubscriptions'])->toHaveCount(5)
                ->and($analytics['activeSubscriptions'])->toHaveCount(6)
                ->and($analytics['largestSubscriptions'][0]['id'])->toBe($monthly->id)
                ->and($analytics['largestSubscriptions'][1]['id'])->toBe($yearly->id)
                ->and($analytics['largestSubscriptions'][1]['monthly_cost_cents'])->toBe(1000)
                ->and($analytics['topThreeShare'])->toBeGreaterThan(90.0);

            return true;
        });
});

test('analytics remains based on all owned active subscriptions when the list is filtered', function () {
    Subscription::factory()->for($this->user)->create(['name' => 'Music', 'amount_cents' => 10000]);
    Subscription::factory()->for($this->user)->create(['name' => 'Storage', 'amount_cents' => 5000]);

    $this->actingAs($this->user)->get(route('subscriptions.index', ['search' => 'Music']))->assertOk()
        ->assertViewHas('analytics', fn (array $analytics): bool => $analytics['annualCostCents'] === 180000 && count($analytics['activeSubscriptions']) === 2);
});

test('empty or inactive subscriptions produce zero analytics without division errors', function () {
    $empty = app(SubscriptionAnalytics::class)->build(collect(), CarbonImmutable::today());
    expect($empty['peakMonth'])->toBeNull()
        ->and($empty['annualCostCents'])->toBe(0)
        ->and($empty['topThreeShare'])->toBe(0.0)
        ->and($empty['forecastTotalCents'])->toBe(0);

    Subscription::factory()->for($this->user)->paused()->create();
    $this->actingAs($this->user)->get(route('subscriptions.index'))->assertOk()->assertSee('There are no active subscriptions to analyse.');
});

test('future subscriptions with no renewals in the forecast period have no peak month', function () {
    Subscription::factory()->for($this->user)->create(['next_billing_date' => '2028-01-01']);

    $this->actingAs($this->user)->get(route('subscriptions.index'))->assertOk()
        ->assertSee('No payments in this forecast')
        ->assertViewHas('analytics', fn (array $analytics): bool => $analytics['peakMonth'] === null && $analytics['forecastTotalCents'] === 0);
});

test('analytics safely renders subscription names and category labels', function () {
    Subscription::factory()->for($this->user)->create(['name' => '<script>alert(1)</script>', 'category' => '<img src=x onerror=alert(1)>']);

    $this->actingAs($this->user)->get(route('subscriptions.index'))->assertOk()
        ->assertDontSee('<script>alert(1)</script>', false)->assertDontSee('<img src=x onerror=alert(1)>', false)
        ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false);
});
