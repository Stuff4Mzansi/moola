<?php

use App\Models\Asset;
use App\Models\AssetValuation;
use App\Models\Debt;
use App\Models\DebtPayment;
use App\Models\Liability;
use App\Models\NetWorthSnapshot;
use App\Models\SavingsGoal;
use App\Models\User;
use App\NetWorthWorkspace;
use App\UserRole;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-04 12:00:00'));
    User::factory()->superAdmin()->create();
    $this->owner = User::factory()->create();
    $this->actingAs($this->owner);
    $this->asset = Asset::factory()->create(['user_id' => $this->owner->id, 'name' => 'Private bank account', 'kind' => 'bank']);
    $this->value = AssetValuation::factory()->create(['asset_id' => $this->asset->id, 'amount_cents' => 200000, 'date' => '2026-10-01']);
});

function assetDetails(array $changes = []): array
{
    return [...['name' => 'Savings account', 'kind' => 'bank', 'institution' => 'My bank', 'amount' => '1000.50', 'date' => '2026-10-04', 'form_kind' => 'asset'], ...$changes];
}

function assetValueData(array $changes = []): array
{
    return [...['amount' => '3000', 'date' => '2026-10-04', 'form_kind' => 'value'], ...$changes];
}

test('assets can be added edited and valued from one page with correct monetary validation', function () {
    $this->post(route('net-worth.assets.store'), assetDetails())->assertRedirect();
    $asset = Asset::query()->where('name', 'Savings account')->sole();
    expect($asset->user_id)->toBe($this->owner->id)->and($asset->valuations()->sole()->amount_cents)->toBe(100050);
    $this->put(route('net-worth.assets.update', $asset), assetDetails(['name' => 'Updated account']))->assertRedirect();
    $this->get(route('net-worth.index', ['tab' => 'assets']))->assertOk()->assertSee('Updated account')->assertSee('Update value')->assertSee('data-confirm-title="Remove asset?"', false);
    foreach ([['amount' => '-1'], ['amount' => '1.001'], ['date' => '2026-10-05'], ['kind' => 'loan']] as $invalid) {
        $this->postJson(route('net-worth.assets.store'), assetDetails($invalid))->assertUnprocessable();
    }
});

test('manual liabilities use liability types and reduce net worth without debt payments', function () {
    $payload = ['name' => 'Credit card', 'kind' => 'credit_card', 'institution' => 'My bank', 'amount' => '500', 'date' => '2026-10-04', 'form_kind' => 'liability'];

    $this->post(route('net-worth.liabilities.store'), $payload)->assertRedirect()->assertSessionHasNoErrors();

    $liability = Liability::query()->where('name', 'Credit card')->sole();
    expect($liability->kind)->toBe('credit_card')
        ->and($liability->valuations()->sole()->amount_cents)->toBe(50000)
        ->and(app(NetWorthWorkspace::class)->build($this->owner)['netWorth'])->toBe(150000);

    $this->postJson(route('net-worth.liabilities.store'), [...$payload, 'kind' => 'bank'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('kind');

    $this->get(route('net-worth.index'))
        ->assertOk()
        ->assertSee('Credit card')
        ->assertSee('Mortgage')
        ->assertSee('Other liability');
});

test('current values use the newest date and same day updates do not duplicate balances', function () {
    $this->post(route('net-worth.values.store', $this->asset), assetValueData())->assertRedirect();
    $this->post(route('net-worth.values.store', $this->asset), assetValueData(['amount' => '3100']))->assertRedirect()->assertSessionHasNoErrors();
    $this->post(route('net-worth.values.store', $this->asset), assetValueData(['amount' => '1000', 'date' => '2026-09-01']))->assertRedirect();
    expect($this->asset->valuations()->count())->toBe(3)->and(app(NetWorthWorkspace::class)->build($this->owner)['assetTotal'])->toBe(310000);
    $this->post(route('net-worth.values.store', $this->asset), assetValueData(['valuation_id' => $this->value->id, 'amount' => '2100', 'date' => '2026-10-01']))->assertRedirect();
    expect($this->value->fresh()->amount_cents)->toBe(210000);
    $this->postJson(route('net-worth.values.store', $this->asset), assetValueData(['valuation_id' => $this->value->id, 'date' => '2026-10-04']))->assertUnprocessable();
});

test('net worth includes debt principal and never adds savings goals as extra assets', function () {
    config(['features.debt_tracking' => true]);
    $debt = Debt::factory()->create(['user_id' => $this->owner->id, 'opening_balance_cents' => 100000, 'balance_date' => '2026-10-01']);
    DebtPayment::factory()->create(['debt_id' => $debt->id, 'amount_cents' => 10000, 'interest_cents' => 1000, 'date' => '2026-10-03']);
    SavingsGoal::factory()->create(['user_id' => $this->owner->id, 'opening_cents' => 999999]);
    $data = app(NetWorthWorkspace::class)->build($this->owner);
    expect($data['assetTotal'])->toBe(200000)->and($data['debtTotal'])->toBe(91000)->and($data['netWorth'])->toBe(109000);
    $this->get(route('net-worth.index'))->assertOk()->assertSee('R 1,090.00')->assertSee('Savings goals earmark money');
    $this->get(route('dashboard'))->assertOk()->assertViewHas('netWorthOverview', fn (array $data): bool => $data['netWorth'] === 109000);
});

test('negative net worth zero asset balances and stale values have actionable summaries', function () {
    config(['features.debt_tracking' => true]);
    $this->value->update(['amount_cents' => 0, 'date' => '2026-08-01']);
    Debt::factory()->create(['user_id' => $this->owner->id, 'opening_balance_cents' => 50000, 'balance_date' => '2026-10-01']);
    $data = app(NetWorthWorkspace::class)->build($this->owner);
    expect($data['netWorth'])->toBe(-50000)->and($data['stale'])->toBe(1)->and($data['groups']->sole()['share'])->toBe(0);
    $this->get(route('net-worth.index'))->assertOk()->assertSee('R -500.00')->assertSee('Update values');
    $this->get(route('dashboard'))->assertOk()->assertSee('Refresh their values');
});

test('assets valuations snapshots and dashboard totals remain private for every role', function (UserRole $role) {
    $actor = User::factory()->create(['role' => $role]);
    $snapshot = NetWorthSnapshot::factory()->create(['user_id' => $this->owner->id]);
    $this->actingAs($actor)->get(route('net-worth.index'))->assertOk()->assertDontSee('Private bank account')->assertViewHas('netWorth', 0);
    $this->get(route('dashboard'))->assertOk()->assertViewHas('netWorthOverview', fn (array $data): bool => $data['assetTotal'] === 0);
    $this->putJson(route('net-worth.assets.update', $this->asset), assetDetails())->assertForbidden();
    $this->postJson(route('net-worth.values.store', $this->asset), assetValueData())->assertForbidden();
    $this->delete(route('net-worth.assets.destroy', $this->asset))->assertForbidden();
    $this->delete(route('net-worth.snapshots.destroy', $snapshot))->assertForbidden();
})->with([UserRole::Member, UserRole::Admin, UserRole::SuperAdmin]);

test('backdated snapshots use asset and debt records through that date and remain immutable', function () {
    config(['features.debt_tracking' => true]);
    $this->post(route('net-worth.values.store', $this->asset), assetValueData())->assertRedirect();
    $debt = Debt::factory()->create(['user_id' => $this->owner->id, 'name' => 'Loan', 'opening_balance_cents' => 100000, 'balance_date' => '2026-10-01']);
    DebtPayment::factory()->create(['debt_id' => $debt->id, 'amount_cents' => 50000, 'interest_cents' => 1000, 'date' => '2026-10-04']);
    $this->post(route('net-worth.snapshots.store'), ['date' => '2026-10-02'])->assertRedirect();
    $snapshot = NetWorthSnapshot::query()->sole();
    expect($snapshot->assets_cents)->toBe(200000)->and($snapshot->debts_cents)->toBe(100000)->and($snapshot->net_worth_cents)->toBe(100000)->and($snapshot->details['assets'][0]['name'])->toBe('Private bank account');
    $this->value->update(['amount_cents' => 150000]);
    $debt->delete();
    $this->asset->delete();
    expect($snapshot->fresh()->assets_cents)->toBe(200000)->and($snapshot->fresh()->net_worth_cents)->toBe(100000);
    $this->get(route('net-worth.index', ['tab' => 'history']))->assertOk()->assertSee('Private bank account')->assertSee('Loan');
});

test('snapshot dates cannot duplicate active or removed history and exclusions are recorded', function () {
    $futureAsset = Asset::factory()->create(['user_id' => $this->owner->id]);
    AssetValuation::factory()->create(['asset_id' => $futureAsset->id, 'date' => '2026-10-04']);
    $this->post(route('net-worth.snapshots.store'), ['date' => '2026-10-01'])->assertRedirect();
    $snapshot = NetWorthSnapshot::query()->sole();
    expect($snapshot->details['excluded'])->toBe(1)->and($snapshot->assets_cents)->toBe(200000);
    $this->postJson(route('net-worth.snapshots.store'), ['date' => '2026-10-01'])->assertUnprocessable();
    $this->delete(route('net-worth.snapshots.destroy', $snapshot))->assertRedirect()->assertSessionHas('undo_snapshot');
    $this->postJson(route('net-worth.snapshots.store'), ['date' => '2026-10-01'])->assertUnprocessable();
    $this->post(route('net-worth.snapshots.restore', $snapshot))->assertRedirect();
    expect(NetWorthSnapshot::query()->count())->toBe(1);
    $this->postJson(route('net-worth.snapshots.store'), ['date' => '2026-10-05'])->assertUnprocessable();
    $this->postJson(route('net-worth.snapshots.store'), ['date' => '2026-09-01'])->assertUnprocessable();
});

test('assets and valuations can be removed and restored while keeping at least one value', function () {
    $this->delete(route('net-worth.values.destroy', [$this->asset, $this->value]))->assertUnprocessable();
    $this->post(route('net-worth.values.store', $this->asset), assetValueData())->assertRedirect();
    $latest = $this->asset->valuations()->whereDate('date', '2026-10-04')->sole();
    $this->delete(route('net-worth.values.destroy', [$this->asset, $latest]))->assertRedirect()->assertSessionHas('undo_valuation');
    expect(app(NetWorthWorkspace::class)->build($this->owner)['assetTotal'])->toBe(200000);
    $this->postJson(route('net-worth.values.store', $this->asset), assetValueData())->assertUnprocessable();
    $this->post(route('net-worth.values.restore', [$this->asset, $latest]))->assertRedirect();
    expect(app(NetWorthWorkspace::class)->build($this->owner)['assetTotal'])->toBe(300000);
    $this->delete(route('net-worth.assets.destroy', $this->asset))->assertRedirect()->assertSessionHas('undo_asset');
    expect(app(NetWorthWorkspace::class)->build($this->owner)['assetTotal'])->toBe(0);
    $this->post(route('net-worth.assets.restore', $this->asset))->assertRedirect();
    expect(app(NetWorthWorkspace::class)->build($this->owner)['assetTotal'])->toBe(300000);
});

test('valuation ids cannot access another asset even if both assets are owned', function () {
    $otherAsset = Asset::factory()->create(['user_id' => $this->owner->id]);
    $otherValue = AssetValuation::factory()->create(['asset_id' => $otherAsset->id]);
    $this->postJson(route('net-worth.values.store', $this->asset), assetValueData(['valuation_id' => $otherValue->id]))->assertNotFound();
    $this->delete(route('net-worth.values.destroy', [$this->asset, $otherValue]))->assertNotFound();
    $this->post(route('net-worth.values.restore', [$this->asset, $otherValue]))->assertNotFound();
});

test('snapshots provide chart data and comparison with the current live position', function () {
    $this->post(route('net-worth.snapshots.store'), ['date' => '2026-10-01'])->assertRedirect();
    $this->post(route('net-worth.values.store', $this->asset), assetValueData())->assertRedirect();
    $data = app(NetWorthWorkspace::class)->build($this->owner);
    expect($data['change'])->toBe(100000)->and($data['latest']->date->toDateString())->toBe('2026-10-01');
    $this->get(route('net-worth.index'))->assertOk()->assertSee('data-worth-data', false)->assertSee('Last 6 snapshots')->assertSee('higher');
    $this->get(route('dashboard'))->assertOk()->assertSee('dashboard-net-worth', false)->assertSee('higher');
    $this->get(route('net-worth.index', ['tab' => 'history']))->assertOk()->assertSee('Asset valuation history', false);
});

test('asset metadata can be edited without resubmitting a value and removed assets reject value changes', function () {
    $this->put(route('net-worth.assets.update', $this->asset), ['name' => 'New label', 'kind' => 'investment'])->assertRedirect()->assertSessionHasNoErrors();
    expect($this->asset->fresh()->name)->toBe('New label')->and($this->asset->valuations()->sole()->amount_cents)->toBe(200000);
    $this->delete(route('net-worth.assets.destroy', $this->asset))->assertRedirect();
    $this->post(route('net-worth.values.store', $this->asset), assetValueData())->assertNotFound();
    $this->get(route('net-worth.index', ['tab' => 'assets']))->assertOk()->assertSee('Removed assets')->assertSee('Restore');
});

test('restore endpoints cannot expose another users removed financial records', function () {
    $snapshot = NetWorthSnapshot::factory()->create(['user_id' => $this->owner->id]);
    $snapshot->delete();
    $this->value->delete();
    $this->asset->delete();
    $this->actingAs(User::factory()->admin()->create());
    $this->post(route('net-worth.assets.restore', $this->asset))->assertForbidden();
    $this->post(route('net-worth.values.restore', [$this->asset, $this->value]))->assertForbidden();
    $this->post(route('net-worth.snapshots.restore', $snapshot))->assertForbidden();
});

test('recorded principal payments reversals are reflected live while saved snapshots stay unchanged', function () {
    config(['features.debt_tracking' => true]);
    $debt = Debt::factory()->create(['user_id' => $this->owner->id, 'opening_balance_cents' => 100000, 'balance_date' => '2026-10-01']);
    $payment = DebtPayment::factory()->create(['debt_id' => $debt->id, 'amount_cents' => 10000, 'interest_cents' => 1000, 'date' => '2026-10-03']);
    $this->post(route('net-worth.snapshots.store'), ['date' => '2026-10-04'])->assertRedirect();
    $snapshot = NetWorthSnapshot::query()->sole();
    expect($snapshot->debts_cents)->toBe(91000);
    $payment->delete();
    expect(app(NetWorthWorkspace::class)->build($this->owner)['debtTotal'])->toBe(100000)->and($snapshot->fresh()->debts_cents)->toBe(91000);
});
