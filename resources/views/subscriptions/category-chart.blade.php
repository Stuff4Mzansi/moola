            <section class="card border border-base-300 bg-base-100" aria-labelledby="{{ $chartId }}-title">
                <div class="card-body gap-5">
                    <div><h3 id="{{ $chartId }}-title" class="card-title">Where your money goes</h3><p class="mt-1 text-sm opacity-70">Monthly equivalent by category</p></div>
                    @php
                        $chartColours = ['#6366f1', '#14b8a6', '#f59e0b', '#ec4899', '#0ea5e9', '#8b5cf6', '#84cc16', '#f97316'];
                        $categoryOffset = 0;
                    @endphp
                    <svg viewBox="0 0 120 120" class="mx-auto w-48" role="img" aria-labelledby="{{ $chartId }}-title" aria-describedby="{{ $chartId }}-description">
                        <circle cx="60" cy="60" r="44" fill="none" stroke="currentColor" stroke-width="14" class="text-base-300" />
                        @foreach($analytics['categories'] as $index => $category)
                            <circle cx="60" cy="60" r="44" fill="none" stroke="{{ $chartColours[$index % count($chartColours)] }}" stroke-width="14" pathLength="100" stroke-dasharray="{{ $category['share'] }} {{ 100 - $category['share'] }}" stroke-dashoffset="{{ -$categoryOffset }}" transform="rotate(-90 60 60)">
                                <title>{{ $category['name'] }}: {{ number_format($category['share'], 1) }}%, ZAR {{ number_format($category['monthly_cost_cents'] / 100, 2) }} monthly equivalent</title>
                            </circle>
                            @php $categoryOffset += $category['share']; @endphp
                        @endforeach
                        <text x="60" y="58" text-anchor="middle" fill="currentColor" font-size="18" font-weight="700">{{ $activeCount }}</text>
                        <text x="60" y="73" text-anchor="middle" fill="currentColor" font-size="8">active subscriptions</text>
                    </svg>
                    <p id="{{ $chartId }}-description" class="sr-only">Category shares of annual equivalent spending, with monthly equivalent amounts listed below.</p>
                    <ul class="space-y-3">
                        @foreach($analytics['categories'] as $index => $category)
                            <li class="flex items-start justify-between gap-3 text-sm">
                                <span class="flex min-w-0 items-start gap-2"><span class="mt-1 size-3 shrink-0 rounded-full" style="background-color: {{ $chartColours[$index % count($chartColours)] }}" aria-hidden="true"></span><span class="break-words">{{ $category['name'] }}</span></span>
                                <span class="shrink-0 text-right"><span class="font-semibold">ZAR {{ number_format($category['monthly_cost_cents'] / 100, 2) }}</span><span class="block text-xs opacity-60">{{ number_format($category['share'], 1) }}%</span></span>
                            </li>
                        @endforeach
                    </ul>
                </div>
            </section>

