import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';
import vm from 'node:vm';

const layout = readFileSync(new URL('../resources/views/layouts/app.blade.php', import.meta.url), 'utf8');
const restore = layout.match(/<script data-sidebar-preference>([\s\S]*?)<\/script>/)[1];
const source = readFileSync(new URL('../resources/js/app.js', import.meta.url), 'utf8').replace(/^import .*;\r?\n/gm, '');

function sidebarPage(storage) {
    const callbacks = {};
    const changes = [];
    const drawer = { checked: false, addEventListener: (event, callback) => changes.push(callback), dispatchEvent: () => changes.forEach((callback) => callback()) };
    const toggle = { attributes: {}, setAttribute(name, value) { this.attributes[name] = value; }, addEventListener: (event, callback) => { callbacks[event] = callback; } };
    const document = { getElementById: (id) => id === 'my-drawer-4' ? drawer : null, querySelector: () => toggle, addEventListener: (event, callback) => { callbacks[event] = callback; } };
    const context = vm.createContext({ document, localStorage: storage, Event });
    vm.runInContext(restore, context);
    const restoredBeforeInitialization = drawer.checked;
    vm.runInContext(source, context);
    callbacks.DOMContentLoaded();
    return { drawer, toggle, restoredBeforeInitialization, click: callbacks.click };
}

function preferenceStore() {
    const values = new Map();
    return { getItem: (key) => values.get(key) ?? null, setItem: (key, value) => values.set(key, value) };
}

test('sidebar expansion and collapse survive navigation and restore before initialization', () => {
    const storage = preferenceStore();
    const first = sidebarPage(storage);
    assert.equal(first.drawer.checked, false);
    first.click();
    assert.equal(first.toggle.attributes['aria-expanded'], 'true');
    const next = sidebarPage(storage);
    assert.equal(next.restoredBeforeInitialization, true);
    assert.equal(next.toggle.attributes['aria-expanded'], 'true');
    next.click();
    const refreshed = sidebarPage(storage);
    assert.equal(refreshed.restoredBeforeInitialization, false);
    assert.equal(refreshed.toggle.attributes['aria-expanded'], 'false');
});

test('closing the sidebar through its overlay also persists the collapsed preference', () => {
    const storage = preferenceStore();
    const page = sidebarPage(storage);
    page.click();
    page.drawer.checked = false;
    page.drawer.dispatchEvent(new Event('change'));
    assert.equal(sidebarPage(storage).drawer.checked, false);
});

test('unavailable browser storage does not prevent the sidebar from toggling', () => {
    const page = sidebarPage({ getItem() { throw new Error('Storage unavailable'); }, setItem() { throw new Error('Storage unavailable'); } });
    page.click();
    assert.equal(page.drawer.checked, true);
    assert.equal(page.toggle.attributes['aria-expanded'], 'true');
    page.click();
    assert.equal(page.drawer.checked, false);
});
