import test from 'node:test';
import vm from 'node:vm';
import { readFileSync } from 'node:fs';
import assert from 'node:assert/strict';
import { liquidityGeometry } from '../resources/js/liquidity.js';

test('forecast geometry contains negative gaps and buffer without widening for longer horizons', () => {
    const days = Array.from({ length: 90 }, (_, index) => ({ date: `day-${index}`, low: -10000 + index * 1000, closing: index * 1000 }));
    const geometry = liquidityGeometry(days, 120000);
    assert.equal(geometry.width, 640);
    assert.ok(geometry.minimum < -10000);
    assert.ok(geometry.maximum > 120000);
    assert.equal(geometry.points[0].x, geometry.left);
    assert.equal(geometry.points.at(-1).x, geometry.right);
    assert.ok(geometry.y(-10000) > geometry.y(0));
});
test('empty and flat forecasts retain finite scales', () => {
    for (const days of [[], [{ low: 0, closing: 0 }]]) {
        const geometry = liquidityGeometry(days);
        assert.ok(Number.isFinite(geometry.y(0)));
        assert.ok(geometry.maximum > geometry.minimum);
    }
});


function reserveHarness(reopen = null) {
    const handlers = {};
    const names = ['reserve_id', 'asset_id', 'purpose', 'goal_id', 'name', 'amount'];
    const fields = Object.fromEntries(names.map((name) => [name, { value: '' }]));
    fields.asset_id.selectedOptions = [{ dataset: { free: '10000' } }];
    const sections = Object.fromEntries(['[data-reserve-goal]', '[data-reserve-name]', '[data-reserve-capacity]'].map((name) => [name, {}]));
    const form = { elements: { namedItem: (name) => fields[name] }, reset() { names.forEach((name) => { fields[name].value = ''; }); fields.purpose.value = 'emergency'; }, querySelector: (name) => sections[name], addEventListener(name, handler) { handlers[name] = handler; } };
    const dialog = { querySelector: () => form, showModal() { this.open = true; }, close() { this.open = false; } };
    const document = { getElementById: () => dialog, querySelectorAll: () => [], querySelector: (name) => name === '[data-reserve-reopen]' ? { textContent: JSON.stringify(reopen) } : { addEventListener(name, handler) { handlers[name] = handler; } }, addEventListener(name, handler) { handlers[name] = handler; } };
    vm.runInNewContext(readFileSync(new URL('../resources/js/liquidity.js', import.meta.url), 'utf8').replaceAll('export function', 'function'), { document, Intl });
    handlers.DOMContentLoaded();
    return { fields, sections, dialog, change: () => handlers.change(), click(attribute, data = null) { handlers.click({ target: { closest: () => ({ dataset: { reserveEdit: JSON.stringify(data) }, hasAttribute: (name) => name === attribute }) } }); } };
}

test('reserve edits preserve allocation capacity and new reserves reset goal controls', () => {
    const view = reserveHarness();
    view.click('data-reserve-edit', { reserve_id: 1, asset_id: '4', purpose: 'goal', goal_id: 2, amount: '50' });
    assert.equal(view.dialog.open, true);
    assert.equal(view.fields.goal_id.disabled, false);
    assert.equal(view.fields.goal_id.required, true);
    assert.match(view.sections['[data-reserve-capacity]'].textContent, /150/);
    view.fields.asset_id.value = '5';
    view.change();
    assert.match(view.sections['[data-reserve-capacity]'].textContent, /100/);
    view.click('data-reserve-new');
    assert.equal(view.fields.reserve_id.value, '');
    assert.equal(view.fields.goal_id.disabled, true);
    assert.equal(view.sections['[data-reserve-goal]'].hidden, true);
});

test('reserve validation restores values and makes custom names required', () => {
    const view = reserveHarness({ asset_id: '4', purpose: 'other', name: 'Keep me', amount: '1.001' });
    assert.equal(view.dialog.open, true);
    assert.equal(view.fields.name.value, 'Keep me');
    assert.equal(view.fields.name.required, true);
    assert.equal(view.fields.amount.value, '1.001');
    view.click('data-reserve-close');
    assert.equal(view.dialog.open, false);
});
