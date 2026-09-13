<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="color-scheme" content="light">
        <title>{{ config('app.name') }}</title>
        <link rel="stylesheet" href="{{ asset('assets/app.css') }}">
    </head>
    <body>
        <main class="shell">
            <section class="welcome" aria-labelledby="welcome-title">
                <span class="mark" aria-hidden="true">M</span>
                <p class="eyebrow">Your money, made clear</p>
                <h1 id="welcome-title">Welcome to Moola</h1>
                <p class="lede">The private, self-hosted home for your household budget, debt payoff, net worth, and subscriptions.</p>
                <div class="status" role="status">
                    <span class="status-dot" aria-hidden="true"></span>
                    Foundation ready
                </div>
                <div class="welcome-actions">
                    @auth
                        <p>Signed in as {{ auth()->user()->name }}</p>
                        <form method="post" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="button button-secondary">Sign out</button>
                        </form>
                    @else
                        <a class="button" href="{{ route('login') }}">Sign in</a>
                    @endauth
                </div>
            </section>
        </main>
    </body>
</html>
