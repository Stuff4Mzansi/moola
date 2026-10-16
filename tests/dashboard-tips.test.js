import assert from 'node:assert/strict';
import test from 'node:test';
import { dashboardTips } from '../resources/js/dashboard-tips.js';

function createComponent() {
    const root = {
        child: {},
        hovered: false,
        contains(element) {
            return element === this.child;
        },
        matches(selector) {
            return selector === ':hover' && this.hovered;
        },
    };

    return Object.assign(dashboardTips(), { $el: root });
}

test('budget tips cycle forward and backward without leaving the list', () => {
    const component = createComponent();

    assert.equal(component.currentTip.title, 'Adjust dates when your pay cycle changes');
    assert.equal(component.tipNumber, '01');
    component.previous();
    assert.equal(component.currentTip.title, 'Tune budget settings to fit your routine');
    assert.equal(component.tipNumber, '05');
    component.next();
    assert.equal(component.currentTip.title, 'Adjust dates when your pay cycle changes');
});

test('tips separately cover period dates, new periods, plan copying, sharing, and settings', () => {
    const component = createComponent();

    assert.deepEqual(component.tips.map((tip) => tip.title), [
        'Adjust dates when your pay cycle changes',
        'Create your next budget period',
        'Carry your plan forward, not old transactions',
        'Share a budget with your household',
        'Tune budget settings to fit your routine',
    ]);
});

test('automatic tips pause on interaction and can be resumed', () => {
    const component = createComponent();
    const previousWindow = globalThis.window;
    const previousDocument = globalThis.document;
    let nextTimer = 0;
    const timers = new Map();
    globalThis.window = {
        matchMedia: () => ({ matches: false }),
        setInterval(callback, delay) {
            const id = ++nextTimer;
            timers.set(id, { callback, delay });
            return id;
        },
        clearInterval(id) {
            timers.delete(id);
        },
    };
    globalThis.document = { activeElement: null };

    try {
        component.init();
        assert.equal(timers.get(component.timer).delay, 9000);
        timers.get(component.timer).callback();
        assert.equal(component.activeIndex, 1);

        component.pauseForInteraction();
        assert.equal(component.timer, null);
        component.resumeAfterInteraction();
        assert.notEqual(component.timer, null);
        component.togglePause();
        assert.equal(component.isPaused, true);
        assert.equal(component.timer, null);
        component.togglePause();
        assert.equal(component.isPaused, false);
        assert.notEqual(component.timer, null);
        component.destroy();
        assert.equal(component.timer, null);
    } finally {
        globalThis.window = previousWindow;
        globalThis.document = previousDocument;
    }
});

test('reduced-motion preference disables automatic rotation until requested', () => {
    const component = createComponent();
    const previousWindow = globalThis.window;
    const timers = [];
    globalThis.window = {
        matchMedia: () => ({ matches: true }),
        setInterval(callback) {
            timers.push(callback);
            return timers.length;
        },
        clearInterval() {},
    };

    try {
        component.init();
        assert.equal(component.isPaused, true);
        assert.equal(component.timer, null);
        assert.equal(timers.length, 0);
        component.togglePause();
        assert.notEqual(component.timer, null);
        assert.equal(timers.length, 1);
        component.destroy();
    } finally {
        globalThis.window = previousWindow;
    }
});
