<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreUserRequest;
use App\Http\Requests\Admin\UpdateUserRoleRequest;
use App\Models\User;
use App\UserRole;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class UserController extends Controller
{
    public function index(): View
    {
        return view('admin.users.index', [
            'users' => User::query()->orderBy('name')->paginate(20),
        ]);
    }

    public function create(): View
    {
        return view('admin.users.create');
    }

    public function store(StoreUserRequest $request): RedirectResponse
    {
        $user = new User($request->safe()->only(['name', 'email', 'password']));
        $user->role = UserRole::from($request->validated('role'));
        $user->save();

        return redirect()->route('admin.users.index')->with('status', 'User added.');
    }

    public function update(UpdateUserRoleRequest $request, User $user): RedirectResponse
    {
        $user->role = UserRole::from($request->validated('role'));
        $user->save();

        return redirect()->route('admin.users.index')->with('status', 'User role updated.');
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        Gate::authorize('users.delete', $user);
        $user->delete();

        return redirect()->route('admin.users.index')->with('status', 'User deleted.');
    }
}
