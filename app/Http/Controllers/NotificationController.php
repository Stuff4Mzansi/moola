<?php

namespace App\Http\Controllers;

use App\BudgetNotifications;
use App\Http\Requests\BudgetNotificationSettingsRequest;
use App\Http\Requests\NotificationPreferenceRequest;
use App\Models\Budget;
use App\Models\BudgetNotificationPreference;
use App\Models\FinancialNotification;
use App\Models\MailSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class NotificationController extends Controller
{
    public function index(Request $request): View
    {
        return view('notifications.index', ['notifications' => FinancialNotification::visibleTo($request->user())->latest('id')->paginate(25)]);
    }

    public function feed(Request $request): JsonResponse
    {
        return response()->json(['html' => view('notifications.bell', $this->bellData($request))->render()]);
    }

    /** @return array<string, mixed> */
    public function bellData(Request $request): array
    {
        $query = FinancialNotification::visibleTo($request->user())->whereNull('resolved_at');

        return ['notificationCount' => (clone $query)->whereNull('read_at')->count(), 'recentNotifications' => $query->latest('id')->limit(5)->get()];
    }

    public function open(Request $request, int $notification): RedirectResponse
    {
        $item = FinancialNotification::visibleTo($request->user())->findOrFail($notification);
        $item->update(['read_at' => $item->read_at ?? now()]);

        return redirect()->to($item->url());
    }

    public function readAll(Request $request): RedirectResponse
    {
        FinancialNotification::visibleTo($request->user())->whereNull('read_at')->update(['read_at' => now()]);

        return back()->with('status', 'Notifications marked as read.');
    }

    public function settings(BudgetNotificationSettingsRequest $request, Budget $budget): RedirectResponse
    {
        Cache::store('database')->lock('budget:'.$budget->id, 30)->block(5, function () use ($request, $budget): void {
            DB::transaction(function () use ($request, $budget): void {
                $budget->update($request->validated());
                $budget->periods()->increment('version');
                if (! $budget->notifications_enabled) {
                    FinancialNotification::query()->where('budget_id', $budget->id)->whereNull('resolved_at')->update(['resolved_at' => now()]);
                }
            });
        });

        return back()->with('status', 'Budget notification settings saved.');
    }

    public function preferences(NotificationPreferenceRequest $request, Budget $budget): RedirectResponse
    {
        $muted = array_values(array_diff(array_keys(BudgetNotifications::TYPES), $request->validated('enabled_types', [])));
        $emailEnabled = $request->boolean('email_enabled');
        $preference = BudgetNotificationPreference::query()->firstOrNew(['user_id' => $request->user()->id, 'budget_id' => $budget->id]);
        if ($emailEnabled && ! $preference->email_enabled && ! MailSetting::query()->where('id', 1)->where('enabled', true)->exists()) {
            throw ValidationException::withMessages(['email_enabled' => 'An admin must enable SMTP email delivery first.']);
        }
        if ($emailEnabled && ! $preference->email_enabled) {
            $preference->email_after_notification_id = FinancialNotification::query()->where('user_id', $request->user()->id)->where('budget_id', $budget->id)->max('id') ?? 0;
        }
        $preference->fill(['muted_types' => $muted, 'email_enabled' => $emailEnabled])->save();
        FinancialNotification::query()->where('user_id', $request->user()->id)->where('budget_id', $budget->id)->whereIn('type', $muted)->whereNull('resolved_at')->update(['resolved_at' => now()]);

        return back()->with('status', 'Your notification preferences saved.');
    }
}
