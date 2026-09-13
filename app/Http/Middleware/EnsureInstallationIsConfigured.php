<?php

namespace App\Http\Middleware;

use App\Domain\Identity\Models\User;
use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class EnsureInstallationIsConfigured
{
    public function handle(Request $request, Closure $next): Response|RedirectResponse
    {
        if (
            ! $request->routeIs('setup.*', 'health.*')
            && ! User::query()->exists()
        ) {
            return redirect()->route('setup.create');
        }

        return $next($request);
    }
}
