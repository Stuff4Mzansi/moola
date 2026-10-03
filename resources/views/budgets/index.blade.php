@extends('layouts.app')
@section('title', 'Budget')
@section('content')
<div class="mx-auto max-w-7xl space-y-6" data-budget-page>
    <header class="flex flex-wrap items-center justify-between gap-4"><div><h1 class="text-3xl font-bold">Budget</h1><p class="mt-2 opacity-70">A clear picture of your money, with a plan behind it.</p></div><button class="btn btn-outline btn-sm" data-open-dialog="new-budget" type="button">New budget</button></header>
    <div class="flex flex-wrap items-center gap-2 text-xs opacity-70" role="status" aria-live="polite"><span data-budget-status>{{ session('status', 'Your budget is up to date.') }}</span><button class="btn btn-sm btn-ghost hidden" type="button" data-budget-refresh>Refresh budget</button><button class="btn btn-sm btn-outline hidden" type="button" data-budget-undo>Undo deletion</button></div>
    <div id="budget-workspace" data-budget-workspace>@include('budgets.workspace')</div>
    @include('budgets.new-budget')
</div>
@endsection