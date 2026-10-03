import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';
import vm from 'node:vm';

const source = readFileSync(new URL('../resources/js/confirm-actions.js', import.meta.url), 'utf8');

function confirmationHarness() {
    const handlers = new Map();
    const element = () => ({ textContent: '', disabled: false, handlers: {}, addEventListener(name, handler) { this.handlers[name] = handler; } });
    const controls = new Map(['accept', 'cancel', 'dialog-title', 'dialog-message'].map((name) => [`[data-confirm-${name}]`, element()]));
    const dialog = {
        open: false,
        handlers: {},
        querySelector(selector) { return controls.get(selector); },
        addEventListener(name, handler) { this.handlers[name] = handler; },
        showModal() { this.open = true; },
        close() { this.open = false; this.handlers.close?.(); },
    };
    const document = {
        getElementById() { return dialog; },
        addEventListener(name, handler) { handlers.set(name, handler); },
    };
    class Form {
        constructor(dataset = { confirm: 'Delete this record?', confirmLabel: 'Delete' }) {
            this.dataset = dataset;
            this.isConnected = true;
            this.requests = 0;
        }
        hasAttribute(name) { return name === 'data-confirm' && this.dataset.confirm !== undefined; }
        closest() { return null; }
        requestSubmit(submitter) {
            this.lastSubmitter = submitter;
            const event = { target: this, submitter, prevented: false, stopped: false, preventDefault() { this.prevented = true; }, stopImmediatePropagation() { this.stopped = true; } };
            handlers.get('submit')(event);
            if (!event.prevented) this.requests++;
            return event;
        }
    }
    vm.runInNewContext(source, { document, HTMLFormElement: Form, WeakSet });
    handlers.get('DOMContentLoaded')();
    return { Form, dialog, accept: controls.get('[data-confirm-accept]'), cancel: controls.get('[data-confirm-cancel]'), message: controls.get('[data-confirm-dialog-message]') };
}

test('destructive submissions wait for explicit confirmation and preserve the submitter', () => {
    const { Form, dialog, accept } = confirmationHarness();
    const form = new Form();
    const submitter = { isConnected: true };
    const event = form.requestSubmit(submitter);
    assert.equal(event.prevented, true);
    assert.equal(event.stopped, true);
    assert.equal(form.requests, 0);
    assert.equal(dialog.open, true);
    accept.handlers.click();
    assert.equal(form.requests, 1);
    assert.equal(form.lastSubmitter, submitter);
    assert.equal(dialog.open, false);
    form.requestSubmit(submitter);
    assert.equal(form.requests, 1);
    assert.equal(dialog.open, true);
});

test('cancel and Escape close without sending a destructive request', () => {
    for (const escape of [false, true]) {
        const { Form, dialog, cancel } = confirmationHarness();
        const form = new Form();
        form.requestSubmit();
        if (escape) dialog.close(); else cancel.handlers.click();
        assert.equal(form.requests, 0);
        assert.equal(dialog.open, false);
    }
});

test('a replaced budget form cannot be deleted through a stale dialog', () => {
    const { Form, accept, message } = confirmationHarness();
    const form = new Form();
    form.requestSubmit();
    form.isConnected = false;
    accept.handlers.click();
    assert.equal(form.requests, 0);
    assert.equal(accept.disabled, true);
    assert.match(message.textContent, /changed/);
});

test('repeated confirm clicks never submit the same destructive request twice', () => {
    const { Form, accept } = confirmationHarness();
    const form = new Form();
    form.requestSubmit();
    accept.handlers.click();
    accept.handlers.click();
    assert.equal(form.requests, 1);
});

test('ordinary forms do not open a confirmation dialog', () => {
    const { Form, dialog } = confirmationHarness();
    const form = new Form({});
    form.requestSubmit();
    assert.equal(form.requests, 1);
    assert.equal(dialog.open, false);
});
