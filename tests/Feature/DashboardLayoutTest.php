<?php

use App\DashboardLayout;
use App\Models\User;
use App\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    User::factory()->superAdmin()->create();
    $this->user = User::factory()->create();
    $this->layout = app(DashboardLayout::class)->defaults($this->user);
});

test('new accounts get the default dashboard and only authorized widgets', function () {
    $response = $this->actingAs($this->user)->get(route('dashboard'))->assertOk()
        ->assertSee('Customize detailed views')->assertViewHas('dashboardLayout', $this->layout)
        ->assertDontSee('Needs attention')
        ->assertDontSee('data-layout-widget="household"', false)
        ->assertDontSee('data-layout-widget="budgets"', false)
        ->assertDontSee('data-layout-widget="category-trends"', false)
        ->assertDontSee('Category spending trends')
        ->assertSee('Budget at a glance');

    expect(substr_count($response->getContent(), 'id="dashboard-budgets-title"'))->toBe(1)
        ->and(strpos($response->getContent(), 'id="dashboard-budgets-title"'))->toBeLessThan(strpos($response->getContent(), 'x-ref="dashboardDetails"'));

    expect(collect($this->layout)->firstWhere('id', 'goal-forecast')['visible'])->toBeTrue()
        ->and(collect($this->layout)->firstWhere('id', 'spending-mix')['visible'])->toBeFalse()
        ->and(collect($this->layout)->pluck('id'))->not->toContain('budgets')->not->toContain('budget-pace');

    $admin = User::factory()->admin()->create();
    $this->actingAs($admin)->get(route('dashboard'))->assertOk()->assertSee('data-layout-widget="household"', false)
        ->assertSee('data-layout-widget="goal-forecast"', false)
        ->assertSee('data-layout-widget="spending-mix"', false);
});

test('layouts persist ordering visibility and tile sizes only for the current account', function () {
    $other = User::factory()->create();
    $layout = array_reverse($this->layout);
    $layout[0] = [...$layout[0], 'visible' => false, 'width' => 'small', 'height' => 'compact'];
    $layout[1] = [...$layout[1], 'width' => 'medium', 'height' => 'tall'];

    $this->actingAs($this->user)->putJson(route('dashboard.layout.update'), ['widgets' => $layout, 'user_id' => $other->id])
        ->assertOk()->assertJsonPath('widgets', $layout);

    expect($this->user->fresh()->dashboard_layout)->toBe($layout)
        ->and($other->fresh()->dashboard_layout)->toBeNull();

    $this->get(route('dashboard'))->assertOk()->assertViewHas('dashboardLayout', $layout)
        ->assertSee('data-layout-widget="'.$layout[0]['id'].'" data-width="small" data-height="compact" style="order: 0"', false);
});

test('all widgets can be hidden and the default layout can be restored', function () {
    $hidden = array_map(fn (array $widget): array => [...$widget, 'visible' => false], $this->layout);
    $this->actingAs($this->user)->putJson(route('dashboard.layout.update'), ['widgets' => $hidden])->assertOk();
    $this->get(route('dashboard'))->assertOk()->assertViewHas('dashboardLayout', $hidden)->assertSee('No detailed views selected');
    $this->putJson(route('dashboard.layout.update'), ['widgets' => $this->layout])->assertOk();
    expect($this->user->fresh()->dashboard_layout)->toBe($this->layout);
});

test('invalid layouts are rejected without changing saved preferences', function (string $invalid) {
    $layout = $this->layout;
    match ($invalid) {
        'duplicate' => $layout[1]['id'] = $layout[0]['id'],
        'unauthorized' => $layout[0]['id'] = 'household',
        'missing' => array_pop($layout),
        'width' => $layout[0]['width'] = 'huge',
        'height' => $layout[0]['height'] = ['compact'],
        'visibility' => $layout[0]['visible'] = 'yes',
        'extra' => $layout[0]['html'] = '<script>alert(1)</script>',
    };

    $this->actingAs($this->user)->putJson(route('dashboard.layout.update'), ['widgets' => $layout])->assertUnprocessable();
    expect($this->user->fresh()->dashboard_layout)->toBeNull();
})->with(['duplicate', 'unauthorized', 'missing', 'width', 'height', 'visibility', 'extra']);

test('saved layouts recover unknown duplicate and invalid entries and append new widgets', function () {
    $this->user->dashboard_layout = [
        ['id' => 'subscriptions', 'visible' => false, 'width' => 'medium', 'height' => 'regular'],
        ['id' => 'subscriptions', 'visible' => true],
        ['id' => 'category-trends', 'visible' => true, 'width' => 'wide', 'height' => 'auto'],
        ['id' => 'obsolete'],
        ['id' => 'budget-pace', 'visible' => true, 'width' => 'wide', 'height' => 'auto'],
        ['id' => 'budgets', 'visible' => 'no', 'width' => 'huge', 'height' => 'negative'],
        'invalid',
    ];
    $this->user->save();
    $layout = app(DashboardLayout::class)->build($this->user);
    expect($layout)->toHaveCount(count($this->layout))
        ->and($layout[0])->toBe(['id' => 'subscriptions', 'visible' => false, 'width' => 'medium', 'height' => 'regular'])
        ->and($layout[1]['id'])->toBe('goals')
        ->and(array_column($layout, 'id'))->toContain('goals')->not->toContain('obsolete')->not->toContain('budgets')->not->toContain('budget-pace');
});

test('role changes remove previously saved administrator widgets', function () {
    $admin = User::factory()->admin()->create();
    $admin->dashboard_layout = app(DashboardLayout::class)->defaults($admin);
    $admin->role = UserRole::Member;
    $admin->save();
    $this->actingAs($admin)->get(route('dashboard'))->assertOk()->assertDontSee('data-layout-widget="household"', false)
        ->assertViewHas('dashboardLayout', fn (array $layout): bool => ! in_array('household', array_column($layout, 'id'), true));
});

test('guests cannot save dashboard preferences', function () {
    $this->putJson(route('dashboard.layout.update'), ['widgets' => $this->layout])->assertUnauthorized();
});
