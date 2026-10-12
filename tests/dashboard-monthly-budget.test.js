import assert from 'node:assert/strict';
import test from 'node:test';
import { dashboardMonthlyBudget } from '../resources/js/dashboard-monthly-budget.js';

const budgets = [
    { id: '12', title: 'Budget for this month', spentLabel: 'R 500.00' },
    { id: '19', title: 'Current budget period', spentLabel: 'R 900.00' },
];

function createComponent(selectedId = '12') {
    const root = {
        querySelector(selector) {
            if (selector === '[data-budget-choices]') {
                return { textContent: JSON.stringify(budgets) };
            }
            if (selector === '#dashboard-budget-period') {
                return { value: selectedId };
            }
            return null;
        },
    };

    return Object.assign(dashboardMonthlyBudget(), { $el: root });
}

test('monthly budget widget initializes from the selected period', () => {
    const component = createComponent('19');
    component.init();

    assert.equal(component.selectedBudget.title, 'Current budget period');
    assert.equal(component.selectedBudget.spentLabel, 'R 900.00');
});

test('changing period updates the widget selection and address without reloading', () => {
    const component = createComponent();
    const previousWindow = globalThis.window;
    const replacements = [];
    globalThis.window = {
        location: { href: 'https://moola.test/?budget=12&tab=summary#budget' },
        history: { replaceState(...args) { replacements.push(args); } },
    };

    try {
        component.init();
        component.budgetId = '19';
        component.selectBudget();

        assert.equal(component.selectedBudget.spentLabel, 'R 900.00');
        assert.equal(replacements[0][2].toString(), 'https://moola.test/?budget=19&tab=summary#budget');
    } finally {
        globalThis.window = previousWindow;
    }
});
