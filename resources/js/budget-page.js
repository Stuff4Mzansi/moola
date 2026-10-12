export function budgetPage() {
    let component;
    let page;
    let workspace;
    let status;
    let refresh;
    let undo;
    let queue = Promise.resolve();
    let deferredHtml = null;
    let undoRecord = null;
    let pendingMutations = 0;
    const submittingForms = new WeakSet();
    const currencyPrefix = document.body.dataset.currencyPrefix || 'R ';
    const money = (cents) => `${currencyPrefix}${new Intl.NumberFormat('en-ZA', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(cents / 100)}`;
    const editing = () => workspace.contains(document.activeElement) && document.activeElement.matches('input:not([type=hidden]), textarea, select');

    function selectTab(name, focus = false) {
        const selected = [...workspace.querySelectorAll('[data-budget-tab]')].find((tab) => tab.dataset.budgetTab === name);
        if (!selected) {
            return;
        }

        component.activeTab = name;
        const url = new URL(location.href);
        if (name === 'overview') {
            url.searchParams.delete('tab');
        } else {
            url.searchParams.set('tab', name);
        }
        history.replaceState(null, '', url);
        if (focus) {
            selected.focus();
        }
    }

    function updateWorkspace(html, force = false) {
        const parsed = document.createElement('div');
        parsed.innerHTML = html;
        const nextPeriod = parsed.querySelector('[data-budget-version]')?.dataset.budgetPeriod;
        if (nextPeriod !== workspace.querySelector('[data-budget-version]')?.dataset.budgetPeriod) {
            component.activeTab = 'overview';
        }
        const version = parsed.querySelector('[data-budget-version]')?.dataset.budgetVersion;
        const current = workspace.querySelector('[data-budget-version]');
        if (current && version) {
            current.dataset.budgetVersion = version;
        }
        page.querySelectorAll('input[name=version]').forEach((input) => {
            if (version) {
                input.value = version;
            }
        });
        if (!force && (editing() || pendingMutations > 1)) {
            parsed.querySelectorAll('[data-budget-live]').forEach((next) => {
                const existing = [...workspace.querySelectorAll('[data-budget-live]')].find((element) => element.dataset.budgetLive === next.dataset.budgetLive);
                if (existing) {
                    existing.replaceChildren(...next.cloneNode(true).childNodes);
                }
            });
            deferredHtml = html;
        } else {
            workspace.innerHTML = html;
            deferredHtml = null;
            selectTab(component.activeTab);
        }
    }

    async function request(url, body = null, form = null, force = true) {
        status.textContent = body ? 'Saving…' : 'Loading budget…';
        if (form) {
            form.querySelector('[data-form-errors]')?.remove();
        }
        if (body?.has('version')) {
            body.set('version', workspace.querySelector('[data-budget-version]')?.dataset.budgetVersion ?? body.get('version'));
        }
        const response = await fetch(url, { method: body ? 'POST' : 'GET', body, headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
        const result = await response.json();
        if (!response.ok) {
            const errors = result.errors ? Object.values(result.errors).flat().join(' ') : result.message;
            throw new Error(errors || 'Your change was not saved. Please retry.');
        }
        if (result.html !== undefined) {
            if (force) {
                form?.closest('dialog')?.close();
            }
            updateWorkspace(result.html, force);
            if (result.url) {
                const responseUrl = new URL(result.url, location.href);
                if (component.activeTab !== 'overview') {
                    responseUrl.searchParams.set('tab', component.activeTab);
                }
                history.replaceState(null, '', responseUrl);
            }
        }
        if (result.undo_id) {
            undoRecord = {
                id: result.undo_id,
                url: workspace.querySelector('[data-budget-version]').dataset.budgetUndoUrl,
                period: workspace.querySelector('[data-budget-version]').dataset.budgetPeriod,
            };
            undo.classList.remove('hidden');
        }
        status.textContent = result.message || 'Saved.';
        refresh.classList.add('hidden');
        return result;
    }

    function enqueue(url, body, form = null, force = true) {
        if (force && form && submittingForms.has(form)) {
            return queue;
        }
        if (force && form) {
            submittingForms.add(form);
            form.querySelectorAll('button[type=submit]').forEach((button) => {
                button.disabled = true;
            });
        }
        pendingMutations++;
        queue = queue.then(() => request(url, body, form, force)).catch((error) => {
            status.textContent = `Not saved: ${error.message}`;
            refresh.classList.remove('hidden');
            if (form?.isConnected) {
                let alert = form.querySelector('[data-form-errors]');
                if (!alert) {
                    alert = document.createElement('div');
                    alert.dataset.formErrors = '';
                    alert.className = 'alert alert-error text-sm';
                    alert.setAttribute('role', 'alert');
                    form.prepend(alert);
                }
                alert.textContent = error.message;
            }
            return null;
        }).finally(() => {
            pendingMutations--;
            if (force && form) {
                submittingForms.delete(form);
                form.querySelectorAll('button[type=submit]').forEach((button) => {
                    button.disabled = false;
                });
            }
        });
        return queue;
    }

    function validForm(form) {
        const received = form.elements.namedItem('received_amount');
        const receivedDate = form.elements.namedItem('received_date');
        if (received && receivedDate) {
            receivedDate.required = Number(received.value) > 0;
        }
        return form.reportValidity();
    }

    function filterExpenses() {
        const search = (page.querySelector('[data-budget-search]')?.value || '').toLocaleLowerCase();
        const category = page.querySelector('[data-budget-filter]')?.value || '';
        const rows = [...page.querySelectorAll('[data-budget-expense-row]')];
        let matches = 0;
        rows.forEach((row) => {
            const match = row.textContent.toLocaleLowerCase().includes(search) && (!category || row.dataset.category === category);
            row.hidden = !match;
            if (match) {
                matches++;
            }
        });
        page.querySelector('[data-budget-no-results]')?.classList.toggle('hidden', matches > 0 || rows.length === 0);
    }

    function undoDeletion() {
        if (!undoRecord) {
            return;
        }
        if (workspace.querySelector('[data-budget-version]')?.dataset.budgetPeriod !== undoRecord.period) {
            status.textContent = 'Open the period containing the deleted expense before undoing.';
            return;
        }
        const body = new FormData();
        body.set('_token', page.querySelector('input[name=_token]').value);
        body.set('id', undoRecord.id);
        body.set('version', workspace.querySelector('[data-budget-version]').dataset.budgetVersion);
        enqueue(undoRecord.url, body).then((result) => {
            if (result) {
                undo.classList.add('hidden');
                undoRecord = null;
            }
        });
    }

    return {
        activeTab: 'overview',

        init() {
            component = this;
            page = this.$root;
            workspace = page.querySelector('[data-budget-workspace]');
            status = page.querySelector('[data-budget-status]');
            refresh = page.querySelector('[data-budget-refresh]');
            undo = page.querySelector('[data-budget-undo]');
            if (!workspace) {
                return;
            }
            const requestedTab = new URL(location.href).searchParams.get('tab');
            const validTab = [...workspace.querySelectorAll('[data-budget-tab]')].some((tab) => tab.dataset.budgetTab === requestedTab);
            selectTab(validTab ? requestedTab : 'overview');
        },

        handleKeydown(event) {
            const tab = event.target.closest('[data-budget-tab]');
            if (!tab || !['ArrowLeft', 'ArrowRight', 'Home', 'End'].includes(event.key)) {
                return;
            }
            event.preventDefault();
            const tabs = [...workspace.querySelectorAll('[data-budget-tab]')];
            const index = tabs.indexOf(tab);
            const next = event.key === 'Home' ? 0 : event.key === 'End' ? tabs.length - 1 : (index + (event.key === 'ArrowRight' ? 1 : -1) + tabs.length) % tabs.length;
            selectTab(tabs[next].dataset.budgetTab, true);
        },

        handleSubmit(event) {
            const form = event.target.closest('[data-budget-action]');
            if (!form) {
                return;
            }
            event.preventDefault();
            if (!validForm(form)) {
                return;
            }
            if (form.hasAttribute('data-budget-dates')) {
                const changed = ['start_date', 'end_date'].some((field) => form.elements.namedItem(field).value !== form.elements.namedItem(field).defaultValue);
                if (changed && !form.elements.namedItem('preview_token').value) {
                    status.textContent = 'Preview the date changes before applying.';
                    form.querySelector('[data-preview-result]').textContent = 'Choose Preview impact before applying these dates.';
                    return;
                }
            }
            enqueue(form.action, new FormData(form), form, true);
        },

        handleChange(event) {
            if (event.target.closest('[data-budget-navigation]')) {
                const form = event.target.closest('form');
                enqueue(`${form.action}?${new URLSearchParams(new FormData(form))}`, null);
                return;
            }
            if (event.target.matches('[data-budget-scope]')) {
                event.target.closest('form').querySelector('[data-budget-include]').checked = event.target.value === 'personal';
            }
            if (event.target.closest('[data-budget-dates]') && ['start_date', 'end_date'].includes(event.target.name)) {
                event.target.closest('form').elements.namedItem('preview_token').value = '';
            }
            const assignment = event.target.closest('[data-budget-group-assignment]');
            if (assignment) {
                enqueue(assignment.action, new FormData(assignment), assignment, true);
                return;
            }
            const form = event.target.closest('[data-budget-autosave]');
            if (form) {
                if (event.target.matches('[data-category-limit]')) {
                    const automatic = form.querySelector('input[type=checkbox][name=automatic]');
                    if (automatic) {
                        automatic.checked = false;
                    }
                }
                if (validForm(form)) {
                    enqueue(form.action, new FormData(form), form, false);
                }
            }
            filterExpenses();
        },

        handleInput(event) {
            if (event.target.matches('[data-budget-search]')) {
                filterExpenses();
            }
            if (event.target.closest('[data-budget-dates]') && ['start_date', 'end_date'].includes(event.target.name)) {
                event.target.closest('form').elements.namedItem('preview_token').value = '';
            }
        },

        handleClick(event) {
            const button = event.target.closest('button');
            if (!button) {
                return;
            }
            if (button.hasAttribute('data-budget-refresh')) {
                enqueue(location.href, null);
                return;
            }
            if (button.hasAttribute('data-budget-undo')) {
                undoDeletion();
                return;
            }
            if (button.hasAttribute('data-budget-tab')) {
                selectTab(button.dataset.budgetTab);
            }
            if (button.hasAttribute('data-budget-go')) {
                selectTab(button.dataset.budgetGo, true);
            }
            if (button.hasAttribute('data-open-dialog')) {
                const dialog = document.getElementById(button.dataset.openDialog);
                if (!dialog) {
                    return;
                }
                if (button.hasAttribute('data-new-expense') || button.hasAttribute('data-edit-expense')) {
                    const form = dialog.querySelector('[data-budget-action]');
                    form.reset();
                    const expense = button.dataset.editExpense ? JSON.parse(button.dataset.editExpense) : { id: '', description: '', linked: false };
                    for (const [field, value] of Object.entries(expense)) {
                        const input = form.elements.namedItem(field);
                        if (input) {
                            input.value = value ?? '';
                        }
                    }
                    const occurrence = form.elements.namedItem('recurring_charge_id');
                    [...occurrence.options].forEach((option) => {
                        option.disabled = expense.linked || (option.dataset.paidTransaction && option.dataset.paidTransaction !== String(expense.id));
                    });
                    const category = form.elements.namedItem('category_id');
                    [...category.options].forEach((option) => {
                        option.disabled = expense.linked && option.value !== String(expense.category_id);
                    });
                    form.querySelector('[data-linked-expense-hint]').classList.toggle('hidden', !expense.linked);
                    form.querySelector('[data-form-errors]')?.remove();
                }
                if (button.hasAttribute('data-new-recurring') || button.hasAttribute('data-edit-recurring')) {
                    const form = dialog.querySelector('[data-budget-action]');
                    form.reset();
                    form.elements.namedItem('id').value = '';
                    const expense = button.dataset.editRecurring ? JSON.parse(button.dataset.editRecurring) : {};
                    for (const [field, value] of Object.entries(expense)) {
                        const input = form.elements.namedItem(field);
                        if (input) {
                            input.value = value ?? '';
                        }
                    }
                    form.querySelector('[data-form-errors]')?.remove();
                }
                if (button.hasAttribute('data-pay-charge') || button.hasAttribute('data-pay-recurring')) {
                    const form = dialog.querySelector('[data-budget-action]');
                    form.elements.namedItem('id').value = button.dataset.payCharge ?? button.dataset.payRecurring;
                    form.elements.namedItem('amount').value = button.dataset.chargeAmount;
                    form.querySelector('[data-charge-label]').textContent = `Record the payment for ${button.dataset.chargeName}. Confirm the amount and payment date.`;
                }
                button.closest('details')?.removeAttribute('open');
                dialog.showModal();
            }
            if (button.hasAttribute('data-close-dialog')) {
                button.closest('dialog')?.close();
            }
            if (button.hasAttribute('data-scroll-to')) {
                const target = document.getElementById(button.dataset.scrollTo);
                const panel = target?.closest('[data-budget-panel]');
                if (panel) {
                    selectTab(panel.dataset.budgetPanel);
                }
                target?.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }
            if (button.hasAttribute('data-preview-dates')) {
                const form = button.closest('form');
                if (!validForm(form)) {
                    return;
                }
                queue = queue.then(async () => {
                    const result = await request(form.dataset.previewUrl, new FormData(form), form, false);
                    form.elements.namedItem('preview_token').value = result.preview_token;
                    form.querySelector('[data-preview-result]').textContent = `${result.message} Expected subscription charges: ${money(result.old_subscription_cents)} → ${money(result.new_subscription_cents)} (${result.new_charge_count} charges). Recurring expense forecast: ${money(result.old_recurring_cents)} to ${money(result.new_recurring_cents)} (${result.new_recurring_count} occurrences). ${result.transaction_count} recorded expenses retained.`;
                }).catch((error) => {
                    status.textContent = error.message;
                    form.elements.namedItem('preview_token').value = '';
                    form.querySelector('[data-preview-result]').textContent = error.message;
                });
            }
        },

        handleFocusOut() {
            setTimeout(() => {
                if (deferredHtml && !editing() && pendingMutations === 0) {
                    updateWorkspace(deferredHtml, true);
                }
            }, 0);
        },
    };
}
