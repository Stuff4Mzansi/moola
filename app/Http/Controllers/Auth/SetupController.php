<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\SetupRequest;
use App\Models\AppSetting;
use App\Models\User;
use App\UserRole;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class SetupController extends Controller
{
    public function create(): View|RedirectResponse
    {
        if (User::hasAdministrator()) {
            return redirect()->route('login');
        }

        return view('auth.setup');
    }

    public function store(SetupRequest $request): RedirectResponse
    {
        $user = Cache::store('database')->lock('initial-admin-setup', 30)->block(5, function () use ($request): User {
            return DB::transaction(function () use ($request): User {
                abort_if(User::hasAdministrator(), 403, 'Setup has already been completed.');

                $user = new User($request->safe()->only(['name', 'email', 'password']));
                $user->role = UserRole::SuperAdmin;
                $user->save();
                AppSetting::query()->updateOrCreate(['id' => 1], ['currency' => $request->validated('currency')]);

                return $user;
            });
        });

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('dashboard');
    }
}
