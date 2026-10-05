<?php

namespace App\Http\Controllers;

use App\DashboardLayout;
use App\Http\Requests\DashboardLayoutRequest;
use Illuminate\Http\JsonResponse;

class DashboardLayoutController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(DashboardLayoutRequest $request, DashboardLayout $layout): JsonResponse
    {
        $user = $request->user();
        $user->dashboard_layout = array_map(fn (array $widget): array => [...$widget, 'visible' => (bool) $widget['visible']], $request->validated('widgets'));
        $user->save();

        return response()->json(['widgets' => $layout->build($user), 'message' => 'Your dashboard layout has been saved.']);
    }
}
