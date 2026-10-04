document.addEventListener('DOMContentLoaded', () => {
    const page = document.querySelector('[data-goals-page]');
    if (!page) return;
    const goalDialog = document.getElementById('goal-edit');
    const contributionDialog = document.getElementById('goal-contribution');
    const contributionForm = contributionDialog.querySelector('form');
    const fill = (form, data) => Object.entries(data).forEach(([name, value]) => {
        const field = form.elements.namedItem(name);
        if (field) field.value = value ?? '';
    });
    function openGoal(data = {}) {
        const form = goalDialog.querySelector('form');
        form.reset();
        form.action = data.action || form.dataset.createUrl;
        fill(form, data);
        form.elements.namedItem('_method').value = data.method || (data.action ? 'PUT' : 'POST');
        ['opening', 'start_date'].forEach((name) => { form.elements.namedItem(name).readOnly = Boolean(data.has_history); });
        form.dataset.originalAccountId = String(data.original_asset_id ?? data.asset_id ?? '');
        confirmAccountChange();
        goalDialog.showModal();
    }
    function confirmAccountChange() {
        const form = goalDialog.querySelector('form');
        const account = form.elements.namedItem('asset_id');
        if (account && form.dataset.originalAccountId && String(account.value) !== form.dataset.originalAccountId) {
            form.dataset.confirm = 'Change the account holding this goal? Its protected reserve will move or be released. Account balances stay unchanged; record any actual transfer separately.';
            form.dataset.confirmTitle = 'Change savings account?';
            form.dataset.confirmLabel = 'Save change';
        } else {
            delete form.dataset.confirm;
        }
    }
    goalDialog.querySelector('form').elements.namedItem('asset_id')?.addEventListener('change', confirmAccountChange);
    function updateSource() {
        const form = contributionForm;
        const source = form.elements.namedItem('source').value;
        const existing = source === 'existing';
        const transaction = form.elements.namedItem('transaction_id');
        transaction.disabled = !existing;
        transaction.required = existing;
        form.querySelector('[data-existing-label]').hidden = !existing;
        ['amount', 'date'].forEach((name) => { form.elements.namedItem(name).readOnly = existing; });
        if (existing) {
            const selected = [...transaction.options].find((option) => option.value === transaction.value);
            if (selected?.dataset.amount) fill(form, { amount: selected.dataset.amount, date: selected.dataset.date });
        }
        const origin = form.elements.namedItem('money_origin');
        if (origin) {
            const hasAccount = Boolean(form.dataset.accountId);
            form.querySelector('[data-money-origin]').hidden = !hasAccount;
            origin.disabled = !hasAccount;
            form.querySelector('[data-account-hint]').textContent = `${form.dataset.accountName || 'Linked account'}: ` + (origin.value === 'new'
                ? 'Increases the account balance and goal reserve using this amount and date. Choose this only when the money is not already included in its balance.'
                : 'Protects money already held in the account. Its balance stays unchanged.');
        }
        form.querySelector('[data-contribution-hint]').textContent = form.dataset.linked === 'true'
            ? 'Saving updates the linked budget expense too.'
            : existing ? 'Attach this expense without creating another. Its existing amount and date are used.'
            : source === 'budget' ? 'Creates one expense in the linked savings category. It counts once in your budget and goal.'
            : 'Updates savings progress only. No budget expense is created.';
    }
    function openContribution(data) {
        const form = contributionForm;
        form.reset();
        form.action = data.action;
        form.dataset.linked = data.linked ? 'true' : 'false';
        form.dataset.accountId = String(data.contribution_asset_id || data.asset_id || '');
        form.dataset.accountName = data.contribution_account_name || data.account_name || '';
        const source = form.elements.namedItem('source');
        [...source.options].forEach((option) => {
            option.disabled = data.linked ? option.value !== 'budget' : option.value !== 'goal' && !data.budget_id;
        });
        const transaction = form.elements.namedItem('transaction_id');
        [...transaction.options].forEach((option) => {
            option.hidden = Boolean(option.dataset.budgetId && (option.dataset.budgetId !== String(data.budget_id) || option.dataset.category !== data.category_name));
            option.disabled = option.hidden;
        });
        fill(form, { ...data, money_origin: data.money_origin || 'existing', source: data.linked ? 'budget' : data.source || (data.budget_id ? 'budget' : 'goal') });
        if (!data.request_id && globalThis.crypto?.randomUUID) form.elements.namedItem('request_id').value = crypto.randomUUID();
        updateSource();
        contributionDialog.showModal();
    }
    contributionForm.elements.namedItem('money_origin')?.addEventListener('change', updateSource);
    contributionForm.elements.namedItem('source').addEventListener('change', updateSource);
    contributionForm.elements.namedItem('transaction_id').addEventListener('change', updateSource);
    page.addEventListener('click', (event) => {
        const button = event.target.closest('button');
        if (!button) return;
        if (button.hasAttribute('data-goal-close')) button.closest('dialog').close();
        if (button.hasAttribute('data-goal-new') || button.hasAttribute('data-goal-edit')) openGoal(JSON.parse(button.dataset.goalEdit || button.dataset.goalNew || '{}'));
        if (button.hasAttribute('data-goal-contribute') || button.hasAttribute('data-contribution-edit')) openContribution(JSON.parse(button.dataset.goalContribute || button.dataset.contributionEdit));
    });
    const filter = page.querySelector('[data-goal-filter]');
    function filterContributions() {
        if (!filter) return;
        let visible = 0;
        page.querySelectorAll('[data-goal-row]').forEach((row) => {
            row.hidden = Boolean(filter.value && filter.value !== row.dataset.goalRow);
            if (!row.hidden) visible++;
        });
        page.querySelector('[data-goal-filter-empty]').classList.toggle('hidden', visible > 0);
    }
    filter?.addEventListener('change', filterContributions);
    filterContributions();
    const helper = page.querySelector('[data-emergency-form]');
    if (helper) {
        const budget = helper.elements.namedItem('helper_budget');
        function filterEssentials() {
            helper.querySelectorAll('[data-essential-budget]').forEach((label) => {
                const input = label.querySelector('input');
                label.hidden = label.dataset.essentialBudget !== budget.value;
                input.disabled = label.hidden;
                if (label.hidden) input.checked = false;
            });
        }
        budget.addEventListener('change', filterEssentials);
        filterEssentials();
    }
    const reopen = JSON.parse(page.querySelector('[data-goal-reopen]')?.textContent || 'null');
    if (reopen?.kind === 'goal') openGoal(reopen.data);
    if (reopen?.kind === 'contribution') openContribution(reopen.data);
    page.addEventListener('submit', (event) => {
        const form = event.target;
        if (form.method !== 'post') return;
        if (form.dataset.submitting) { event.preventDefault(); return; }
        form.dataset.submitting = 'true';
        form.querySelectorAll('button[type=submit]').forEach((button) => { button.disabled = true; });
    });
});
