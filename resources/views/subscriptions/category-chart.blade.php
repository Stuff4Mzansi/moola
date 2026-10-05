            <section class="min-w-0 rounded-sm border border-base-300 bg-base-100" aria-labelledby="{{ $chartId }}-title">
                <div class="space-y-3 p-3">
                    <div><h3 id="{{ $chartId }}-title" class="text-sm font-semibold">Where your money goes</h3><p class="mt-1 text-[11px] leading-snug opacity-65">Monthly equivalent by category</p></div>
                    @php
                        $chartColours = ['#6366f1', '#14b8a6', '#f59e0b', '#ec4899', '#0ea5e9', '#8b5cf6', '#84cc16', '#f97316'];
                        $categoryOffset = 0;
                    @endphp
                    <svg viewBox="0 0 120 120" class="mx-auto w-32" role="img" aria-labelledby="{{ $chartId }}-title" aria-describedby="{{ $chartId }}-description">
                        <circle cx="60" cy="60" r="44" fill="none" stroke="currentColor" stroke-width="14" class="text-base-300" />
                        @foreach($analytics['categories'] as $index => $category)
                            <circle cx="60" cy="60" r="44" fill="none" stroke="{{ $chartColours[$index % count($chartColours)] }}" stroke-width="14" pathLength="100" stroke-dasharray="{{ $category['share'] }} {{ 100 - $category['share'] }}" stroke-dashoffset="{{ -$categoryOffset }}" transform="rotate(-90 60 60)">
                                <title>{{ $category['name'] }}: {{ number_format($category['share'], 1) }}%, ZAR {{ number_format($category['monthly_cost_cents'] / 100, 2) }} monthly equivalent</title>
                            </circle>
                            @php $categoryOffset += $category['share']; @endphp
                        @endforeach
                        <text x="60" y="58" text-anchor="middle" fill="currentColor" font-size="18" font-weight="700">{{ $activeCount }}</text>
                        <text x="60" y="73" text-anchor="middle" fill="currentColor" font-size="8">subscriptions</text>
                    </svg>
                    <p id="{{ $chartId }}-description" class="sr-only">Category shares of annual equivalent spending, with monthly equivalent amounts listed below.</p>
                    <ul class="max-h-40 divide-y divide-base-300/60 overflow-auto pr-1" tabindex="0" aria-label="Category shares and monthly amounts">
                        @foreach($analytics['categories'] as $index => $category)
                            <li class="flex items-center justify-between gap-2 py-1.5 text-[11px]">
                                <span class="flex min-w-0 items-start gap-1.5"><span class="mt-1 size-2 shrink-0 rounded-sm" style="background-color: {{ $chartColours[$index % count($chartColours)] }}" aria-hidden="true"></span><span class="break-words">{{ $category['name'] }}</span></span>
                                <span class="shrink-0 text-right tabular-nums"><span class="font-semibold">ZAR {{ number_format($category['monthly_cost_cents'] / 100, 2) }}</span><span class="ml-1.5 text-[10px] opacity-55">{{ number_format($category['share'], 1) }}%</span></span>
                            </li>
                        @endforeach
                    </ul>
                </div>
            </section>

