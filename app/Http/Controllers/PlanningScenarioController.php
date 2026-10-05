<?php

namespace App\Http\Controllers;

use App\Http\Requests\PlanningScenarioRequest;
use App\Models\PlanningScenario;
use App\PlanningScenarios;
use App\SubscriptionStatus;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class PlanningScenarioController extends Controller
{
    public function index(PlanningScenarioRequest $request, PlanningScenarios $planner): View
    {
        $kind = $request->validated('kind');
        $inputs = $request->validated('inputs');
        $ids = $request->validated('compare') ?? [];
        $selected = PlanningScenario::query()->where('user_id', $request->user()->id)->whereIn('id', $ids)->get();
        abort_unless($selected->count() === count($ids), 404);
        if ($selected->contains(fn (PlanningScenario $scenario): bool => $scenario->kind !== $kind)) {
            throw ValidationException::withMessages(['compare' => 'Choose scenarios from the same planning tool to compare.']);
        }
        $window = (int) ($request->validated('comparison_window') ?? 30);
        $comparison = $selected->map(fn (PlanningScenario $scenario): array => ['name' => $scenario->name, 'result' => $planner->evaluate($request->user(), $kind, $scenario->inputs, $kind === 'liquidity' ? $window : null)])->all();
        if ($comparison !== []) {
            array_unshift($comparison, ['name' => $kind === 'debt' ? 'Minimums · avalanche' : 'Current baseline', 'result' => $planner->evaluate($request->user(), $kind, $planner->defaults($kind), $kind === 'liquidity' ? $window : null)]);
        }

        return view('planning-scenarios.index', [
            'kinds' => $planner->kinds(), 'kind' => $kind, 'inputs' => $inputs,
            'editingScenario' => $request->selectedScenario,
            'scenarios' => PlanningScenario::query()->where('user_id', $request->user()->id)->where('kind', $kind)->orderByDesc('updated_at')->orderByDesc('id')->paginate(20)->withQueryString(),
            'activeSubscriptions' => $request->user()->subscriptions()->where('status', SubscriptionStatus::Active->value)->orderBy('name')->get(),
            'result' => $planner->evaluate($request->user(), $kind, $inputs), 'comparison' => $comparison, 'comparisonWindow' => $window,
        ]);
    }

    public function store(PlanningScenarioRequest $request): RedirectResponse
    {
        $scenario = new PlanningScenario($request->safe()->only(['name', 'notes', 'kind', 'inputs']));
        $scenario->user_id = $request->user()->id;
        $scenario->save();

        return to_route('planning-scenarios.index', ['scenario' => $scenario->id])->with('status', 'Planning scenario saved.');
    }

    public function update(PlanningScenarioRequest $request, PlanningScenario $scenario): RedirectResponse
    {
        abort_unless($scenario->user_id === $request->user()->id, 404);
        abort_unless($scenario->kind === $request->validated('kind'), 422, 'Create a new scenario to use a different planning tool.');
        $scenario->fill($request->safe()->only(['name', 'notes', 'inputs']))->save();

        return to_route('planning-scenarios.index', ['scenario' => $scenario->id])->with('status', 'Planning scenario updated.');
    }

    public function duplicate(Request $request, PlanningScenario $scenario): RedirectResponse
    {
        abort_unless($scenario->user_id === $request->user()->id, 404);
        $copy = $scenario->replicate();
        $copy->name = mb_substr($scenario->name, 0, 93).' (copy)';
        $copy->save();

        return to_route('planning-scenarios.index', ['scenario' => $copy->id])->with('status', 'Scenario duplicated. You can now change its assumptions.');
    }

    public function destroy(Request $request, PlanningScenario $scenario): RedirectResponse
    {
        abort_unless($scenario->user_id === $request->user()->id, 404);
        $kind = $scenario->kind;
        $scenario->delete();

        return to_route('planning-scenarios.index', ['kind' => $kind])->with('status', 'Planning scenario deleted.');
    }
}
