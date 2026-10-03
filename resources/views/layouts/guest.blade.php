<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="light">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>@yield('title') · {{ config('app.name') }}</title>
        @fonts
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="min-h-screen bg-base-200 text-base-content">
        <main class="flex min-h-screen items-center justify-center px-4 py-12">
            <div class="w-full max-w-md">
                <div class="mb-8 flex items-center justify-center gap-3 text-2xl font-bold">
                    <span class="flex size-12 items-center justify-center rounded-sm bg-primary text-primary-content" aria-hidden="true">M</span>
                    Moola
                </div>
                <div class="card border border-base-300 bg-base-100 shadow-sm">
                    <div class="card-body gap-5">
                        @yield('content')
                    </div>
                </div>
                <p class="mt-6 text-center text-sm opacity-70">Your space for individual and household finances.</p>
            </div>
        </main>
    </body>
</html>
