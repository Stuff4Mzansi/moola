<?php

namespace App\Http\Controllers\Auth;

use App\Domain\Identity\Models\AuditEvent;
use App\Domain\Identity\Models\User;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

final class PasswordChangeController extends Controller
{
    public function edit(): View
    {
        return view('auth.change-password');
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => [
                'required',
                'confirmed',
                Password::min(12)->mixedCase()->numbers()->symbols(),
            ],
        ]);

        $user = $request->user();
        $user->forceFill([
            'password' => Hash::make($validated['password']),
            'must_change_password' => false,
        ])->save();

        Auth::logoutOtherDevices($validated['password']);
        DB::table('sessions')->where('user_id', $user->id)->delete();
        $this->audit($user);
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        Auth::login($user);
        $request->session()->regenerate();

        return redirect('/')->with('status', 'Password changed successfully.');
    }

    private function audit(User $user): void
    {
        AuditEvent::query()->create([
            'household_id' => $user->memberships()->whereNull('removed_at')->value('household_id'),
            'actor_user_id' => $user->id,
            'event_type' => 'user.password_changed',
            'subject_type' => User::class,
            'subject_id' => $user->id,
            'metadata' => null,
            'occurred_at' => now(),
        ]);
    }
}
