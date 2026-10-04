import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';
import vm from 'node:vm';

const source = readFileSync(new URL('../resources/js/goals.js', import.meta.url), 'utf8');

function goalsHarness(reopen = null) {
    const handlers = {};
    function form(names) {
        const fields = new Map(names.map((name) => [name, { value: '', readOnly: false, disabled: false, addEventListener(event, handler) { this[event] = handler; } }]));
        const label = { hidden: false };
        const hint = { textContent: '' };
        return { dataset: { createUrl: '/goals' }, elements: { namedItem(name) { return fields.get(name); } }, fields, label, hint, reset() { fields.forEach((field) => { field.value = ''; }); }, querySelector(selector) { return selector === '[data-existing-label]' ? label : hint; } };
    }
    const goalForm = form(['_method', 'goal_id', 'name', 'kind', 'target', 'opening', 'start_date', 'target_date', 'monthly', 'category_id', 'notes']);
    const contributionForm = form(['goal_id', 'contribution_id', 'request_id', 'amount', 'date', 'source', 'transaction_id', 'notes']);
    contributionForm.fields.get('source').options = ['goal', 'budget', 'existing'].map((value) => ({ value }));
    contributionForm.fields.get('transaction_id').options = [
        { value: '', dataset: {} },
        { value: '10', dataset: { budgetId: '1', category: 'Savings', amount: '100', date: '2026-10-04' } },
        { value: '20', dataset: { budgetId: '2', category: 'Savings', amount: '200', date: '2026-10-03' } },
        { value: '30', dataset: { budgetId: '1', category: 'Food', amount: '50', date: '2026-10-02' } },
    ];
    const dialog = (form) => ({ open: false, querySelector() { return form; }, showModal() { this.open = true; } });
    const goalDialog = dialog(goalForm);
    const contributionDialog = dialog(contributionForm);
    const page = { querySelector(selector) { return selector === '[data-goal-reopen]' ? { textContent: JSON.stringify(reopen) } : null; }, addEventListener(name, handler) { handlers[name] = handler; } };
    const document = { querySelector() { return page; }, getElementById(id) { return id === 'goal-edit' ? goalDialog : contributionDialog; }, addEventListener(name, handler) { handlers[name] = handler; } };
    vm.runInNewContext(source, { document });
    handlers.DOMContentLoaded();
    function click(attribute, data) {
        const dataset = { [attribute]: data ? JSON.stringify(data) : '' };
        const button = { dataset, hasAttribute(name) { return name === 'data-' + attribute.replace(/[A-Z]/g, (letter) => '-' + letter.toLowerCase()); } };
        handlers.click({ target: { closest() { return button; } } });
    }
    return { click, goalForm, contributionForm, goalDialog, contributionDialog };
}

test('goal presets populate the form and starting savings are protected only when history exists', () => {
    const { click, goalForm, goalDialog } = goalsHarness();
    click('goalEdit', { action: '/goals/1', name: 'Holiday', opening: '100', has_history: true });
    assert.equal(goalForm.fields.get('_method').value, 'PUT');
    assert.equal(goalForm.fields.get('opening').readOnly, true);
    click('goalNew', { name: 'Emergency fund', kind: 'emergency', target: '900' });
    assert.equal(goalDialog.open, true);
    assert.equal(goalForm.action, '/goals');
    assert.equal(goalForm.fields.get('_method').value, 'POST');
    assert.equal(goalForm.fields.get('opening').readOnly, false);
    assert.equal(goalForm.fields.get('target').value, '900');
});

test('attaching an existing expense filters the right category and uses its recorded amount and date', () => {
    const { click, contributionForm } = goalsHarness();
    click('goalContribute', { action: '/goals/1/contributions', goal_id: 1, budget_id: 1, category_name: 'Savings' });
    assert.equal(contributionForm.fields.get('source').value, 'budget');
    const transaction = contributionForm.fields.get('transaction_id');
    assert.equal(transaction.options[1].disabled, false);
    assert.equal(transaction.options[2].disabled, true);
    assert.equal(transaction.options[3].disabled, true);
    contributionForm.fields.get('source').value = 'existing';
    contributionForm.fields.get('source').change();
    transaction.value = '10';
    transaction.change();
    assert.equal(contributionForm.fields.get('amount').value, '100');
    assert.equal(contributionForm.fields.get('date').value, '2026-10-04');
    assert.equal(contributionForm.fields.get('amount').readOnly, true);
    assert.equal(transaction.required, true);
});

test('linked contributions keep their expense and new unlinked forms reset all link state', () => {
    const { click, contributionForm } = goalsHarness();
    click('contributionEdit', { action: '/goals/1/contributions', goal_id: 1, contribution_id: 5, linked: true });
    assert.equal(contributionForm.fields.get('source').options[0].disabled, true);
    assert.equal(contributionForm.fields.get('source').value, 'budget');
    click('goalContribute', { action: '/goals/2/contributions', goal_id: 2 });
    assert.equal(contributionForm.fields.get('contribution_id').value, '');
    assert.equal(contributionForm.fields.get('source').value, 'goal');
    assert.equal(contributionForm.fields.get('source').options[1].disabled, true);
    assert.equal(contributionForm.fields.get('amount').readOnly, false);
});

test('validation errors reopen the right form with entered values and request token intact', () => {
    const { goalForm, goalDialog } = goalsHarness({ kind: 'goal', data: { action: '/goals/1', method: 'PUT', name: 'Retain this name', target: '1.001', has_history: true } });
    assert.equal(goalDialog.open, true);
    assert.equal(goalForm.fields.get('target').value, '1.001');
    assert.equal(goalForm.fields.get('_method').value, 'PUT');
    const { contributionForm, contributionDialog } = goalsHarness({ kind: 'contribution', data: { action: '/goals/1/contributions', amount: '2.001', request_id: 'keep-token' } });
    assert.equal(contributionDialog.open, true);
    assert.equal(contributionForm.fields.get('amount').value, '2.001');
    assert.equal(contributionForm.fields.get('request_id').value, 'keep-token');
});
