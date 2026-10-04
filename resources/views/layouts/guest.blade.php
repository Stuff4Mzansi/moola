<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="light">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="color-scheme" content="light dark">
        <title>@yield('title') · {{ config('app.name') }}</title>
        @include('layouts.theme')
        @fonts
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="guest-shell min-h-dvh text-base-content">
        <main class="flex min-h-dvh items-center justify-center px-4 py-8 sm:py-12">
            <div class="w-full max-w-md">
                <div class="mb-7 flex items-center justify-center gap-3">
                    <span class="flex size-10 items-center justify-center rounded-sm border border-primary/15 bg-primary/10 text-lg font-bold text-primary" aria-hidden="true">M</span>
<span><span class="block text-xl font-semibold tracking-tight">Moola</span><span class="mt-0.5 block text-[11px] text-base-content/50">Finance workspace</span></span>
                </div>
                <div class="card rounded-sm border border-base-300 bg-base-100">
                    <div class="card-body gap-5 p-6 sm:p-7">
                        @yield('content')
                    </div>
                </div>
                <p class="mt-5 text-center text-xs text-base-content/50">Your space for individual and household finances.</p>
            </div>
        </main>
    </body>
</html>
