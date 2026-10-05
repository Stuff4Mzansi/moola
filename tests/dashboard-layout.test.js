import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';
import vm from 'node:vm';
import { dashboardTileSize, moveDashboardWidget } from '../resources/js/dashboard-layout.js';

const defaults = ['budgets', 'debts', 'goals'].map((id) => ({ id, visible: true, width: 'wide', height: 'auto' }));

test('reordering preserves widget settings and never mutates the saved layout', () => {
    const layout = defaults.map((widget) => ({ ...widget }));
    layout[1].visible = false;
    assert.deepEqual(moveDashboardWidget(layout, 'goals', 'budgets').map((widget) => widget.id), ['goals', 'budgets', 'debts']);
    assert.deepEqual(moveDashboardWidget(layout, 'budgets', 'goals', true).map((widget) => widget.id), ['debts', 'goals', 'budgets']);
    assert.deepEqual(moveDashboardWidget(layout, 'unknown', 'goals'), layout);
    assert.deepEqual(layout.map((widget) => widget.id), ['budgets', 'debts', 'goals']);
    assert.equal(layout[1].visible, false);
});

test('resizing snaps to supported widths and heights at the boundaries', () => {
    assert.deepEqual(dashboardTileSize(0.3, 320), { width: 'small', height: 'compact' });
    assert.deepEqual(dashboardTileSize(0.5, 480), { width: 'medium', height: 'regular' });
    assert.deepEqual(dashboardTileSize(0.8, 640), { width: 'wide', height: 'tall' });
    assert.deepEqual(dashboardTileSize(5 / 12, 400), { width: 'medium', height: 'regular' });
    assert.deepEqual(dashboardTileSize(0.75, 560), { width: 'wide', height: 'tall' });
});

function harness({ saved = defaults, fail = false, mobile = false } = {}) {
    class Element {
        constructor(attributes = {}, parent = null) {
            this.attributes = attributes; this.parent = parent; this.dataset = {}; this.children = []; this.handlers = {}; this.hidden = false; this.disabled = false;
            this.value = ''; this.checked = false; this.textContent = ''; this.focused = false;
            Object.entries(attributes).forEach(([key, value]) => { if (key.startsWith('data-')) this.dataset[key.slice(5).replace(/-([a-z])/g, (_, letter) => letter.toUpperCase())] = value; });
        }
        matches(selector) { return selector.split(', ').some((part) => part === '.dashboard-widget-body' ? this.attributes.class === 'dashboard-widget-body' : Object.hasOwn(this.attributes, part.slice(1, -1))); }
        closest(selector) { return this.matches(selector) ? this : this.parent?.closest(selector) ?? null; }
        querySelectorAll(selector) { return this.children.flatMap((child) => [...(child.matches(selector) ? [child] : []), ...child.querySelectorAll(selector)]); }
        querySelector(selector) { return this.querySelectorAll(selector)[0]; }
        appendChild(child) { this.children = this.children.filter((item) => item !== child); this.children.push(child); child.parent = this; }
        addEventListener(name, callback) { this.handlers[name] = callback; }
        setAttribute(name, value) { this.attributes[name] = value; }
        focus() { this.focused = true; }
        setPointerCapture() {}
        getBoundingClientRect() { return { width: 1200, height: 640, top: 0 }; }
    }
    const page = new Element({ 'data-dashboard-customization': '', 'data-layout-url': '/dashboard/layout', 'data-layout-token': 'token' });
    const elements = {};
    const add = (parent, attribute, value = '') => { const element = new Element({ [attribute]: value }, parent); parent.children.push(element); return element; };
    for (const name of ['grid', 'data', 'defaults', 'empty', 'panel', 'edit', 'status']) elements[name] = add(page, `data-layout-${name}`);
    for (const name of ['save', 'cancel', 'reset']) elements[name] = add(elements.panel, `data-layout-${name}`);
    elements.data.textContent = JSON.stringify(saved); elements.defaults.textContent = JSON.stringify(defaults);
    const widgets = new Map();
    for (const widget of saved) {
        const tile = add(elements.grid, 'data-layout-widget', widget.id);
        const toolbar = add(tile, 'data-layout-toolbar');
        const controls = {};
        for (const name of ['width', 'height', 'hide', 'drag', 'resize']) controls[name] = add(toolbar, `data-layout-${name}`);
        controls.up = add(toolbar, 'data-layout-move', 'up'); controls.down = add(toolbar, 'data-layout-move', 'down');
        controls.visible = add(elements.panel, 'data-layout-visible', widget.id);
        const body = new Element({ class: 'dashboard-widget-body' }, tile); tile.children.push(body);
        widgets.set(widget.id, { tile, ...controls });
    }
    const document = { querySelector: () => page, addEventListener(name, handler) { this[name] = handler; } };
    const window = { handlers: {}, matchMedia: () => ({ matches: mobile }), addEventListener(name, handler) { this.handlers[name] = handler; } };
    const requests = [];
    const fetch = async (url, options) => {
        requests.push({ url, ...options, body: JSON.parse(options.body) });
        return { ok: !fail, json: async () => fail ? { message: 'Connection failed' } : { widgets: JSON.parse(options.body).widgets, message: 'Dashboard layout saved.' } };
    };
    const source = readFileSync(new URL('../resources/js/dashboard-layout.js', import.meta.url), 'utf8').replace(/^export /gm, '');
    vm.runInNewContext(source, { document, window, fetch, Map }); document.DOMContentLoaded();
    const dispatch = (name, target, extra = {}) => page.handlers[name]({ target, preventDefault() {}, ...extra });
    return { elements, widgets, requests, window, dispatch, order: () => elements.grid.children.map((tile) => tile.dataset.layoutWidget), fail(value) { fail = value; } };
}

test('visibility sizes and keyboard reorder preview together and Cancel restores saved layout', () => {
    const h = harness(); const budgets = h.widgets.get('budgets');
    h.elements.edit.handlers.click();
    budgets.visible.checked = false; h.dispatch('change', budgets.visible);
    assert.equal(budgets.tile.hidden, true);
    h.dispatch('click', h.widgets.get('goals').up);
    assert.deepEqual(h.order(), ['budgets', 'goals', 'debts']);
    const goals = h.widgets.get('goals'); goals.width.value = 'small'; h.dispatch('change', goals.width);
    goals.height.value = 'compact'; h.dispatch('change', goals.height);
    assert.equal(goals.tile.dataset.width, 'small'); assert.equal(goals.tile.dataset.height, 'compact');
    const event = { prevented: false, preventDefault() { this.prevented = true; } }; h.window.handlers.beforeunload(event);
    assert.equal(event.prevented, true);
    h.elements.cancel.handlers.click();
    assert.deepEqual(h.order(), defaults.map((widget) => widget.id)); assert.equal(budgets.tile.hidden, false);
    assert.equal(goals.tile.dataset.width, 'wide'); assert.equal(h.elements.panel.hidden, true);
    assert.equal(h.requests.length, 0);
});

test('failed saves retain changes for retry and successful saves become the new baseline', async () => {
    const h = harness({ fail: true }); h.elements.edit.handlers.click();
    h.dispatch('click', h.widgets.get('budgets').hide);
    await h.elements.save.handlers.click();
    assert.equal(h.elements.panel.hidden, false); assert.equal(h.widgets.get('budgets').tile.hidden, true);
    assert.match(h.elements.status.textContent, /Connection failed.*changes are still here/);
    h.fail(false); await h.elements.save.handlers.click();
    assert.equal(h.elements.panel.hidden, true); assert.equal(h.requests[1].method, 'PUT');
    assert.equal(h.requests[1].headers['X-CSRF-TOKEN'], 'token'); assert.equal(h.requests[1].body.widgets[0].visible, false);
    h.elements.edit.handlers.click(); h.elements.reset.handlers.click();
    assert.equal(h.widgets.get('budgets').tile.hidden, false);
    h.elements.cancel.handlers.click(); assert.equal(h.widgets.get('budgets').tile.hidden, true);
});

test('drag and corner resizing preserve chart elements and support pointer cancellation', () => {
    const h = harness(); h.elements.edit.handlers.click();
    const goals = h.widgets.get('goals'); const body = goals.tile.querySelector('.dashboard-widget-body');
    const dataTransfer = { setData() {} };
    h.dispatch('dragstart', goals.drag, { dataTransfer });
    h.dispatch('drop', h.widgets.get('budgets').tile, { clientY: 0 });
    h.dispatch('dragend', goals.drag);
    assert.deepEqual(h.order(), ['goals', 'budgets', 'debts']);
    h.dispatch('pointerdown', goals.resize, { button: 0, clientX: 1200, clientY: 640, pointerId: 1 });
    h.dispatch('pointermove', goals.resize, { clientX: 400, clientY: 320 });
    assert.equal(goals.tile.dataset.width, 'small'); assert.equal(goals.tile.dataset.height, 'compact');
    assert.equal(goals.tile.querySelector('.dashboard-widget-body'), body);
    h.dispatch('pointercancel', goals.resize);
    assert.equal(goals.tile.dataset.width, 'wide'); assert.equal(goals.tile.dataset.height, 'auto');
});

test('mobile resizing adjusts height while retaining the chosen desktop width', () => {
    const h = harness({ mobile: true }); h.elements.edit.handlers.click(); const goals = h.widgets.get('goals');
    h.dispatch('pointerdown', goals.resize, { button: 0, clientX: 1200, clientY: 640, pointerId: 1 });
    h.dispatch('pointermove', goals.resize, { clientX: 400, clientY: 320 });
    h.dispatch('pointerup', goals.resize);
    assert.equal(goals.tile.dataset.width, 'wide'); assert.equal(goals.tile.dataset.height, 'compact');
});
