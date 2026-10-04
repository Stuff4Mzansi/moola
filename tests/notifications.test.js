import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';
import vm from 'node:vm';

const source = readFileSync(new URL('../resources/js/notifications.js', import.meta.url), 'utf8');

function notificationHarness(fetcher) {
    const events = {};
    const panel = { dataset: { notificationFeed: '/notifications/feed' }, innerHTML: 'Existing notifications', open: false, focused: false, querySelector() { return this.open ? {} : null; }, contains() { return this.focused; } };
    const document = { hidden: false, activeElement: {}, querySelector: () => panel, addEventListener: (name, callback) => { events[name] = callback; } };
    let refresh;
    vm.runInNewContext(source, { document, window: { addEventListener: (name, callback) => { events[name] = callback; } }, fetch: fetcher, AbortSignal, setInterval: (callback) => { refresh = callback; } });
    events.DOMContentLoaded();
    return { panel, document, refresh, events };
}

test('notification polling refreshes the bell from the authenticated feed', async () => {
    let calls = 0;
    const harness = notificationHarness(async (url, options) => {
        calls++;
        assert.equal(url, '/notifications/feed');
        assert.equal(options.headers.Accept, 'application/json');
        return { ok: true, json: async () => ({ html: 'One unread notification' }) };
    });
    await harness.refresh();
    assert.equal(calls, 1);
    assert.equal(harness.panel.innerHTML, 'One unread notification');
});

test('polling preserves the open menu and keyboard focus and pauses hidden pages', async () => {
    let calls = 0;
    const harness = notificationHarness(async () => { calls++; return { ok: true, json: async () => ({ html: 'Updated' }) }; });
    harness.panel.open = true;
    await harness.refresh();
    harness.panel.open = false;
    harness.panel.focused = true;
    await harness.refresh();
    harness.panel.focused = false;
    harness.document.hidden = true;
    await harness.refresh();
    assert.equal(calls, 0);
    assert.equal(harness.panel.innerHTML, 'Existing notifications');
});

test('a response arriving after the menu opens does not replace its actions', async () => {
    let deliver;
    const harness = notificationHarness(() => new Promise((resolve) => { deliver = resolve; }));
    const pending = harness.refresh();
    harness.panel.open = true;
    deliver({ ok: true, json: async () => ({ html: 'Replacement' }) });
    await pending;
    assert.equal(harness.panel.innerHTML, 'Existing notifications');
});

test('network interruptions retain notifications and allow a later refresh', async () => {
    let calls = 0;
    const harness = notificationHarness(async () => {
        if (++calls === 1) throw new Error('Offline');
        return { ok: true, json: async () => ({ html: 'Recovered' }) };
    });
    await harness.refresh();
    assert.equal(harness.panel.innerHTML, 'Existing notifications');
    await harness.events.focus();
    assert.equal(harness.panel.innerHTML, 'Recovered');
});
