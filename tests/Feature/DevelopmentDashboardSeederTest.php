<?php

use App\Models\Budget;
use App\Models\BudgetPeriod;
use App\Models\BudgetRecurringCharge;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\DevelopmentDashboardSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->travelTo(CarbonImmutable::parse('2026-10-03 12:00:00'));
});

test('development dashboard seeder creates repeatable dashboard and budget scenarios', function (): void {
    $owner = User::factory()->superAdmin()->create();
    $seeder = new DevelopmentDashboardSeeder;

    $seeder->run();
    $periodIds = BudgetPeriod::query()
        ->whereHas('budget', fn ($query) => $query->where('name', 'like', 'Demo - %'))
        ->pluck('id');
    $recurringChargeCount = BudgetRecurringCharge::query()->whereIn('budget_period_id', $periodIds)->count();
    $seeder->run();

    expect(Budget::query()->where('user_id', $owner->id)->count())->toBe(4)
        ->and(BudgetPeriod::query()->whereHas('budget', fn ($query) => $query->where('name', 'Demo - Period explorer'))->count())->toBe(3)
        ->and(BudgetRecurringCharge::query()->whereIn('budget_period_id', $periodIds)->count())->toBe($recurringChargeCount);

    $response = $this->actingAs($owner)->get(route('dashboard'))->assertOk()
        ->assertSee('Demo - Steady pace')
        ->assertSee('Demo - Over budget')
        ->assertSee('Demo - Income not set')
        ->assertSee('Demo - Period explorer');

    expect($response->viewData('budgetOverview')['totalBudgets'])->toBe(4);
});

test('development dashboard seeder refuses to run in production', function (): void {
    app()['env'] = 'production';

    expect(fn () => (new DevelopmentDashboardSeeder)->run())
        ->toThrow(RuntimeException::class, 'Development dashboard data can only be seeded');

    expect(Budget::query()->count())->toBe(0);
});
