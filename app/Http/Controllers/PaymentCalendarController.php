<?php

namespace App\Http\Controllers;

use App\PaymentCalendar;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class PaymentCalendarController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request, PaymentCalendar $calendar): View
    {
        $types = config('features.debt_tracking') ? 'all,subscription,recurring,debt,income' : 'all,subscription,recurring,income';
        $request->validate(['month' => ['nullable', 'date_format:Y-m'], 'type' => ['nullable', 'string', 'in:'.$types]]);
        $today = CarbonImmutable::today();
        $month = $request->filled('month') ? CarbonImmutable::createFromFormat('!Y-m', $request->string('month')->toString()) : $today->startOfMonth();
        if ($month->lt($today->startOfMonth()) || $month->gt($today->startOfMonth()->addMonths(11))) {
            throw ValidationException::withMessages(['month' => 'Choose this month or one of the next eleven months.']);
        }
        $data = $calendar->build($request->user(), $month, $month->endOfMonth());
        $type = $request->input('type', 'all') ?? 'all';
        $events = $data['events']->when($type !== 'all', fn (Collection $events): Collection => $events->where('type', $type))->values();
        $byDay = $events->groupBy('date');
        $days = [];
        for ($date = $month->startOfWeek(CarbonInterface::MONDAY); $date->lte($month->endOfMonth()->endOfWeek(CarbonInterface::SUNDAY)); $date = $date->addDay()) {
            $days[] = ['date' => $date, 'events' => $byDay->get($date->toDateString(), collect())];
        }

        return view('payment-calendar', [...$data, 'events' => $events, 'byDay' => $byDay, 'days' => $days, 'month' => $month, 'today' => $today, 'type' => $type,
            'incoming' => $events->where('type', 'income')->sum('amount'), 'outgoing' => $events->where('type', '!=', 'income')->sum('amount')]);
    }
}
