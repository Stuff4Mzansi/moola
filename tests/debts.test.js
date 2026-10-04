import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';
import vm from 'node:vm';

const source = readFileSync(new URL('../resources/js/debts.js', import.meta.url), 'utf8');

function debtHarness(reopen = null, fetch = undefined) {
    const handlers = {};
    const buildForm = (names) => {
        const fields = new Map(names.map((name) => [name, { value: '', readOnly: false, disabled: false, addEventListener(name, handler) { this[name] = handler; } }]));
        const charge = fields.get('charge_id');
        if (charge) charge.options = [{ dataset: {}, value: '' }, { dataset: { debtId: '1' }, value: '11' }, { dataset: { debtId: '2' }, value: '22' }];
        const label = { hidden: false };
        const interestLabel = { textContent: '' };
        const summary = { textContent: '' };
        const hint = { textContent: '' };
        return { dataset: { createUrl: '/debts' }, addEventListener(name, handler) { this[name] = handler; }, elements: { namedItem(name) { return fields.get(name); } }, reset() { fields.forEach((field) => { field.value = ''; }); }, querySelector(selector) { return selector === '[data-debt-charge-label]' ? label : selector === '[data-interest-label]' ? interestLabel : selector === '[data-interest-summary]' ? summary : hint; }, fields, label, hint, summary };
    };
    const debtForm = buildForm(['_method', 'debt_id', 'name', 'balance', 'balance_date', 'annual_rate', 'minimum_payment', 'due_anchor', 'budget_id', 'notes']);
    const paymentForm = buildForm(['debt_id', 'payment_id', 'amount', 'interest', 'interest_mode', 'date', 'charge_id', 'notes', 'request_id']);
    const dialog = (form) => ({ open: false, querySelector() { return form; }, showModal() { this.open = true; }, close() { this.open = false; } });
    const debtDialog = dialog(debtForm);
    const paymentDialog = dialog(paymentForm);
    const page = { dataset: fetch ? { interestUrl: '/debts/__DEBT__/interest' } : {}, querySelector(selector) { return selector === '[data-debt-reopen]' ? { textContent: JSON.stringify(reopen) } : null; }, addEventListener(name, handler) { handlers[name] = handler; } };
    const document = { querySelector() { return page; }, getElementById(id) { return id === 'debt-edit' ? debtDialog : paymentDialog; }, addEventListener(name, handler) { handlers[name] = handler; } };
    vm.runInNewContext(source, { document, clearTimeout, setTimeout, fetch, URLSearchParams });
    handlers.DOMContentLoaded();
    function click(attribute, data) {
        const dataset = { [attribute]: data ? JSON.stringify(data) : '' };
        const button = { dataset, hasAttribute(name) { return name === 'data-' + attribute.replace(/[A-Z]/g, (letter) => '-' + letter.toLowerCase()); } };
        handlers.click({ target: { closest() { return button; } } });
    }
    return { click, debtForm, paymentForm, debtDialog, paymentDialog };
}

test('editing a debt protects its balance baseline and opening a new debt resets edit state', () => {
    const { click, debtForm, debtDialog } = debtHarness();
    click('debtEdit', { action: '/debts/1', debt_id: 1, name: 'Loan', balance: '100', has_history: true });
    assert.equal(debtDialog.open, true);
    assert.equal(debtForm.action, '/debts/1');
    assert.equal(debtForm.fields.get('_method').value, 'PUT');
    assert.equal(debtForm.fields.get('balance').readOnly, true);
    click('debtNew');
    assert.equal(debtForm.action, '/debts');
    assert.equal(debtForm.fields.get('_method').value, 'POST');
    assert.equal(debtForm.fields.get('balance').readOnly, false);
    assert.equal(debtForm.fields.get('debt_id').value, '');
});

test('a linked payment cannot select another expense and new payments reset their link controls', () => {
    const { click, paymentForm, paymentDialog } = debtHarness();
    click('debtPaymentEdit', { action: '/debts/1/payments', debt_id: 1, payment_id: 9, amount: '100', interest: '10', linked: true });
    assert.equal(paymentDialog.open, true);
    assert.equal(paymentForm.fields.get('interest').value, '10');
    assert.equal(paymentForm.fields.get('charge_id').disabled, true);
    click('debtPay', { action: '/debts/2/payments', debt_id: 2, amount: '200' });
    assert.equal(paymentForm.fields.get('interest_mode').value, 'automatic');
    assert.equal(paymentForm.fields.get('interest').readOnly, true);
    assert.equal(paymentForm.fields.get('interest').disabled, true);
    paymentForm.fields.get('interest_mode').value = 'manual';
    paymentForm.fields.get('interest_mode').change();
    assert.equal(paymentForm.fields.get('interest').disabled, false);
    assert.equal(paymentForm.fields.get('charge_id').disabled, false);
    assert.equal(paymentForm.label.hidden, false);
    assert.equal(paymentForm.fields.get('payment_id').value, '');
    assert.equal(paymentForm.fields.get('charge_id').options[1].disabled, true);
    assert.equal(paymentForm.fields.get('charge_id').options[2].disabled, false);
});

test('validation errors reopen the correct dialog and preserve entered values and update method', () => {
    const { debtForm, debtDialog } = debtHarness({ kind: 'debt', data: { action: '/debts/1', method: 'PUT', name: 'Keep my name', balance: '100.001', has_history: true } });
    assert.equal(debtDialog.open, true);
    assert.equal(debtForm.fields.get('name').value, 'Keep my name');
    assert.equal(debtForm.fields.get('_method').value, 'PUT');
    const { paymentForm, paymentDialog } = debtHarness({ kind: 'payment', data: { action: '/debts/1/payments', debt_id: 1, amount: '100', interest: '101', request_id: 'original-token' } });
    assert.equal(paymentDialog.open, true);
    assert.equal(paymentForm.fields.get('interest').value, '101');
    assert.equal(paymentForm.fields.get('request_id').value, 'original-token');
});


test('automatic estimates refresh the displayed split and stale responses cannot overwrite a manual value', async () => {
    const requests = [];
    const fetch = (url) => new Promise((resolve) => requests.push({ url, resolve }));
    const { click, paymentForm } = debtHarness(null, fetch);
    click('debtPay', { action: '/debts/1/payments', debt_id: 1, amount: '100', date: '2026-10-04' });
    assert.match(requests[0].url, /amount=100/);
    requests[0].resolve({ ok: true, json: async () => ({ interest: '0.99', principal: '99.01' }) });
    await new Promise(setImmediate);
    assert.equal(paymentForm.fields.get('interest').value, '0.99');
    assert.match(paymentForm.summary.textContent, /99.01 towards your balance/);
    click('debtPay', { action: '/debts/2/payments', debt_id: 2, amount: '200', date: '2026-10-04' });
    paymentForm.fields.get('interest_mode').value = 'manual';
    paymentForm.fields.get('interest_mode').change();
    paymentForm.fields.get('interest').value = '12';
    requests[1].resolve({ ok: true, json: async () => ({ interest: '2', principal: '198' }) });
    await new Promise(setImmediate);
    assert.equal(paymentForm.fields.get('interest').value, '12');
    assert.equal(paymentForm.fields.get('interest').disabled, false);
});
