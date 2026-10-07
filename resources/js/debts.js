document.addEventListener('DOMContentLoaded', () => {
    const page = document.querySelector('[data-debts-page]');
    if (!page) return;
    const debtDialog = document.getElementById('debt-edit');
    const paymentDialog = document.getElementById('debt-payment');
    const fill = (form, data) => Object.entries(data).forEach(([name, value]) => { const field = form.elements.namedItem(name); if (field) field.value = value ?? ''; });
    function openDebt(data = {}) {
        const form = debtDialog.querySelector('form');
        form.reset();
        form.action = data.action || form.dataset.createUrl;
        form.elements.namedItem('_method').value = data.method || (data.action ? 'PUT' : 'POST');
        fill(form, data);
        ['balance', 'balance_date'].forEach((name) => { form.elements.namedItem(name).readOnly = Boolean(data.has_history); });
        debtDialog.showModal();
    }
    const paymentForm = paymentDialog.querySelector('form');
    const currencySymbol = document.body.dataset.currencySymbol || 'R';
    let estimateVersion = 0;
    let estimateTimer;
    async function updateInterest() {
        const version = ++estimateVersion;
        const mode = paymentForm.elements.namedItem('interest_mode');
        const interest = paymentForm.elements.namedItem('interest');
        const summary = paymentForm.querySelector('[data-interest-summary]');
        const automatic = mode.value === 'automatic';
        interest.readOnly = automatic;
        interest.disabled = automatic;
        interest.required = !automatic;
        paymentForm.querySelector('[data-interest-label]').textContent = automatic ? `Estimated interest (${currencySymbol})` : `Actual interest (${currencySymbol})`;
        if (!automatic) {
            summary.textContent = "Use the interest portion from your lender's statement. The rest reduces principal.";
            return;
        }
        const amount = paymentForm.elements.namedItem('amount').value;
        const date = paymentForm.elements.namedItem('date').value;
        if (!amount || Number(amount) <= 0 || !date) {
            interest.value = '';
            summary.textContent = 'Enter an amount and date to see your estimated split.';
            return;
        }
        if (!page.dataset?.interestUrl) return;
        summary.textContent = 'Calculating your payment split...';
        try {
            const params = new URLSearchParams({ amount, date });
            const paymentId = paymentForm.elements.namedItem('payment_id').value;
            if (paymentId) params.set('payment_id', paymentId);
            const url = page.dataset.interestUrl.replace('__DEBT__', paymentForm.elements.namedItem('debt_id').value);
            const response = await fetch(`${url}?${params}`, { headers: { Accept: 'application/json' } });
            if (!response.ok) throw new Error('Estimate unavailable');
            const split = await response.json();
            if (version !== estimateVersion) return;
            interest.value = split.interest;
            const currencyPrefix = document.body.dataset.currencyPrefix || 'R ';
            summary.textContent = `Estimated split: ${currencyPrefix}${split.interest} interest + ${currencyPrefix}${split.principal} towards your balance.`;
        } catch {
            if (version !== estimateVersion) return;
            interest.value = '';
            summary.textContent = 'Preview unavailable. Check the amount and date; we calculate the estimate when you save.';
        }
    }
    paymentForm.addEventListener('input', (event) => {
        if (!['amount', 'date'].includes(event.target.name)) return;
        ++estimateVersion;
        clearTimeout(estimateTimer);
        estimateTimer = setTimeout(updateInterest, 200);
    });
    paymentForm.elements.namedItem('interest_mode').addEventListener('change', updateInterest);
    function openPayment(data) {
        const form = paymentDialog.querySelector('form');
        form.reset();
        form.action = data.action;
        fill(form, { ...data, interest_mode: data.interest_mode || (data.payment_id || data.interest !== undefined ? 'manual' : 'automatic') });
        clearTimeout(estimateTimer);
        updateInterest();
        const charge = form.elements.namedItem('charge_id');
        [...charge.options].forEach((option) => { option.hidden = Boolean(option.dataset.debtId && option.dataset.debtId !== String(data.debt_id)); option.disabled = option.hidden; });
        charge.disabled = Boolean(data.linked);
        form.querySelector('[data-debt-charge-label]').hidden = Boolean(data.linked);
        form.querySelector('[data-debt-link-hint]').textContent = data.linked ? 'This payment is already linked. Saving updates its budget expense too.' : 'Selecting a scheduled expense records it once in both places. Already recorded it in a budget? Edit that payment instead.';
        paymentDialog.showModal();
    }
    page.addEventListener('click', (event) => {
        const button = event.target.closest('button');
        if (!button) return;
        if (button.hasAttribute('data-debt-close')) button.closest('dialog').close();
        if (button.hasAttribute('data-debt-new') || button.hasAttribute('data-debt-edit')) openDebt(button.dataset.debtEdit ? JSON.parse(button.dataset.debtEdit) : {});
        if (button.hasAttribute('data-debt-pay') || button.hasAttribute('data-debt-payment-edit')) openPayment(JSON.parse(button.dataset.debtPay || button.dataset.debtPaymentEdit));
    });
    const filter = page.querySelector('[data-debt-filter]');
    function filterPayments() {
        if (!filter) return;
        let visible = 0;
        page.querySelectorAll('[data-debt-payment-row]').forEach((row) => { row.hidden = Boolean(filter.value && row.dataset.debtPaymentRow !== filter.value); if (!row.hidden) visible++; });
        page.querySelector('[data-debt-filter-empty]').classList.toggle('hidden', visible > 0);
    }
    filter?.addEventListener('change', filterPayments);
    filterPayments();
    const reopen = JSON.parse(page.querySelector('[data-debt-reopen]')?.textContent || 'null');
    if (reopen?.kind === 'debt') openDebt(reopen.data);
    if (reopen?.kind === 'payment') openPayment(reopen.data);
    page.addEventListener('submit', (event) => {
        const form = event.target;
        if (form.method !== 'post') return;
        if (form.dataset.submitting) { event.preventDefault(); return; }
        form.dataset.submitting = 'true';
        form.querySelectorAll('button[type=submit]').forEach((button) => { button.disabled = true; });
    });
});
