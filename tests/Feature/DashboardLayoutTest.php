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
    $this->actingAs($this->user)->get(route('dashboard'))->assertOk()
        ->assertSee('Customize dashboard')->assertViewHas('dashboardLayout', $this->layout)
        ->assertDontSee('data-layout-widget="household"', false);

    expect(collect($this->layout)->firstWhere('id', 'goal-forecast')['visible'])->toBeTrue()
        ->and(collect($this->layout)->firstWhere('id', 'budget-pace')['visible'])->toBeTrue()
        ->and(collect($this->layout)->firstWhere('id', 'spending-mix')['visible'])->toBeFalse();

    $admin = User::factory()->admin()->create();
    $this->actingAs($admin)->get(route('dashboard'))->assertOk()->assertSee('data-layout-widget="household"', false)
        ->assertSee('data-layout-widget="goal-forecast"', false)
        ->assertSee('data-layout-widget="budget-pace"', false)
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
        ->assertSee('data-layout-widget="subscriptions" data-width="small" data-height="compact"  hidden', false);
});

test('all widgets can be hidden and the default layout can be restored', function () {
    $hidden = array_map(fn (array $widget): array => [...$widget, 'visible' => false], $this->layout);
    $this->actingAs($this->user)->putJson(route('dashboard.layout.update'), ['widgets' => $hidden])->assertOk();
    $this->get(route('dashboard'))->assertOk()->assertViewHas('dashboardLayout', $hidden)->assertSee('Your dashboard is clear');
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
        ['id' => 'obsolete'],
        ['id' => 'budgets', 'visible' => 'no', 'width' => 'huge', 'height' => 'negative'],
        'invalid',
    ];
    $this->user->save();
    $layout = app(DashboardLayout::class)->build($this->user);
    expect($layout)->toHaveCount(count($this->layout))
        ->and($layout[0])->toBe(['id' => 'subscriptions', 'visible' => false, 'width' => 'medium', 'height' => 'regular'])
        ->and($layout[1])->toBe(['id' => 'budgets', 'visible' => true, 'width' => 'wide', 'height' => 'auto'])
        ->and(array_column($layout, 'id'))->toContain('goals')->not->toContain('obsolete');
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
