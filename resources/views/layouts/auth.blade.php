<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="color-scheme" content="light">
        <title>@yield('title') · {{ config('app.name') }}</title>
        <link rel="stylesheet" href="{{ asset('assets/app.css') }}">
    </head>
    <body>
        <main class="shell">
            <section class="auth-card" aria-labelledby="page-title">
                <a class="brand" href="/" aria-label="Moola home">
                    <span class="mark" aria-hidden="true">M</span>
                    <span>Moola</span>
                </a>

                @if (session('status'))
                    <p class="notice" role="status">{{ session('status') }}</p>
                @endif

                @yield('content')
            </section>
        </main>
    </body>
</html>
