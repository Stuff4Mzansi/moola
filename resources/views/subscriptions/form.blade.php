<div class="card-body gap-6">
    @if($errors->any())
        <div class="alert alert-error" role="alert"><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
    @endif
    <div>
        <label class="label" for="name">Subscription name</label>
        <input class="input w-full" id="name" name="name" value="{{ old('name', $subscription->name) }}" maxlength="255" required autofocus placeholder="e.g. Netflix">
    </div>
    <div class="grid gap-5 sm:grid-cols-2">
        <div>
            <label class="label" for="amount">Price per payment</label>
            <div class="flex items-center gap-2">
                <span class="font-semibold">ZAR</span>
                <input class="input w-full" id="amount" name="amount" type="number" min="0.01" max="9999999.99" step="0.01" value="{{ old('amount', $subscription->exists ? number_format($subscription->amount_cents / 100, 2, '.', '') : '') }}" required placeholder="159.00">
            </div>
            <input type="hidden" name="currency" value="ZAR">
        </div>
        <div>
            <label class="label" for="billing_frequency">Billing frequency</label>
            <select class="select w-full" id="billing_frequency" name="billing_frequency" required>
                @foreach(\App\BillingFrequency::cases() as $frequency)
                    <option value="{{ $frequency->value }}" @selected(old('billing_frequency', $subscription->billing_frequency?->value ?? 'monthly') === $frequency->value)>{{ $frequency->label() }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="label" for="next_billing_date">Billing date</label>
            <input class="input w-full" id="next_billing_date" name="next_billing_date" type="date" min="1900-01-01" max="2100-12-31" value="{{ old('next_billing_date', $subscription->next_billing_date?->toDateString() ?? now()->toDateString()) }}" required aria-describedby="billing-date-help">
            <p class="mt-1 text-sm opacity-70" id="billing-date-help">Use a known billing date. Future renewals follow this schedule.</p>
        </div>
        <div>
            <label class="label" for="status">Status</label>
            <select class="select w-full" id="status" name="status" required aria-describedby="status-help">
                @foreach(\App\SubscriptionStatus::cases() as $status)
                    <option value="{{ $status->value }}" @selected(old('status', $subscription->status?->value ?? 'active') === $status->value)>{{ $status->label() }}</option>
                @endforeach
            </select>
            <p class="mt-1 text-sm opacity-70" id="status-help">To reactivate a subscription, choose its next billing date from today onwards.</p>
        </div>
    </div>
    <div>
        <label class="label" for="category">Category <span class="text-sm opacity-60">(optional)</span></label>
        <input class="input w-full" id="category" name="category" value="{{ old('category', $subscription->category) }}" maxlength="100" list="subscription-categories" placeholder="e.g. Entertainment">
        <datalist id="subscription-categories">
            @foreach(['Entertainment', 'Software', 'Health & fitness', 'Education', 'Cloud storage', 'Other'] as $category)<option value="{{ $category }}"></option>@endforeach
        </datalist>
    </div>
    <div>
        <label class="label" for="website">Website <span class="text-sm opacity-60">(optional)</span></label>
        <input class="input w-full" id="website" name="website" type="url" value="{{ old('website', $subscription->website) }}" maxlength="2048" placeholder="https://example.com">
    </div>
    <div>
        <label class="label" for="notes">Notes <span class="text-sm opacity-60">(optional)</span></label>
        <textarea class="textarea w-full" id="notes" name="notes" rows="4" maxlength="5000" placeholder="Plan details or cancellation instructions">{{ old('notes', $subscription->notes) }}</textarea>
    </div>
    <p class="text-sm opacity-70">Paused and cancelled subscriptions are excluded from forecasts. Changing the status here does not change your subscription with the provider.</p>
    <div class="flex flex-wrap gap-3">
        <button class="btn btn-primary" type="submit">{{ $subscription->exists ? 'Save changes' : 'Add subscription' }}</button>
        <a class="btn btn-ghost" href="{{ $subscription->exists ? route('subscriptions.show', $subscription) : route('subscriptions.index') }}">Cancel</a>
    </div>
</div>
