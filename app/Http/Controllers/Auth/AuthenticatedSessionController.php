<?php

namespace App\Http\Controllers\Auth;

use App\Domain\Identity\Models\AuditEvent;
use App\Domain\Identity\Models\User;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

final class AuthenticatedSessionController extends Controller
{
    private const int MAX_ATTEMPTS = 5;

    public function create(): View
    {
        return view('auth.login');
    }

    public function store(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'string', 'email:rfc', 'max:254'],
            'password' => ['required', 'string'],
        ]);

        $email = mb_strtolower(trim($credentials['email']));
        $throttleKey = Str::transliterate($email.'|'.$request->ip());

        if (RateLimiter::tooManyAttempts($throttleKey, self::MAX_ATTEMPTS)) {
            $this->fail();
        }

        if (! Auth::attempt([
            'email_normalized' => $email,
            'password' => $credentials['password'],
            'is_enabled' => true,
        ])) {
            RateLimiter::hit($throttleKey, 60);
            $this->fail();
        }

        RateLimiter::clear($throttleKey);
        $request->session()->regenerate();
        $this->audit($request->user(), 'authentication.signed_in');

        if ($request->user()->must_change_password) {
            return redirect()->route('password.change');
        }

        return redirect()->intended('/');
    }

    public function destroy(Request $request): RedirectResponse
    {
        if ($request->user() !== null) {
            $this->audit($request->user(), 'authentication.signed_out');
        }

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    private function fail(): never
    {
        throw ValidationException::withMessages([
            'email' => __('These credentials do not match our records.'),
        ]);
    }

    private function audit(User $user, string $eventType): void
    {
        AuditEvent::query()->create([
            'household_id' => $user->memberships()->whereNull('removed_at')->value('household_id'),
            'actor_user_id' => $user->id,
            'event_type' => $eventType,
            'subject_type' => User::class,
            'subject_id' => $user->id,
            'metadata' => null,
            'occurred_at' => now(),
        ]);
    }
}
