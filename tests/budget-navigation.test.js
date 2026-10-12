import assert from 'node:assert/strict';
import test from 'node:test';
import { budgetPage } from '../resources/js/budget-page.js';

function navigationHarness(tab) {
    const tabs = ['overview', 'review', 'plan', 'expenses', 'subscriptions', 'recurring', 'notifications'].map((name) => ({
        dataset: { budgetTab: name },
        focused: false,
        hasAttribute(attribute) {
            return attribute === 'data-budget-tab';
        },
        focus() {
            this.focused = true;
        },
    }));
    const workspace = {
        querySelectorAll(selector) {
            return selector === '[data-budget-tab]' ? tabs : [];
        },
    };
    const page = {
        querySelector(selector) {
            return {
                '[data-budget-workspace]': workspace,
                '[data-budget-status]': {},
                '[data-budget-refresh]': {},
                '[data-budget-undo]': {},
            }[selector];
        },
    };
    const location = { href: `https://moola.test/budgets?period=42&tab=${encodeURIComponent(tab)}` };
    const history = { replaceState(state, title, url) { location.href = url.toString(); } };
    const previousGlobals = {
        document: globalThis.document,
        history: globalThis.history,
        location: globalThis.location,
    };
    globalThis.document = { body: { dataset: { currencyPrefix: 'R ' } }, activeElement: null };
    globalThis.history = history;
    globalThis.location = location;

    const component = budgetPage();
    component.$root = page;
    component.init();

    return {
        component,
        tabs,
        location,
        restore() {
            for (const [name, value] of Object.entries(previousGlobals)) {
                if (value === undefined) {
                    delete globalThis[name];
                } else {
                    globalThis[name] = value;
                }
            }
        },
    };
}

test('budget navigation selects requested tabs while retaining the selected period', () => {
    for (const name of ['review', 'plan', 'expenses', 'subscriptions', 'recurring', 'notifications']) {
        const harness = navigationHarness(name);
        try {
            assert.equal(harness.component.activeTab, name);
            assert.equal(new URL(harness.location.href).searchParams.get('period'), '42');
            assert.equal(new URL(harness.location.href).searchParams.get('tab'), name);
        } finally {
            harness.restore();
        }
    }
});

test('unrecognized or malformed tab URLs fall back to overview', () => {
    for (const name of ['', 'missing', '"invalid]']) {
        const harness = navigationHarness(name);
        try {
            assert.equal(harness.component.activeTab, 'overview');
            assert.equal(new URL(harness.location.href).searchParams.get('period'), '42');
            assert.equal(new URL(harness.location.href).searchParams.has('tab'), false);
        } finally {
            harness.restore();
        }
    }
});

test('keyboard navigation updates the selected tab and URL while retaining the period', () => {
    const harness = navigationHarness('plan');
    try {
        const event = {
            target: { closest() { return harness.tabs[2]; } },
            key: 'ArrowRight',
            preventDefault() {},
        };
        harness.component.handleKeydown(event);
        assert.equal(harness.tabs[3].focused, true);
        assert.equal(harness.component.activeTab, 'expenses');
        assert.equal(new URL(harness.location.href).searchParams.get('tab'), 'expenses');
        assert.equal(new URL(harness.location.href).searchParams.get('period'), '42');
    } finally {
        harness.restore();
    }
});
