import assert from 'node:assert/strict';
import test from 'node:test';
import { budgetPage } from '../resources/js/budget-page.js';

function navigationHarness(tab, { includePeriod = true, rememberedPeriod = null, periodOptions = ['42', '41'] } = {}) {
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
    const periodOptionsList = periodOptions.map((value) => ({ value, textContent: `Period ${value}` }));
    const periodSelector = {
        value: '42',
        options: periodOptionsList,
        get selectedOptions() {
            return [periodOptionsList.find((option) => option.value === this.value)];
        },
        matches(selector) {
            return selector === '[data-budget-period-select]';
        },
        closest(selector) {
            return selector === 'form' ? form : null;
        },
    };
    const groups = [];
    const form = { action: '/budgets' };
    const noResults = { hidden: true };
    const picker = { open: true };
    const periodButtons = [];
    const workspace = {
        querySelector(selector) {
            return {
                '[data-budget-period-select]': periodSelector,
                '[data-budget-period-picker]': picker,
                '[data-budget-period-query]': periodSearch,
                '[data-budget-period-empty]': noResults,
            }[selector] ?? null;
        },
        querySelectorAll(selector) {
            return {
                '[data-budget-tab]': tabs,
                '[data-budget-period-group]': groups,
                '[data-budget-period-option]': periodButtons,
            }[selector] ?? [];
        },
    };
    const periodSearch = { value: '' };
    const page = {
        querySelector(selector) {
            return {
                '[data-budget-workspace]': workspace,
                '[data-budget-status]': {},
                '[data-budget-refresh]': { classList: { add() {}, remove() {} } },
                '[data-budget-undo]': {},
            }[selector];
        },
    };
    const location = { href: `https://moola.test/budgets?${includePeriod ? 'period=42&' : ''}tab=${encodeURIComponent(tab)}` };
    const history = { replaceState(state, title, url) { location.href = url.toString(); } };
    const storage = new Map(rememberedPeriod ? [['moola.budgets.last-period', rememberedPeriod]] : []);
    const fetchUrls = [];
    const previousGlobals = {
        document: globalThis.document,
        FormData: globalThis.FormData,
        fetch: globalThis.fetch,
        history: globalThis.history,
        location: globalThis.location,
    };
    const previousLocalStorage = Object.getOwnPropertyDescriptor(globalThis, 'localStorage');
    globalThis.document = { body: { dataset: { currencyPrefix: 'R ' } }, activeElement: null };
    globalThis.FormData = class {
        [Symbol.iterator]() {
            return [['period', periodSelector.value]][Symbol.iterator]();
        }
    };
    globalThis.fetch = async (url) => {
        fetchUrls.push(url.toString());
        return { ok: true, json: async () => ({ message: 'Budget refreshed.' }) };
    };
    globalThis.history = history;
    globalThis.localStorage = {
        getItem(key) {
            return storage.get(key) ?? null;
        },
        setItem(key, value) {
            storage.set(key, value);
        },
    };
    globalThis.location = location;

    const component = budgetPage();
    component.$root = page;
    component.init();

    return {
        component,
        tabs,
        periodSelector,
        noResults,
        picker,
        storage,
        fetchUrls,
        groups,
        periodButtons,
        location,
        restore() {
            for (const [name, value] of Object.entries(previousGlobals)) {
                if (value === undefined) {
                    delete globalThis[name];
                } else {
                    globalThis[name] = value;
                }
            }
            if (previousLocalStorage) {
                Object.defineProperty(globalThis, 'localStorage', previousLocalStorage);
            } else {
                delete globalThis.localStorage;
            }
        },
    };
}

test('the selected period is remembered and restored when no period is requested', async () => {
    const harness = navigationHarness('overview', { includePeriod: false, rememberedPeriod: '41' });
    try {
        assert.equal(harness.periodSelector.value, '41');
        await new Promise((resolve) => setTimeout(resolve, 0));
        assert.equal(new URL(harness.fetchUrls[0]).searchParams.get('period'), '41');

        harness.periodSelector.value = '42';
        harness.component.handleChange({ target: harness.periodSelector });
        assert.equal(harness.storage.get('moola.budgets.last-period'), '42');
    } finally {
        harness.restore();
    }
});

test('period search filters by period and budget name and reports empty results', () => {
    const harness = navigationHarness('overview');
    const first = { dataset: { budgetPeriodSearchTerm: 'personal budget january 01 jan 2026' }, hidden: false };
    const second = { dataset: { budgetPeriodSearchTerm: 'household budget february 01 feb 2026' }, hidden: false };
    harness.periodButtons.push(first, second);
    harness.groups.push(
        { hidden: false, querySelectorAll: () => [first] },
        { hidden: false, querySelectorAll: () => [second] },
    );
    try {
        harness.component.handleInput({ target: { matches: (selector) => selector === '[data-budget-period-query]', value: 'household' } });
        assert.equal(first.hidden, true);
        assert.equal(second.hidden, false);
        assert.equal(harness.groups[0].hidden, true);
        assert.equal(harness.groups[1].hidden, false);
        assert.equal(harness.noResults.hidden, true);

        harness.component.handleInput({ target: { matches: (selector) => selector === '[data-budget-period-query]', value: 'no match' } });
        assert.equal(harness.noResults.hidden, false);
    } finally {
        harness.restore();
    }
});

test('selecting a dropdown period updates persistence and navigates to that period', async () => {
    const harness = navigationHarness('overview');
    try {
        harness.component.selectBudgetPeriod('41');
        assert.equal(harness.periodSelector.value, '41');
        assert.equal(harness.storage.get('moola.budgets.last-period'), '41');
        assert.equal(harness.component.selectedPeriodLabel, 'Period 41');
        assert.equal(harness.picker.open, false);
        await new Promise((resolve) => setTimeout(resolve, 0));
        assert.equal(new URL(harness.fetchUrls[0]).searchParams.get('period'), '41');
    } finally {
        harness.restore();
    }
});

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
