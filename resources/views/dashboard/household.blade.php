@can('users.manage')
<section class="flex flex-wrap items-center justify-between gap-3 rounded-sm border border-base-300 bg-base-100 px-3 py-2.5" aria-labelledby="dashboard-household-title">
    <div>
        <h2 id="dashboard-household-title" class="flex items-center gap-1.5 text-sm font-semibold"><x-lucide-users-round class="size-3.5 text-primary" aria-hidden="true" />Manage your household</h2>
        <p class="mt-0.5 text-[10px] opacity-60">Add users and assign their roles to get everyone set up.</p>
    </div>
    <a class="btn btn-primary btn-xs" href="{{ route('admin.users.index') }}">Manage users</a>
</section>
@endcan
