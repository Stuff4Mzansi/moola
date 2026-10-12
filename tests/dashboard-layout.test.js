import assert from 'node:assert/strict';
import test from 'node:test';
import { dashboardLayout } from '../resources/js/dashboard-layout.js';

const defaults = ['budgets', 'debts', 'goals'].map((id) => ({ id, visible: true, width: 'wide', height: 'auto' }));

function makeElement(attributes = {}, parent = null) {
    return {
        attributes,
        dataset: Object.fromEntries(Object.entries(attributes).filter(([key]) => key.startsWith('data-')).map(([key, value]) => [
            key.slice(5).replace(/-([a-z])/g, (_, letter) => letter.toUpperCase()),
            value,
        ])),
        parent,
        closest(selector) {
            const attribute = selector.slice(1, -1);
            return Object.hasOwn(this.attributes, attribute) ? this : this.parent?.closest(selector) ?? null;
        },
        focus() {
            this.focused = true;
        },
        setPointerCapture() {},
        hasPointerCapture() {
            return true;
        },
        releasePointerCapture() {},
        getBoundingClientRect() {
            return { width: 1200 };
        },
    };
}

function createLayout(saved = defaults) {
    const data = { textContent: JSON.stringify(saved) };
    const defaultData = { textContent: JSON.stringify(defaults) };
    const tiles = new Map(saved.map((widget) => [
        widget.id,
        makeElement({ 'data-layout-widget': widget.id }),
    ]));
    const grid = {
        getBoundingClientRect() {
            return { width: 1200 };
        },
    };
    const root = makeElement();
    root.dataset.layoutUrl = '/dashboard/layout';
    root.querySelector = (selector) => {
        if (selector === '[data-layout-data]') return data;
        if (selector === '[data-layout-defaults]') return defaultData;
        if (selector === '[data-layout-grid]') return grid;
        const match = selector.match(/^\[data-layout-widget="([^"]+)"\]/);
        return match ? tiles.get(match[1]) : null;
    };
    const component = Object.assign(dashboardLayout(), {
        $el: root,
        $refs: { saveButton: makeElement() },
        $nextTick(callback) {
            callback();
        },
    });
    component.init();
    return { component, tiles };
}

test('widget ordering, visibility and reset operate on an isolated draft', () => {
    const { component } = createLayout();
    component.beginEditing();
    component.setWidget('budgets', { visible: false });
    component.moveWidget('goals', -2);

    assert.deepEqual(component.draft.map((widget) => widget.id), ['goals', 'budgets', 'debts']);
    assert.equal(component.widget('budgets').visible, false);
    assert.equal(component.hasVisibleWidgets, true);
    assert.equal(component.$refs.saveButton.focused, true);

    component.setWidget('debts', { visible: false });
    component.setWidget('goals', { visible: false });
    assert.equal(component.hasVisibleWidgets, false);
    component.reset();
    assert.deepEqual(component.draft, defaults);
});

test('failed saves preserve edits; successful saves send the validated payload', async () => {
    const { component } = createLayout();
    const previousFetch = globalThis.fetch;
    const previousDocument = globalThis.document;
    const previousWindow = globalThis.window;
    const requests = [];
    let shouldFail = true;
    globalThis.document = { querySelector: () => ({ content: 'csrf-token' }) };
    globalThis.window = { location: { reload() {} } };
    globalThis.fetch = async (url, options) => {
        requests.push({ url, ...options, body: JSON.parse(options.body) });
        return {
            ok: !shouldFail,
            json: async () => shouldFail ? { message: 'Connection failed' } : { widgets: JSON.parse(options.body).widgets, message: 'Saved.' },
        };
    };

    try {
        component.editing = true;
        component.setWidget('budgets', { visible: false });
        await component.save();
        assert.equal(component.editing, true);
        assert.match(component.statusMessage, /Connection failed/);

        shouldFail = false;
        await component.save();
        assert.equal(component.editing, false);
        assert.equal(component.statusMessage, 'Saved.');
        assert.equal(requests[1].url, '/dashboard/layout');
        assert.equal(requests[1].method, 'PUT');
        assert.equal(requests[1].headers['X-CSRF-TOKEN'], 'csrf-token');
        assert.equal(requests[1].body.widgets[0].visible, false);
    } finally {
        globalThis.fetch = previousFetch;
        globalThis.document = previousDocument;
        globalThis.window = previousWindow;
    }
});

test('drag reorder and pointer resizing preserve a widget and support cancellation', () => {
    const { component, tiles } = createLayout();
    const previousWindow = globalThis.window;
    globalThis.window = { matchMedia: () => ({ matches: true }) };
    const dataTransfer = { effectAllowed: '', setData() {} };
    const goals = tiles.get('goals');
    try {
        component.handleDragStart({ target: goals, dataTransfer });
        component.handleDrop({ target: tiles.get('budgets'), preventDefault() {} });
        assert.deepEqual(component.draft.map((widget) => widget.id), ['goals', 'budgets', 'debts']);

        const resizeHandle = makeElement({ 'data-layout-resize': '' }, goals);
        component.handlePointerDown({ target: resizeHandle, button: 0, pointerId: 1, clientX: 1200, clientY: 640, preventDefault() {} });
        component.handlePointerMove({ clientX: 400, clientY: 320 });
        assert.equal(component.widget('goals').width, 'small');
        assert.equal(component.widget('goals').height, 'compact');
        component.handlePointerCancel();
        assert.equal(component.widget('goals').width, 'wide');
        assert.equal(component.widget('goals').height, 'auto');
    } finally {
        globalThis.window = previousWindow;
    }
});

test('mobile resizing adjusts height without changing desktop width', () => {
    const { component, tiles } = createLayout();
    const resizeHandle = makeElement({ 'data-layout-resize': '' }, tiles.get('goals'));
    const previousWindow = globalThis.window;
    globalThis.window = { matchMedia: () => ({ matches: false }) };
    try {
        component.handlePointerDown({ target: resizeHandle, button: 0, pointerId: 1, clientX: 1200, clientY: 640, preventDefault() {} });
        component.handlePointerMove({ clientX: 400, clientY: 320 });
        component.handlePointerUp({ pointerId: 1 });
    } finally {
        globalThis.window = previousWindow;
    }
    assert.equal(component.widget('goals').width, 'wide');
    assert.equal(component.widget('goals').height, 'compact');
});
