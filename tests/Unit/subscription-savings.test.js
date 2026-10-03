import test from 'node:test';
import assert from 'node:assert/strict';
import { calculateSavings } from '../../resources/js/subscription-analytics.js';

test('combines selected yearly commitments before calculating monthly savings', () => {
    const savings = calculateSavings(412000, 240000, 10000);
    assert.equal(savings.annualSavingsCents, 240000);
    assert.equal(savings.monthlySavingsCents, 20000);
    assert.equal(savings.remainingMonthlyCents, 14333);
    assert.equal(savings.targetGapCents, 4333);
});

test('monthly reduction and remaining costs reconcile after rounding', () => {
    const savings = calculateSavings(10, 5);
    assert.equal(savings.monthlySavingsCents + savings.remainingMonthlyCents, Math.round(10 / 12));
    assert.equal(savings.targetGapCents, null);
});

test('selecting every subscription reduces future commitment to zero', () => {
    const savings = calculateSavings(412000, 412000, 50000);
    assert.equal(savings.remainingMonthlyCents, 0);
    assert.equal(savings.remainingPercent, 0);
    assert.equal(savings.monthlySavingsCents, 34333);
    assert.equal(savings.targetGapCents, -50000);
});

test('no selections retain the original commitment and match an equal target', () => {
    const savings = calculateSavings(120000, 0, 10000);
    assert.equal(savings.annualSavingsCents, 0);
    assert.equal(savings.remainingMonthlyCents, 10000);
    assert.equal(savings.remainingPercent, 100);
    assert.equal(savings.targetGapCents, 0);
});

test('empty and excessive selections cannot produce negative forecasts or invalid percentages', () => {
    assert.equal(calculateSavings(0, 0).remainingPercent, 0);
    assert.equal(calculateSavings(12000, 24000).remainingMonthlyCents, 0);
    assert.equal(calculateSavings(12000, -1).annualSavingsCents, 0);
});
