@extends('layouts.app')
@section('title', 'Manage budgets')
@section('content')
<div class="mx-auto max-w-5xl space-y-6">
    <header><h1 class="text-2xl font-bold">Manage budgets</h1><p class="mt-2 opacity-70">Remove budgets from this app. Financial details remain private to their owners and invited members.</p></header>
    @if(session('status'))<div class="alert alert-success" role="status">{{ session('status') }}</div>@endif
    <div class="overflow-x-auto rounded-sm border border-base-300 bg-base-100"><table class="table"><thead><tr><th>Budget</th><th>Owner</th><th>Type</th><th>Periods</th><th>Actions</th></tr></thead><tbody>
        @forelse($budgets as $budget)
        <tr><td class="font-medium">{{ $budget->name }}</td><td>{{ $budget->owner->name }}</td><td>{{ ucfirst($budget->scope) }}</td><td>{{ $budget->periods_count }}</td><td>
            @can('view', $budget)
            @if($budget->periods_count > 0)<a class="btn btn-sm btn-ghost" href="{{ route('budgets.index', ['period' => $budget->periods()->orderByDesc('start_date')->value('id')]) }}">Open</a>@endif
            @endcan
            @can('delete', $budget)
            <form class="inline-block" method="post" action="{{ route('admin.budgets.destroy', $budget) }}" data-confirm="Permanently delete {{ $budget->name }} and all its periods, groups, categories, income, and expenses? This cannot be undone. The owner's subscriptions and other budgets are kept." data-confirm-title="Delete budget?" data-confirm-label="Delete budget">@csrf @method('DELETE')<button class="btn btn-sm btn-outline btn-error" type="submit">Delete</button></form>
            @endcan
        </td></tr>
        @empty<tr><td class="p-8 text-center opacity-60" colspan="5">No budgets to manage.</td></tr>@endforelse
    </tbody></table></div>
    {{ $budgets->links() }}
</div>
@endsection
