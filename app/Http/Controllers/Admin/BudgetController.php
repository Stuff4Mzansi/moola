<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Budget;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class BudgetController extends Controller
{
    public function index(): View
    {
        return view('admin.budgets.index', ['budgets' => Budget::query()->select(['id', 'user_id', 'name', 'scope'])->with('owner:id,name')->withCount('periods')->orderBy('name')->paginate(20)]);
    }

    public function destroy(Budget $budget): RedirectResponse
    {
        Gate::authorize('delete', $budget);
        Cache::store('database')->lock('budget:'.$budget->id, 30)->block(5, function () use ($budget): void {
            DB::transaction(function () use ($budget): void {
                $budget = Budget::query()->findOrFail($budget->id);
                Gate::authorize('delete', $budget);
                $budget->delete();
            });
        });

        return redirect()->route('admin.budgets.index')->with('status', 'Budget and all its periods deleted.');
    }
}
