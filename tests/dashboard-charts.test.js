import assert from 'node:assert/strict';
import test from 'node:test';
import { categoryMixSegments, dashboardPaceGeometry } from '../resources/js/dashboard-charts.js';

test('spending pace geometry includes all actual, planned, and received values', () => {
    const points = [{ spent: 1000, planned: 500 }, { spent: 4000, planned: 1500 }, { spent: null, planned: 2500 }];
    const geometry = dashboardPaceGeometry(points, 2500, 3000, 480);

    assert.equal(geometry.width, 520);
    assert.ok(geometry.maximum > 4000);
    assert.ok(geometry.y(4000) >= geometry.top);
    assert.ok(geometry.y(3000) >= geometry.top);
    assert.ok(geometry.x(2) <= geometry.right);
});

test('category mix allocates exact total shares and retains uncategorised amounts', () => {
    const mix = categoryMixSegments([{ name: 'Food', amount: 7500 }, { name: 'Uncategorised', amount: 2500 }]);

    assert.equal(mix.total, 10000);
    assert.deepEqual(mix.segments.map((segment) => segment.share), [75, 25]);
    assert.deepEqual(mix.segments.map((segment) => segment.offset), [0, 75]);
});

test('empty category mix has zero total and no invalid shares', () => {
    assert.deepEqual(categoryMixSegments([]), { total: 0, segments: [] });
});
