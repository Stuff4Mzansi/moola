import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';
import vm from 'node:vm';
import { selectWorthSnapshots, worthGeometry } from '../resources/js/net-worth.js';

const points = [
    { date: '2026-09-01', assets: 10000, debts: 20000, netWorth: -10000 },
    { date: '2026-09-02', assets: 25000, debts: 15000, netWorth: 10000 },
    { date: '2026-10-02', assets: 45000, debts: 10000, netWorth: 35000 },
];

test('net worth geometry includes negative balances and spaces irregular dates proportionally', () => {
    const geometry = worthGeometry(points, true);
    assert.ok(geometry.minimum < -10000);
    assert.ok(geometry.maximum > 45000);
    assert.ok(geometry.y(-10000) > geometry.y(0));
    assert.ok(geometry.y(45000) < geometry.y(0));
    const short = geometry.points[1].x - geometry.points[0].x;
    const long = geometry.points[2].x - geometry.points[1].x;
    assert.ok(Math.abs(long / short - 30) < 0.00001);
});

test('empty zero and single snapshot charts have finite scales and range selection does not change the source', () => {
    const single = [{ date: '2026-10-04', assets: 0, debts: 0, netWorth: 0 }];
    assert.ok(Number.isFinite(worthGeometry([]).y(0)));
    assert.ok(Number.isFinite(worthGeometry(single).points[0].x));
    assert.ok(Number.isFinite(worthGeometry(single).y(0)));
    const list = Array.from({ length: 15 }, (_, index) => ({ ...single[0], netWorth: index }));
    assert.equal(selectWorthSnapshots(list).length, 6);
    assert.equal(selectWorthSnapshots(list, '12').length, 12);
    assert.equal(selectWorthSnapshots(list, 'all').length, 15);
    assert.equal(list.length, 15);
    assert.equal(worthGeometry(single, false, true).height, 150);
    assert.equal(worthGeometry(single, false, false, 1000).width, 1000);
});

function worthHarness(reopen = null, liabilityKinds = []) {
    const handlers = {};
    const form = (names) => {
        const fields = new Map(names.map((name) => [name, { value: '', disabled: false, addEventListener() {} }]));
        const initial = { hidden: false };
        return { dataset: { createUrl: '/net-worth/assets' }, fields, initial, reset() { fields.forEach((field) => { field.value = ''; }); }, elements: { namedItem(name) { return fields.get(name); } }, querySelector(selector) { return selector === '[data-asset-initial]' ? initial : (this.sections[selector] ??= { hidden: true }); }, sections: {} };
    };
    const assetForm = form(['asset_id', '_method', 'name', 'kind', 'institution', 'notes', 'amount', 'date', 'liquidity', 'access_days', 'available_date', 'value_uncertain']);
    assetForm.fields.get('value_uncertain').type = 'checkbox';
    const valueForm = form(['asset_id', 'valuation_id', 'amount', 'date', 'notes']);
    const liabilityForm = form(['liability_id', '_method', 'name', 'kind', 'institution', 'notes', 'amount', 'date']);
    const liabilityValueForm = form(['liability_id', 'valuation_id', 'amount', 'date', 'notes']);
    const snapshotForm = form(['date']);
    const dialog = (form) => ({ open: false, querySelector() { return form; }, showModal() { this.open = true; } });
    const assetDialog = dialog(assetForm), valueDialog = dialog(valueForm), liabilityDialog = dialog(liabilityForm), liabilityValueDialog = dialog(liabilityValueForm), snapshotDialog = dialog(snapshotForm);
    const liabilityFilter = { value: '', addEventListener(name, handler) { this[name] = handler; } };
    const liabilityFilterEmpty = { classList: { toggle(name, hidden) { this.hidden = hidden; } } };
    const liabilityRows = liabilityKinds.map((kind) => ({ dataset: { liabilityRow: kind }, hidden: false }));
    const page = {
        addEventListener(name, handler) { handlers[name] = handler; },
        querySelector(selector) {
            if (selector === '[data-worth-reopen]') return { textContent: JSON.stringify(reopen) };
            if (selector === '[data-liability-filter]') return liabilityFilter;
            if (selector === '[data-liability-filter-empty]') return liabilityFilterEmpty;
            return null;
        },
        querySelectorAll(selector) { return selector === '[data-liability-row]' ? liabilityRows : []; },
    };
    const document = { addEventListener(name, handler) { handlers[name] = handler; }, querySelectorAll() { return []; }, querySelector() { return page; }, getElementById(id) { if (id === 'asset-edit') return assetDialog; if (id === 'asset-value') return valueDialog; if (id === 'liability-edit') return liabilityDialog; if (id === 'liability-value') return liabilityValueDialog; return snapshotDialog; } };
    const source = readFileSync(new URL('../resources/js/net-worth.js', import.meta.url), 'utf8').replaceAll('export function', 'function');
    vm.runInNewContext(source, { document });
    handlers.DOMContentLoaded();
    const click = (attribute, data) => {
        const button = { dataset: { [attribute]: data ? JSON.stringify(data) : '' }, hasAttribute(name) { return name === 'data-' + attribute.replace(/[A-Z]/g, (letter) => '-' + letter.toLowerCase()); } };
        handlers.click({ target: { closest() { return button; } } });
    };
    return { click, assetForm, valueForm, liabilityForm, liabilityValueForm, snapshotForm, assetDialog, valueDialog, liabilityDialog, liabilityValueDialog, snapshotDialog, liabilityFilter, liabilityFilterEmpty, liabilityRows };
}

test('liability type filter updates visible rows and reports an unmatched type', () => {
    const { liabilityFilter, liabilityFilterEmpty, liabilityRows } = worthHarness(null, ['credit_card', 'mortgage']);

    assert.deepEqual(liabilityRows.map((row) => row.hidden), [false, false]);
    liabilityFilter.value = 'mortgage';
    liabilityFilter.change();
    assert.deepEqual(liabilityRows.map((row) => row.hidden), [true, false]);
    assert.equal(liabilityFilterEmpty.classList.hidden, true);

    liabilityFilter.value = 'tax';
    liabilityFilter.change();
    assert.deepEqual(liabilityRows.map((row) => row.hidden), [true, true]);
    assert.equal(liabilityFilterEmpty.classList.hidden, false);
});

test('asset metadata edits hide initial values and a new asset resets those fields', () => {
    const { click, assetForm } = worthHarness();
    click('assetEdit', { action: '/net-worth/assets/1', asset_id: 1, name: 'Account' });
    assert.equal(assetForm.fields.get('_method').value, 'PUT');
    assert.equal(assetForm.fields.get('amount').disabled, true);
    assert.equal(assetForm.initial.hidden, true);
    click('assetNew');
    assert.equal(assetForm.fields.get('_method').value, 'POST');
    assert.equal(assetForm.fields.get('amount').disabled, false);
    assert.equal(assetForm.initial.hidden, false);
    assert.equal(assetForm.fields.get('asset_id').value, '');
});

test('valuation edits retain their id and recording a new value clears edit state', () => {
    const { click, valueForm } = worthHarness();
    click('assetValue', { action: '/net-worth/assets/1/values', valuation_id: 8, amount: '100', date: '2026-09-01' });
    assert.equal(valueForm.fields.get('valuation_id').value, 8);
    click('assetValue', { action: '/net-worth/assets/1/values', amount: '200', date: '2026-10-04' });
    assert.equal(valueForm.fields.get('valuation_id').value, '');
    assert.equal(valueForm.fields.get('amount').value, '200');
});

test('liability editing opens the liability dialogs and retains field state', () => {
    const { click, liabilityForm, liabilityValueForm } = worthHarness();
    click('liabilityEdit', { action: '/net-worth/liabilities/1', liability_id: 1, name: 'Credit card', kind: 'credit', amount: '2500', date: '2026-09-01' });
    assert.equal(liabilityForm.fields.get('_method').value, 'PUT');
    assert.equal(liabilityForm.fields.get('amount').disabled, true);
    assert.equal(liabilityForm.fields.get('name').value, 'Credit card');
    click('liabilityNew');
    assert.equal(liabilityForm.fields.get('_method').value, 'POST');
    assert.equal(liabilityForm.fields.get('amount').disabled, false);
    click('liabilityValue', { action: '/net-worth/liabilities/1/values', valuation_id: 9, amount: '2750', date: '2026-10-02' });
    assert.equal(liabilityValueForm.fields.get('valuation_id').value, 9);
    assert.equal(liabilityValueForm.fields.get('amount').value, '2750');
});

test('validation recovery opens asset value and snapshot dialogs with retained input', () => {
    const { assetForm, assetDialog } = worthHarness({ kind: 'asset', data: { action: '/net-worth/assets', method: 'POST', name: 'Keep name', amount: '1.001' } });
    assert.equal(assetDialog.open, true);
    assert.equal(assetForm.fields.get('_method').value, 'POST');
    assert.equal(assetForm.fields.get('amount').value, '1.001');
    const { valueForm, valueDialog } = worthHarness({ kind: 'value', data: { action: '/net-worth/assets/1/values', amount: '-1' } });
    assert.equal(valueDialog.open, true);
    assert.equal(valueForm.fields.get('amount').value, '-1');
    const { snapshotForm, snapshotDialog } = worthHarness({ kind: 'snapshot', data: { date: '2026-09-01' } });
    assert.equal(snapshotDialog.open, true);
    assert.equal(snapshotForm.fields.get('date').value, '2026-09-01');
});


test('asset access editing checks uncertainty and enables only applicable date or notice inputs', () => {
    const { click, assetForm } = worthHarness();
    click('assetEdit', { action: '/assets/1', liquidity: 'dated', available_date: '2026-11-01', value_uncertain: true });
    assert.equal(assetForm.fields.get('value_uncertain').checked, true);
    assert.equal(assetForm.fields.get('available_date').disabled, false);
    assert.equal(assetForm.fields.get('available_date').required, true);
    assert.equal(assetForm.fields.get('access_days').disabled, true);
    click('assetEdit', { action: '/assets/1', liquidity: 'delayed', access_days: 30, value_uncertain: false });
    assert.equal(assetForm.fields.get('value_uncertain').checked, false);
    assert.equal(assetForm.fields.get('available_date').disabled, true);
    assert.equal(assetForm.fields.get('access_days').required, true);
});
