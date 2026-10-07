export function calculateSavings(totalAnnualCents, selectedAnnualCents, targetCents = null) {
    const annualSavingsCents = Math.min(totalAnnualCents, Math.max(0, selectedAnnualCents));
    const remainingAnnualCents = totalAnnualCents - annualSavingsCents;
    const remainingMonthlyCents = Math.round(remainingAnnualCents / 12);

    return {
        annualSavingsCents,
        monthlySavingsCents: Math.round(totalAnnualCents / 12) - remainingMonthlyCents,
        remainingMonthlyCents,
        remainingPercent: totalAnnualCents > 0 ? remainingAnnualCents / totalAnnualCents * 100 : 0,
        targetGapCents: targetCents === null ? null : remainingMonthlyCents - targetCents,
    };
}

function initializeSavingsCalculator() {
    const calculator = document.querySelector('[data-subscription-savings]');
    if (!calculator) return;

    const totalAnnualCents = Number(calculator.dataset.totalAnnualCents);
    const subscriptions = [...calculator.querySelectorAll('[data-savings-subscription]')];
    const target = calculator.querySelector('[data-savings-target]');
    const currency = new Intl.NumberFormat('en-ZA', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    const currencyPrefix = document.body.dataset.currencyPrefix || 'R ';
    const formatMoney = (cents) => `${currencyPrefix}${currency.format(cents / 100)}`;

    function updateSavings() {
        const selectedAnnualCents = subscriptions.reduce((total, subscription) => total + (subscription.checked ? Number(subscription.dataset.annualCents) : 0), 0);
        const hasTarget = target.value.trim() !== '';
        const validTarget = hasTarget && target.checkValidity() && Number.isFinite(target.valueAsNumber);
        const targetCents = validTarget ? Math.round(target.valueAsNumber * 100) : null;
        const savings = calculateSavings(totalAnnualCents, selectedAnnualCents, targetCents);

        calculator.querySelector('[data-savings-monthly]').textContent = formatMoney(savings.monthlySavingsCents);
        calculator.querySelector('[data-savings-annual]').textContent = formatMoney(savings.annualSavingsCents);
        calculator.querySelector('[data-savings-remaining]').textContent = formatMoney(savings.remainingMonthlyCents);
        calculator.querySelector('[data-savings-bar]').style.width = `${savings.remainingPercent}%`;

        const targetResult = calculator.querySelector('[data-savings-target-result]');
        if (!hasTarget) {
            targetResult.textContent = 'Enter a target to compare it with your remaining monthly equivalent.';
        } else if (!validTarget) {
            targetResult.textContent = 'Enter a non-negative target with up to two decimal places.';
        } else if (savings.targetGapCents > 0) {
            targetResult.textContent = `${formatMoney(savings.targetGapCents)} above your monthly target after selected changes.`;
        } else if (savings.targetGapCents < 0) {
            targetResult.textContent = `${formatMoney(-savings.targetGapCents)} below your monthly target after selected changes.`;
        } else {
            targetResult.textContent = 'Your remaining monthly equivalent matches your target.';
        }
    }

    subscriptions.forEach((subscription) => subscription.addEventListener('change', updateSavings));
    target.addEventListener('input', updateSavings);
    calculator.querySelector('[data-savings-reset]').addEventListener('click', () => {
        subscriptions.forEach((subscription) => { subscription.checked = false; });
        target.value = '';
        updateSavings();
    });
    updateSavings();
}

if (typeof document !== 'undefined') {
    document.addEventListener('DOMContentLoaded', initializeSavingsCalculator);
}
