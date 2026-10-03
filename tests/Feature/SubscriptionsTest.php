<?php

use App\BillingFrequency;
use App\Models\Subscription;
use App\Models\User;
use App\SubscriptionStatus;
use App\UserRole;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->travelTo(Carbon::parse('2026-10-03 12:00:00'));
    User::factory()->superAdmin()->create();
    $this->user = User::factory()->create();
    $this->subscriptionData = [
        'name' => 'Streaming service',
        'amount' => '159.99',
        'currency' => 'ZAR',
        'billing_frequency' => 'monthly',
        'next_billing_date' => '2026-10-10',
        'status' => 'active',
        'category' => 'Entertainment',
        'website' => 'https://example.com',
        'notes' => 'Family plan',
    ];
});

test('guests cannot access subscription screens or mutations', function () {
    $subscription = Subscription::factory()->for($this->user)->create();

    foreach (['subscriptions.index', 'subscriptions.create'] as $name) {
        $this->get(route($name))->assertRedirect(route('login'));
    }

    $this->get(route('subscriptions.show', $subscription))->assertRedirect(route('login'));
    $this->get(route('subscriptions.edit', $subscription))->assertRedirect(route('login'));
    $this->post(route('subscriptions.store'), $this->subscriptionData)->assertRedirect(route('login'));
    $this->patch(route('subscriptions.update', $subscription), $this->subscriptionData)->assertRedirect(route('login'));
    $this->delete(route('subscriptions.destroy', $subscription))->assertRedirect(route('login'));
    $this->assertDatabaseCount('subscriptions', 1);
});

test('empty list offers adding a first subscription and renders the create form', function () {
    $this->actingAs($this->user)->get(route('subscriptions.index'))->assertOk()
        ->assertSee('Start with your first subscription')->assertViewHas('activeCount', 0);
    $this->get(route('subscriptions.create'))->assertOk()->assertSee('Price per payment');
});

test('subscription creation stores exact cents and assigns the authenticated owner', function (string $amount, int $cents) {
    $this->actingAs($this->user)->post(route('subscriptions.store'), [
        ...$this->subscriptionData,
        'amount' => $amount,
        'user_id' => User::factory()->create()->id,
        'amount_cents' => 1,
    ])->assertRedirect();

    $subscription = Subscription::query()->sole();
    expect($subscription->user_id)->toBe($this->user->id)
        ->and($subscription->amount_cents)->toBe($cents)
        ->and($subscription->billing_frequency)->toBe(BillingFrequency::Monthly)
        ->and($subscription->status)->toBe(SubscriptionStatus::Active);
    $this->get(route('subscriptions.show', $subscription))->assertOk()->assertSee('Family plan');
})->with([['159.99', 15999], ['12.5', 1250], ['12', 1200], ['0.01', 1]]);

test('subscription details can be viewed and edited without changing ownership', function () {
    $subscription = Subscription::factory()->for($this->user)->create();
    $this->actingAs($this->user)->get(route('subscriptions.edit', $subscription))->assertOk();

    $this->patch(route('subscriptions.update', $subscription), [
        ...$this->subscriptionData,
        'name' => 'Updated service',
        'billing_frequency' => 'yearly',
        'user_id' => User::factory()->create()->id,
    ])->assertRedirect(route('subscriptions.show', $subscription));

    expect($subscription->fresh()->name)->toBe('Updated service')
        ->and($subscription->fresh()->billing_frequency)->toBe(BillingFrequency::Yearly)
        ->and($subscription->fresh()->user_id)->toBe($this->user->id);
});

test('subscription amounts reject invalid values and fractional cents', function (string $amount) {
    $this->actingAs($this->user)->post(route('subscriptions.store'), [...$this->subscriptionData, 'amount' => $amount])
        ->assertSessionHasErrors('amount');
    $this->assertDatabaseCount('subscriptions', 0);
})->with(['0', '-1', '1.001', '1e2', '10000000', 'invalid']);

test('subscription fields reject invalid dates statuses currencies and unsafe websites', function () {
    $this->actingAs($this->user)->post(route('subscriptions.store'), [
        ...$this->subscriptionData,
        'name' => '',
        'currency' => 'USD',
        'billing_frequency' => 'daily',
        'next_billing_date' => '2026-02-30',
        'status' => 'unknown',
        'category' => str_repeat('a', 101),
        'website' => 'javascript:alert(1)',
        'notes' => str_repeat('a', 5001),
    ])->assertSessionHasErrors(['name', 'currency', 'billing_frequency', 'next_billing_date', 'status', 'category', 'website', 'notes']);
    $this->assertDatabaseCount('subscriptions', 0);
});

test('optional subscription fields may be omitted', function () {
    $data = $this->subscriptionData;
    unset($data['category'], $data['website'], $data['notes']);

    $this->actingAs($this->user)->post(route('subscriptions.store'), $data)->assertRedirect();
    expect(Subscription::query()->sole()->category)->toBeNull();
});

test('users including administrators cannot access another users subscription', function (UserRole $role) {
    $actor = User::factory()->create(['role' => $role]);
    $subscription = Subscription::factory()->for($this->user)->create(['name' => 'Private service', 'category' => 'Private category']);

    $this->actingAs($actor)->get(route('subscriptions.index'))->assertOk()
        ->assertDontSee('Private service')->assertDontSee('Private category')->assertViewHas('activeCount', 0);
    $this->get(route('subscriptions.show', $subscription))->assertForbidden();
    $this->get(route('subscriptions.edit', $subscription))->assertForbidden();
    $this->patch(route('subscriptions.update', $subscription), $this->subscriptionData)->assertForbidden();
    $this->delete(route('subscriptions.destroy', $subscription))->assertForbidden();

    expect($subscription->fresh()->name)->toBe('Private service');
})->with([UserRole::Member, UserRole::Admin, UserRole::SuperAdmin]);

test('spending summary includes only owned active subscriptions and all scheduled payments', function () {
    foreach ([
        ['weekly', 1000, '2026-10-03'],
        ['monthly', 10000, '2026-10-04'],
        ['quarterly', 30000, '2026-10-05'],
        ['yearly', 120000, '2026-10-06'],
    ] as [$frequency, $cents, $date]) {
        Subscription::factory()->for($this->user)->create(['billing_frequency' => $frequency, 'amount_cents' => $cents, 'next_billing_date' => $date]);
    }

    Subscription::factory()->for($this->user)->paused()->create(['amount_cents' => 999999]);
    Subscription::factory()->for($this->user)->cancelled()->create(['amount_cents' => 999999]);
    Subscription::factory()->create(['amount_cents' => 999999]);

    $this->actingAs($this->user)->get(route('subscriptions.index'))->assertOk()
        ->assertViewHas('activeCount', 4)
        ->assertViewHas('annualCostCents', 412000)
        ->assertViewHas('monthlyCostCents', 34333)
        ->assertViewHas('upcomingCostCents', 165000)
        ->assertViewHas('renewals', fn (Collection $renewals): bool => $renewals->count() === 8);
});

test('search category and status filters work together without changing the summary', function () {
    $match = Subscription::factory()->for($this->user)->create(['name' => 'Music premium', 'category' => 'Entertainment']);
    Subscription::factory()->for($this->user)->paused()->create(['name' => 'Music paused', 'category' => 'Entertainment']);
    Subscription::factory()->for($this->user)->create(['name' => 'Music tool', 'category' => 'Software']);

    $this->actingAs($this->user)->get(route('subscriptions.index', ['search' => 'MUSIC', 'status' => 'active', 'category' => 'Entertainment']))
        ->assertOk()->assertViewHas('activeCount', 2)
        ->assertViewHas('subscriptions', fn (LengthAwarePaginator $subscriptions): bool => $subscriptions->total() === 1 && $subscriptions->first()->is($match));

    $this->get(route('subscriptions.index', ['search' => 'No match']))->assertOk()->assertSee('No subscriptions match your filters.');
});

test('cost sorting compares monthly equivalents rather than individual payment prices', function () {
    $monthly = Subscription::factory()->for($this->user)->create(['amount_cents' => 5000, 'billing_frequency' => 'monthly']);
    $annual = Subscription::factory()->for($this->user)->create(['amount_cents' => 12000, 'billing_frequency' => 'yearly']);

    $this->actingAs($this->user)->get(route('subscriptions.index', ['sort' => 'monthly_cost']))->assertOk()
        ->assertViewHas('subscriptions', fn (LengthAwarePaginator $subscriptions): bool => $subscriptions->first()->is($annual));
    $this->get(route('subscriptions.index', ['sort' => 'monthly_cost', 'direction' => 'desc']))->assertOk()
        ->assertViewHas('subscriptions', fn (LengthAwarePaginator $subscriptions): bool => $subscriptions->first()->is($monthly));
});

test('renewal sorting uses projected dates and places inactive subscriptions last', function () {
    $later = Subscription::factory()->for($this->user)->create(['next_billing_date' => '2026-08-01']);
    $sooner = Subscription::factory()->for($this->user)->create(['next_billing_date' => '2026-10-04']);
    $paused = Subscription::factory()->for($this->user)->paused()->create(['next_billing_date' => '2026-01-01']);

    $this->actingAs($this->user)->get(route('subscriptions.index'))->assertOk()
        ->assertViewHas('subscriptions', fn (LengthAwarePaginator $subscriptions): bool => $subscriptions->getCollection()->pluck('id')->all() === [$sooner->id, $later->id, $paused->id]);
});

test('subscription lists paginate while retaining filters', function () {
    Subscription::factory()->count(16)->for($this->user)->create(['category' => 'Software']);

    $this->actingAs($this->user)->get(route('subscriptions.index', ['category' => 'Software', 'page' => 2]))->assertOk()
        ->assertViewHas('subscriptions', fn (LengthAwarePaginator $subscriptions): bool => $subscriptions->total() === 16 && $subscriptions->count() === 1 && str_contains($subscriptions->url(1), 'category=Software'));
});

test('upcoming periods include today and exclude dates beyond the selected range', function () {
    Subscription::factory()->for($this->user)->create(['next_billing_date' => '2026-10-03']);
    Subscription::factory()->for($this->user)->create(['next_billing_date' => '2026-10-09']);
    Subscription::factory()->for($this->user)->create(['next_billing_date' => '2026-10-10']);
    Subscription::factory()->for($this->user)->create(['next_billing_date' => '2026-11-02']);

    $this->actingAs($this->user)->get(route('subscriptions.index', ['renewal_window' => 7]))->assertOk()
        ->assertViewHas('renewals', fn (Collection $renewals): bool => $renewals->count() === 2);
    $this->get(route('subscriptions.index', ['renewal_window' => 30]))->assertOk()
        ->assertViewHas('renewals', fn (Collection $renewals): bool => $renewals->count() === 3);
});

test('monthly renewals retain the original billing day after short months', function () {
    $subscription = Subscription::factory()->for($this->user)->create(['next_billing_date' => '2026-01-31']);

    $dates = $subscription->renewalsBetween(CarbonImmutable::parse('2026-02-01'), CarbonImmutable::parse('2026-04-30'));
    expect(array_map(fn (CarbonImmutable $date): string => $date->toDateString(), $dates))
        ->toBe(['2026-02-28', '2026-03-31', '2026-04-30']);
    expect($subscription->nextRenewalDate(CarbonImmutable::parse('2026-03-01'))->toDateString())->toBe('2026-03-31');
});

test('yearly renewals handle leap years without losing the original day', function () {
    $subscription = Subscription::factory()->for($this->user)->create(['next_billing_date' => '2024-02-29', 'billing_frequency' => 'yearly']);

    expect($subscription->nextRenewalDate(CarbonImmutable::parse('2025-02-01'))->toDateString())->toBe('2025-02-28')
        ->and($subscription->nextRenewalDate(CarbonImmutable::parse('2028-02-01'))->toDateString())->toBe('2028-02-29');
});

test('quarterly and old weekly billing dates project to the next occurrence', function () {
    $quarterly = Subscription::factory()->for($this->user)->create(['next_billing_date' => '2026-01-31', 'billing_frequency' => 'quarterly']);
    $weekly = Subscription::factory()->for($this->user)->create(['next_billing_date' => '2020-01-04', 'billing_frequency' => 'weekly']);

    expect($quarterly->nextRenewalDate(CarbonImmutable::parse('2026-05-01'))->toDateString())->toBe('2026-07-31')
        ->and($weekly->nextRenewalDate()->toDateString())->toBe('2026-10-03');
});

test('all billing frequencies produce the expected cost equivalents', function (BillingFrequency $frequency, int $annual, int $monthly) {
    $subscription = Subscription::factory()->for($this->user)->create(['billing_frequency' => $frequency, 'amount_cents' => 1200]);

    expect($subscription->annualCostCents())->toBe($annual)
        ->and($subscription->monthlyCostCents())->toBe($monthly);
})->with([
    [BillingFrequency::Weekly, 62400, 5200],
    [BillingFrequency::Monthly, 14400, 1200],
    [BillingFrequency::Quarterly, 4800, 400],
    [BillingFrequency::Yearly, 1200, 100],
]);

test('pausing and cancelling removes subscriptions from forecasts but retains their records', function (string $status) {
    $subscription = Subscription::factory()->for($this->user)->create();

    $this->actingAs($this->user)->patch(route('subscriptions.update', $subscription), [...$this->subscriptionData, 'status' => $status])
        ->assertRedirect(route('subscriptions.show', $subscription));
    $this->get(route('subscriptions.index'))->assertOk()->assertViewHas('activeCount', 0)->assertViewHas('upcomingCostCents', 0);
    expect($subscription->fresh()->nextRenewalDate())->toBeNull()
        ->and($subscription->fresh()->renewalsBetween(CarbonImmutable::today(), CarbonImmutable::today()->addDays(29)))->toBe([]);
    $this->assertDatabaseCount('subscriptions', 1);
})->with(['paused', 'cancelled']);

test('reactivation requires an upcoming billing date', function () {
    $subscription = Subscription::factory()->for($this->user)->paused()->create();

    $this->actingAs($this->user)->patch(route('subscriptions.update', $subscription), [...$this->subscriptionData, 'next_billing_date' => '2026-10-02'])
        ->assertSessionHasErrors('next_billing_date');
    expect($subscription->fresh()->status)->toBe(SubscriptionStatus::Paused);

    $this->patch(route('subscriptions.update', $subscription), [...$this->subscriptionData, 'next_billing_date' => '2026-10-03'])
        ->assertRedirect(route('subscriptions.show', $subscription));
    expect($subscription->fresh()->status)->toBe(SubscriptionStatus::Active);
});

test('subscription deletion removes the record and its forecast', function () {
    $subscription = Subscription::factory()->for($this->user)->create();

    $this->actingAs($this->user)->get(route('subscriptions.show', $subscription))->assertOk()->assertSee('Keep subscription');
    $this->delete(route('subscriptions.destroy', $subscription))->assertRedirect(route('subscriptions.index'));
    $this->assertModelMissing($subscription);
    $this->get(route('subscriptions.index'))->assertOk()->assertViewHas('activeCount', 0)->assertViewHas('annualCostCents', 0);
});

test('subscription text is escaped when displayed', function () {
    $subscription = Subscription::factory()->for($this->user)->create(['notes' => '<script>alert(1)</script>']);

    $this->actingAs($this->user)->get(route('subscriptions.show', $subscription))->assertOk()
        ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)->assertDontSee('<script>alert(1)</script>', false);
});

test('search and category filters treat zero as a meaningful value', function () {
    $match = Subscription::factory()->for($this->user)->create(['name' => 'Plan 0', 'category' => '0']);
    Subscription::factory()->for($this->user)->create(['name' => 'Plan 1', 'category' => '0']);
    Subscription::factory()->for($this->user)->create(['name' => 'Another 0', 'category' => 'Software']);

    $this->actingAs($this->user)->get(route('subscriptions.index', ['search' => '0', 'category' => '0']))->assertOk()
        ->assertViewHas('subscriptions', fn (LengthAwarePaginator $subscriptions): bool => $subscriptions->total() === 1 && $subscriptions->first()->is($match))
        ->assertViewHas('categories', fn (Collection $categories): bool => $categories->contains('0'));
});

test('deleting a household user also removes their subscriptions', function () {
    $owner = User::query()->where('role', UserRole::SuperAdmin->value)->sole();
    $subscription = Subscription::factory()->for($this->user)->create();

    $this->actingAs($owner)->delete(route('admin.users.destroy', $this->user))->assertRedirect(route('admin.users.index'));

    $this->assertModelMissing($this->user);
    $this->assertModelMissing($subscription);
});
