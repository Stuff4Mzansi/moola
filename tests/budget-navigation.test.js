import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';
import vm from 'node:vm';

const source = readFileSync(new URL('../resources/js/budgets.js', import.meta.url), 'utf8');

function navigationHarness(tab) {
    const tabs = ['overview', 'plan', 'expenses', 'subscriptions', 'recurring'].map((name) => ({
        dataset: { budgetTab: name },
        classes: new Set(),
        attributes: {},
        classList: { toggle(value, active) { if (active) this.owner.classes.add(value); else this.owner.classes.delete(value); } },
        setAttribute(name, value) { this.attributes[name] = value; },
        focus() { this.focused = true; },
    }));
    tabs.forEach((item) => { item.classList.owner = item; });
    const panels = tabs.map((item) => ({ dataset: { budgetPanel: item.dataset.budgetTab }, hidden: true }));
    const handlers = new Map();
    const workspace = {
        querySelectorAll(selector) { return selector === '[data-budget-tab]' ? tabs : panels; },
    };
    const page = {
        querySelector(selector) { return selector === '[data-budget-workspace]' ? workspace : { addEventListener() {} }; },
        addEventListener(name, handler) { handlers.set(name, handler); },
    };
    const location = { href: `https://moola.test/budgets?period=42&tab=${encodeURIComponent(tab)}` };
    const document = { querySelector() { return page; }, addEventListener(name, handler) { handlers.set(name, handler); } };
    const history = { replaceState(state, title, url) { location.href = url.toString(); } };
    vm.runInNewContext(source, { document, history, location, URL, WeakSet, Intl });
    handlers.get('DOMContentLoaded')();
    return { tabs, panels, handlers, location };
}

test('dashboard action links open the relevant tab and retain the selected budget period', () => {
    for (const name of ['plan', 'expenses', 'subscriptions', 'recurring']) {
        const { tabs, panels, location } = navigationHarness(name);
        assert.equal(tabs.find((tab) => tab.dataset.budgetTab === name).attributes['aria-selected'], 'true');
        assert.deepEqual(panels.filter((panel) => !panel.hidden).map((panel) => panel.dataset.budgetPanel), [name]);
        assert.equal(new URL(location.href).searchParams.get('period'), '42');
        assert.equal(new URL(location.href).searchParams.get('tab'), name);
    }
});

test('unrecognized or malformed tab URLs fall back to analytics safely', () => {
    for (const name of ['', 'missing', '\"invalid]']) {
        const { tabs, panels, location } = navigationHarness(name);
        assert.equal(tabs[0].attributes['aria-selected'], 'true');
        assert.equal(panels[0].hidden, false);
        assert.equal(new URL(location.href).searchParams.has('tab'), false);
    }
});

test('keyboard navigation updates the link for refresh while retaining the period', () => {
    const { tabs, panels, handlers, location } = navigationHarness('plan');
    const event = { target: { closest() { return tabs[1]; } }, key: 'ArrowRight', preventDefault() {} };
    handlers.get('keydown')(event);
    assert.equal(tabs[2].focused, true);
    assert.equal(panels[2].hidden, false);
    assert.equal(new URL(location.href).searchParams.get('tab'), 'expenses');
    assert.equal(new URL(location.href).searchParams.get('period'), '42');
});
