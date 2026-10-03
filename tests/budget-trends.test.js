import assert from 'node:assert/strict';
import test from 'node:test';
import { selectTrendPeriods, summarizeTrendPeriods, trendGeometry } from '../resources/js/budget-trends.js';

const period = (planned, spent, complete = true) => ({ planned, spent, complete });

test('history modes select the latest periods in chronological order without inventing missing data', () => {
    const periods = Array.from({ length: 15 }, (_, id) => ({ id, ...period(10000, 5000) }));
    assert.deepEqual(selectTrendPeriods(periods, '3').map((item) => item.id), [12, 13, 14]);
    assert.equal(selectTrendPeriods(periods, '6').length, 6);
    assert.equal(selectTrendPeriods(periods, '12').length, 12);
    assert.equal(selectTrendPeriods(periods, 'all').length, 15);
    assert.equal(selectTrendPeriods([periods[0]], '6').length, 1);
    assert.equal(periods.length, 15);
});

test('completed variance excludes unfinished periods and still identifies overspent periods despite a net underspend', () => {
    const summary = summarizeTrendPeriods([period(10000, 5000), period(10000, 12000), period(100000, 1000, false)]);
    assert.equal(summary.planned, 120000);
    assert.equal(summary.spent, 18000);
    assert.equal(summary.completedDifference, 3000);
    assert.equal(summary.overCount, 1);
    assert.equal(summary.difference, 3000);
});

test('a current period shows remaining room or overspending so far without claiming a completed result', () => {
    assert.equal(summarizeTrendPeriods([period(10000, 3000, false)]).difference, 7000);
    assert.equal(summarizeTrendPeriods([period(10000, 12000, false)]).difference, -2000);
    assert.equal(summarizeTrendPeriods([period(10000, 12000)]).difference, -2000);
    assert.equal(summarizeTrendPeriods([period(0, 0, false)]).difference, 0);
});

test('charts support a single zero period and scale overspending and long histories without invalid geometry', () => {
    for (const periods of [[period(0, 0)], [period(10000, 30000)], Array.from({ length: 24 }, () => period(10000, 20000))]) {
        const layout = trendGeometry(periods);
        assert.ok(Number.isFinite(layout.maximum));
        layout.points.forEach((point) => {
            assert.ok(point.x >= layout.left && point.x <= layout.right);
            assert.ok(point.plannedY >= layout.top && point.plannedY <= layout.bottom);
            assert.ok(point.spentY >= layout.top && point.spentY <= layout.bottom);
        });
    }
    assert.ok(trendGeometry(Array.from({ length: 24 }, () => period(10000, 5000))).width > 760);
});


async function chartHarness(budgets) {
    const { readFileSync } = await import('node:fs');
    const { default: vm } = await import('node:vm');
    class Element {
        constructor(tag = 'div') { this.tag = tag; this.children = []; this.attributes = {}; this.handlers = {}; this.classes = new Set(); this.dataset = {}; this.value = ''; this.hidden = false; this.textContent = ''; this.classList = { toggle: (name, enabled) => { if (enabled) this.classes.add(name); else this.classes.delete(name); } }; }
        setAttribute(name, value) { this.attributes[name] = value; }
        append(...children) { this.children.push(...children); }
        replaceChildren(...children) { this.children = children; }
        addEventListener(name, handler) { this.handlers[name] = handler; }
    }
    const names = ['data', 'budget', 'range', 'controls', 'content', 'empty', 'planned', 'spent', 'variance-label', 'variance', 'variance-detail', 'window', 'chart', 'table', 'insight'];
    const elements = new Map(names.map((name) => [`[data-trend-${name}]`, new Element()]));
    elements.get('[data-trend-data]').textContent = JSON.stringify(budgets);
    elements.get('[data-trend-range]').value = '6';
    const modes = ['line', 'bar'].map((mode) => { const element = new Element('button'); element.dataset.trendMode = mode; return element; });
    const section = { querySelector(selector) { return elements.get(selector); }, querySelectorAll() { return modes; } };
    const handlers = {};
    const document = { querySelector() { return section; }, createElement(tag) { return new Element(tag); }, createElementNS(namespace, tag) { return new Element(tag); }, addEventListener(name, handler) { handlers[name] = handler; } };
    const source = readFileSync(new URL('../resources/js/budget-trends.js', import.meta.url), 'utf8').replace(/^export /gm, '');
    vm.runInNewContext(source, { document, Intl, Date });
    handlers.DOMContentLoaded();
    return { elements, modes };
}

test('chart controls switch modes ranges and budgets and show one period without requiring another', async () => {
    const periods = Array.from({ length: 8 }, (_, id) => ({ id, label: `Period ${id}`, start: `2026-0${id + 1}-01`, end: `2026-0${id + 1}-28`, url: `/budgets?period=${id}`, ...period(10000, 5000) }));
    const other = { id: 99, label: 'Current only', start: '2026-10-03', end: '2026-10-31', url: '/budgets?period=99', ...period(20000, 25000, false) };
    const { elements, modes } = await chartHarness([{ id: 1, name: 'Historic', periods }, { id: 2, name: 'Current', periods: [other] }]);
    const get = (name) => elements.get(`[data-trend-${name}]`);
    assert.equal(get('budget').value, '2');
    assert.equal(get('table').children.length, 1);
    assert.ok(get('chart').children[0].children.some((child) => child.tag === 'a'));
    modes[1].handlers.click();
    assert.equal(modes[1].attributes['aria-pressed'], 'true');
    assert.ok(get('chart').children[0].children.find((child) => child.tag === 'a').children.some((child) => child.tag === 'rect'));
    assert.match(get('variance-label').textContent, /so far/);
    get('budget').value = '1';
    get('budget').handlers.change();
    assert.equal(get('table').children.length, 6);
    get('range').value = '3';
    get('range').handlers.change();
    assert.equal(get('table').children.length, 3);
    modes[0].handlers.click();
    assert.ok(get('chart').children[0].children.some((child) => child.tag === 'path'));
    get('range').value = 'all';
    get('range').handlers.change();
    assert.equal(get('table').children.length, 8);
    assert.equal(get('table').children[0].children[0].children[0].href, '/budgets?period=0');
});

test('future-only budgets show an empty state while controls can return to historical data', async () => {
    const { elements } = await chartHarness([{ id: 1, name: 'Later', periods: [] }, { id: 2, name: 'Earlier', periods: [{ id: 1, label: 'Past', start: '2026-09-01', end: '2026-09-30', url: '/budgets?period=1', ...period(0, 0) }] }]);
    const get = (name) => elements.get(`[data-trend-${name}]`);
    assert.equal(get('budget').value, '2');
    get('budget').value = '1';
    get('budget').handlers.change();
    assert.equal(get('content').hidden, true);
    assert.equal(get('empty').hidden, false);
    get('budget').value = '2';
    get('budget').handlers.change();
    assert.equal(get('content').hidden, false);
    assert.equal(get('empty').hidden, true);
    assert.equal(get('table').children.length, 1);
});
