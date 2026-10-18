import assert from 'node:assert/strict';
import test from 'node:test';
import { dashboardPaceGeometry } from '../resources/js/dashboard-charts.js';

test('spending pace geometry includes all actual, planned, and received values', () => {
    const points = [{ spent: 1000, planned: 500 }, { spent: 4000, planned: 1500 }, { spent: null, planned: 2500 }];
    const geometry = dashboardPaceGeometry(points, 2500, 3000, 480);

    assert.equal(geometry.width, 480);
    assert.ok(geometry.maximum > 4000);
    assert.ok(geometry.y(4000) >= geometry.top);
    assert.ok(geometry.y(3000) >= geometry.top);
    assert.equal(geometry.x(0), geometry.left);
    assert.equal(geometry.x(2), geometry.right);
    assert.ok(geometry.left > 0);
    assert.equal(geometry.right, geometry.width);
});
