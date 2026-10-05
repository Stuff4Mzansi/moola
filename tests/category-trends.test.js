import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';
import vm from 'node:vm';
import { summarizeCategoryTrends } from '../resources/js/category-trends.js';
import { selectTrendPeriods, trendGeometry } from '../resources/js/budget-trends.js';

const category = (name, spent, planned = 10000) => ({ key: `custom:${name}`, name, spent, planned });
const period = (id, days, received, categories, complete = true) => ({ id, label: `Period ${id}`, start: `2026-0${id}-01`, end: `2026-0${id}-28`, days, received, categories, complete, url: `/budgets?period=${id}` });

test('category changes normalize custom period lengths and use received income for shares', () => {
    const rows = summarizeCategoryTrends([
        period(1, 10, 20000, [category('Food', 10000)]),
        period(2, 20, 30000, [category('Food', 20000)]),
        period(3, 30, 10000, [category('Food', 90000)], false),
    ]);
    assert.equal(rows[0].change, 0);
    assert.ok(Math.abs(rows[0].shareChange - 16.666666666666657) < 0.00001);
    assert.equal(rows[0].points.length, 2);
    assert.equal(rows[0].latest.category.spent, 20000);
});

test('missing and renamed categories remain gaps and cannot generate comparisons or repeated overspending', () => {
    const rows = summarizeCategoryTrends([
        period(1, 30, 100000, [category('Food', 20000)]),
        period(2, 30, 100000, [category('Groceries', 20000)]),
        period(3, 30, 100000, [category('Food', 20000)]),
    ]);
    const food = rows.find((row) => row.name === 'Food');
    assert.equal(food.points[1].daily, null);
    assert.equal(food.change, null);
    assert.equal(food.repeated, false);
    assert.equal(rows.find((row) => row.name === 'Groceries').latest.category, null);
});

test('repeated overspending requires three consecutive completed periods with positive allocations', () => {
    const periods = [1, 2, 3].map((id) => period(id, 30, 100000, [category('Food', 11000), category('Unplanned', 11000, 0)]));
    const rows = summarizeCategoryTrends(periods);
    assert.equal(rows.find((row) => row.name === 'Food').repeated, true);
    assert.equal(rows.find((row) => row.name === 'Food').overCount, 3);
    assert.equal(rows.find((row) => row.name === 'Unplanned').repeated, false);
    assert.equal(summarizeCategoryTrends(periods.slice(0, 2))[0].repeated, false);
});

test('single periods zero income and empty history never invent percentage changes', () => {
    assert.deepEqual(summarizeCategoryTrends([]), []);
    const rows = summarizeCategoryTrends([period(1, 1, 0, [category('Food', 0, 0)])]);
    assert.equal(rows[0].change, null);
    assert.equal(rows[0].shareChange, null);
    assert.equal(rows[0].latest.share, null);
    assert.equal(rows[0].latest.daily, 0);
});

function harness(budgets) {
    class Element {
        constructor(tag = 'div') { this.tag = tag; this.children = []; this.attributes = {}; this.handlers = {}; this.value = ''; this.textContent = ''; this.hidden = false; }
        setAttribute(name, value) { this.attributes[name] = value; }
        append(...children) { this.children.push(...children); }
        replaceChildren(...children) { this.children = children; }
        addEventListener(name, handler) { this.handlers[name] = handler; }
    }
    const elements = new Map(['data', 'budget', 'range', 'select', 'controls', 'content', 'empty', 'window', 'insights', 'chart', 'table'].map((name) => [`[data-category-${name}]`, new Element()]));
    elements.get('[data-category-data]').textContent = JSON.stringify(budgets);
    elements.get('[data-category-range]').value = '6';
    const section = { querySelector(selector) { return elements.get(selector); } };
    const handlers = {};
    const document = { querySelector() { return section; }, createElement(tag) { return new Element(tag); }, createElementNS(namespace, tag) { return new Element(tag); }, addEventListener(name, handler) { handlers[name] = handler; } };
    const source = readFileSync(new URL('../resources/js/category-trends.js', import.meta.url), 'utf8').replace(/^import .*;\r?\n/gm, '').replace(/^export /gm, '');
    vm.runInNewContext(source, { document, Intl, Map, selectTrendPeriods, trendGeometry });
    handlers.DOMContentLoaded();
    return (name) => elements.get(`[data-category-${name}]`);
}

test('category chart controls switch budgets history and categories while preserving review links', () => {
    const periods = [1, 2, 3, 4].map((id) => period(id, 30, 100000, [category('Food', id * 10000), category('Travel', 5000)]));
    const get = harness([{ id: 1, name: 'Household', periods }, { id: 2, name: 'Empty', periods: [] }]);
    assert.equal(get('table').children.length, 2);
    assert.equal(get('chart').children[0].children.filter((node) => node.tag === 'a').length, 4);
    get('range').value = '3'; get('range').handlers.change();
    assert.equal(get('chart').children[0].children.filter((node) => node.tag === 'a').length, 3);
    get('select').value = 'custom:Travel'; get('select').handlers.change();
    assert.match(get('chart').children[0].children[0].textContent, /Travel/);
    assert.equal(get('chart').children[0].children.find((node) => node.tag === 'a').attributes.href, '/budgets?period=2');
    get('budget').value = '2'; get('budget').handlers.change();
    assert.equal(get('content').hidden, true);
    get('budget').value = '1'; get('budget').handlers.change();
    assert.equal(get('content').hidden, false);
});

test('charts leave a gap rather than joining spending across a missing category period', () => {
    const get = harness([{ id: 1, name: 'History', periods: [period(1, 30, 100000, [category('Food', 10000)]), period(2, 30, 100000, []), period(3, 30, 100000, [category('Food', 20000)])] }]);
    const svg = get('chart').children[0];
    assert.equal(svg.children.filter((node) => node.tag === 'a').length, 2);
    assert.equal(svg.children.filter((node) => node.tag === 'line').length, 5);
    assert.ok(svg.children.some((node) => node.textContent === 'No category'));
});
