<?php

namespace App\Http\Controllers;

use App\Domain\Identity\Actions\CreateFirstAdministrator;
use App\Domain\Identity\Models\User;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

final class SetupController extends Controller
{
    public function create(): View|RedirectResponse
    {
        if (User::query()->exists()) {
            return Auth::check() ? redirect('/') : redirect()->route('login');
        }

        return view('setup.create');
    }

    public function store(Request $request, CreateFirstAdministrator $creator): RedirectResponse
    {
        if (User::query()->exists()) {
            return Auth::check() ? redirect('/') : redirect()->route('login');
        }

        $validated = $request->validate([
            'household_name' => ['required', 'string', 'max:120'],
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'string', 'email:rfc', 'max:254'],
            'password' => [
                'required',
                'confirmed',
                Password::min(12)->mixedCase()->numbers()->symbols(),
            ],
        ]);

        try {
            $administrator = $creator->handle(
                $validated['name'],
                $validated['email'],
                $validated['password'],
                $validated['household_name'],
            );
        } catch (DomainException) {
            return redirect()->route('login');
        }

        Auth::login($administrator);
        $request->session()->regenerate();

        return redirect('/')->with('status', 'Your Moola household is ready.');
    }
}
