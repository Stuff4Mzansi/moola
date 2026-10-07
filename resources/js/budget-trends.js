export function selectTrendPeriods(periods, range) {
    const counts = { '3': 3, '6': 6, '12': 12 };
    return range === 'all' ? [...periods] : periods.slice(-(counts[range] ?? 6));
}

export function summarizeTrendPeriods(periods, referenceKey = 'planned') {
    const completed = periods.filter((period) => period.complete);
    const sum = (items, key) => items.reduce((total, period) => total + period[key], 0);
    const planned = sum(periods, referenceKey);
    const spent = sum(periods, 'spent');
    const completedDifference = sum(completed, referenceKey) - sum(completed, 'spent');
    return { planned, spent, completed, completedDifference, difference: completed.length ? completedDifference : planned - spent, overCount: completed.filter((period) => period.spent > period[referenceKey]).length };
}

export function trendGeometry(periods, viewportWidth = 680, referenceKey = 'planned') {
    const width = Math.max(680, viewportWidth, periods.length * 84 + 100);
    const maximum = Math.max(100, ...periods.flatMap((period) => [period[referenceKey], period.spent])) * 1.15;
    const left = 76, right = width - 24, top = 20, bottom = 136;
    const step = (right - left) / Math.max(1, periods.length);
    const y = (amount) => bottom - amount / maximum * (bottom - top);
    return { width, maximum, left, right, top, bottom, step, y, points: periods.map((period, index) => ({ period, x: left + step * (index + 0.5), plannedY: y(period[referenceKey]), spentY: y(period.spent) })) };
}

function initializeBudgetTrends() {
    const section = document.querySelector('[data-budget-trends]');
    if (!section) return;
    const budgets = JSON.parse(section.querySelector('[data-trend-data]').textContent);
    if (!budgets.length) return;
    const find = (name) => section.querySelector(`[data-trend-${name}]`);
    const selector = find('budget');
    const range = find('range');
    const currencyPrefix = document.body.dataset.currencyPrefix || 'R ';
    const currencySymbol = document.body.dataset.currencySymbol || 'R';
    const money = (cents) => `${currencyPrefix}${new Intl.NumberFormat('en-ZA', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(cents / 100)}`;
    const date = (day) => new Intl.DateTimeFormat('en-ZA', { day: '2-digit', month: 'short', year: 'numeric', timeZone: 'Africa/Johannesburg' }).format(new Date(`${day}T12:00:00+02:00`));
    const shortDate = (day) => new Intl.DateTimeFormat('en-ZA', { day: 'numeric', month: 'short', timeZone: 'Africa/Johannesburg' }).format(new Date(`${day}T12:00:00+02:00`));
    let mode = 'line';
    let comparison = 'budget';
    const initialBudget = budgets.find((budget) => budget.periods.some((period) => !period.complete)) ?? budgets.find((budget) => budget.periods.length) ?? budgets[0];
    selector.value = String(initialBudget.id);
    find('controls').hidden = false;

    function svgElement(name, attributes = {}, content = null) {
        const element = document.createElementNS('http://www.w3.org/2000/svg', name);
        Object.entries(attributes).forEach(([key, value]) => element.setAttribute(key, String(value)));
        if (content !== null) element.textContent = content;
        return element;
    }

    function textElement(name, content, classes = '') {
        const element = document.createElement(name);
        element.textContent = content;
        element.className = classes;
        return element;
    }

    function referenceKey() {
        return comparison === 'income' ? 'received' : 'planned';
    }

    function referenceLabel() {
        return comparison === 'income' ? 'Income received' : 'Budgeted';
    }

    function status(period) {
        const difference = period[referenceKey()] - period.spent;
        if (difference < 0) return `${money(-difference)} ${comparison === 'income' ? 'spending above income' : 'over budget'}${period.complete ? '' : ' so far'}`;
        if (!period.complete) return `${money(difference)} ${comparison === 'income' ? 'income remaining' : 'remaining'} (in progress)`;
        return difference === 0 ? (comparison === 'income' ? 'Income matched spending' : 'Exactly on budget') : `${money(difference)} ${comparison === 'income' ? 'income remaining' : 'under budget'}`;
    }

    function renderChart(periods, budget) {
        const key = referenceKey();
        const geometry = trendGeometry(periods, find('chart').clientWidth || 680, key);
        const { width, maximum, left, right, top, bottom, step, y, points } = geometry;
        const svg = svgElement('svg', { viewBox: `0 0 ${width} 190`, width, height: 190, class: 'block max-w-none', style: `width: ${width}px; height: 190px`, role: 'img', 'aria-labelledby': 'budget-trend-svg-title budget-trend-svg-desc' });
        svg.append(svgElement('title', { id: 'budget-trend-svg-title' }, `${budget.name}: ${referenceLabel().toLowerCase()} versus spent`));
        svg.append(svgElement('desc', { id: 'budget-trend-svg-desc' }, `${mode === 'line' ? 'Line' : 'Bar'} chart, oldest to newest. ${periods.length} budget periods. Red spending marks exceed ${referenceLabel().toLowerCase()}. Open a point or bar to review its period. Exact amounts are in the table below.`));
        for (let tick = 0; tick <= 4; tick++) {
            const value = maximum * tick / 4;
            const position = y(value);
            svg.append(svgElement('line', { x1: left, x2: right, y1: position, y2: position, class: 'stroke-base-content', opacity: 0.1 }));
            const label = new Intl.NumberFormat('en-ZA', { notation: 'compact', maximumFractionDigits: 1 }).format(value / 100);
            svg.append(svgElement('text', { x: left - 12, y: position + 4, 'text-anchor': 'end', 'font-size': 11, class: 'fill-base-content', opacity: 0.6 }, label));
        }
        svg.append(svgElement('text', { x: left - 12, y: top - 12, 'text-anchor': 'end', 'font-size': 10, class: 'fill-base-content', opacity: 0.6 }, currencySymbol));
        if (mode === 'line' && points.length > 1) {
            svg.append(svgElement('path', { d: points.map((point, index) => `${index ? 'L' : 'M'} ${point.x} ${point.plannedY}`).join(' '), fill: 'none', class: 'stroke-primary', 'stroke-width': 2.5 }));
            points.slice(1).forEach((point, index) => {
                const previous = points[index];
                svg.append(svgElement('line', { x1: previous.x, y1: previous.spentY, x2: point.x, y2: point.spentY, class: 'stroke-info', 'stroke-width': 2.5, 'stroke-dasharray': point.period.complete ? 'none' : '6 4' }));
            });
        }
        points.forEach((point) => {
            const { period, x, plannedY, spentY } = point;
            const description = `${period.label}, ${date(period.start)} to ${date(period.end)}. ${referenceLabel()} ${money(period[key])}, spent ${money(period.spent)}. ${status(period)}. Open budget.`;
            const anchor = svgElement('a', { href: period.url, tabindex: 0, 'aria-label': description });
            anchor.append(svgElement('title', {}, description));
            if (mode === 'bar') {
                const barWidth = Math.min(24, step * 0.24);
                anchor.append(svgElement('rect', { x: x - barWidth - 3, y: plannedY, width: barWidth, height: Math.max(2, bottom - plannedY), rx: 4, class: 'fill-primary', opacity: 0.7 }));
                anchor.append(svgElement('rect', { x: x + 3, y: spentY, width: barWidth, height: Math.max(2, bottom - spentY), rx: 4, class: period.spent > period[key] ? 'fill-error' : 'fill-info', opacity: period.complete ? 1 : 0.55 }));
            } else {
                anchor.append(svgElement('circle', { cx: x, cy: plannedY, r: 4, class: 'fill-primary stroke-base-100', 'stroke-width': 2 }));
                anchor.append(svgElement('circle', { cx: x, cy: spentY, r: 5, class: period.spent > period[key] ? 'fill-error stroke-base-100' : 'fill-info stroke-base-100', 'stroke-width': 2 }));
                anchor.append(svgElement('circle', { cx: x, cy: spentY, r: 13, fill: 'transparent' }));
            }
            anchor.append(svgElement('text', { x, y: bottom + 16, 'text-anchor': 'middle', 'font-size': 11, class: 'fill-base-content', opacity: 0.75 }, shortDate(period.start)));
            anchor.append(svgElement('text', { x, y: bottom + 30, 'text-anchor': 'middle', 'font-size': 10, class: 'fill-base-content', opacity: 0.55 }, period.start.slice(0, 4)));
            if (!period.complete) anchor.append(svgElement('text', { x, y: bottom + 44, 'text-anchor': 'middle', 'font-size': 10, class: 'fill-info' }, 'In progress'));
            svg.append(anchor);
        });
        find('chart').replaceChildren(svg);
    }

    function render() {
        const budget = budgets.find((candidate) => String(candidate.id) === selector.value) ?? budgets[0];
        const periods = selectTrendPeriods(budget.periods, range.value);
        find('content').hidden = periods.length === 0;
        find('empty').hidden = periods.length > 0;
        if (!periods.length) return;
        const key = referenceKey();
        const summary = summarizeTrendPeriods(periods, key);
        find('reference-label').textContent = referenceLabel();
        find('reference-legend').textContent = referenceLabel();
        find('over-legend').textContent = comparison === 'income' ? 'Spending above income' : 'Over budget';
        find('table-reference').textContent = referenceLabel();
        find('difference-heading').textContent = comparison === 'income' ? 'Balance' : 'Difference';
        find('caption').textContent = `${referenceLabel()} and recorded spending by budget period`;
        find('planned').textContent = money(summary.planned);
        find('spent').textContent = money(summary.spent);
        find('variance-label').textContent = comparison === 'income'
            ? (summary.completed.length ? 'Received minus spending - completed periods' : 'Received minus spending so far')
            : (summary.completed.length ? (summary.difference < 0 ? 'Net overspending - completed periods' : 'Net underspending - completed periods') : (summary.difference < 0 ? 'Over budget so far' : 'Room remaining - in progress'));
        if (comparison === 'budget' && summary.completed.length && summary.difference === 0) find('variance-label').textContent = 'On budget - completed periods';
        find('variance').textContent = money(Math.abs(summary.difference));
        find('variance').classList.toggle('text-error', summary.difference < 0);
        find('variance-detail').textContent = summary.completed.length ? `${summary.overCount} of ${summary.completed.length} completed periods spent above ${comparison === 'income' ? 'received income' : 'budget'}. Current periods excluded here.` : 'Recorded spending so far; this is not a final result.';
        find('window').textContent = `${budget.name} | ${date(periods[0].start)} to ${date(periods.at(-1).end)} | ${periods.length} ${periods.length === 1 ? 'period' : 'periods'}`;
        renderChart(periods, budget);
        const rows = periods.map((period) => {
            const row = document.createElement('tr');
            const nameCell = document.createElement('td');
            const link = textElement('a', period.label, 'link link-primary font-medium');
            link.href = period.url;
            nameCell.append(link);
            row.append(nameCell, textElement('td', `${date(period.start)} - ${date(period.end)}`, 'whitespace-nowrap'), textElement('td', money(period[key]), 'whitespace-nowrap'), textElement('td', money(period.spent), 'whitespace-nowrap'), textElement('td', status(period), period.spent > period[key] ? 'text-error' : ''));
            return row;
        });
        find('table').replaceChildren(...rows);
        const latest = periods.at(-1);
        const insight = document.createElement('div');
        const heading = comparison === 'income' ? `Latest period: ${status(latest)}.` : (latest.planned === 0 ? 'This period has no category allocations.' : `Latest period: ${status(latest)}.`);
        insight.append(textElement('p', heading, 'font-semibold'));
        const detail = comparison === 'income'
            ? (!latest.complete ? 'This period is still running; received income may be incomplete.' : 'Received income and recorded spending only; missing records can change this balance.')
            : (latest.planned === 0 ? 'Set category amounts to give spending a useful benchmark.' : (!latest.complete ? 'This period is still running. Check unpaid scheduled expenses before deciding how much is available.' : 'Compare the original dates before changing your next plan; period lengths can differ, and underspending reflects recorded expenses only.'));
        insight.append(textElement('p', detail, 'mt-1 text-xs opacity-65'));
        const link = textElement('a', 'Review this period', 'link link-primary mt-1 inline-block text-xs font-semibold');
        link.href = latest.url;
        insight.append(link);
        find('insight').replaceChildren(insight);
    }

    selector.addEventListener('change', render);
    range.addEventListener('change', render);
    section.querySelectorAll('[data-trend-comparison]').forEach((button) => {
        button.addEventListener('click', () => {
            comparison = button.dataset.trendComparison;
            section.querySelectorAll('[data-trend-comparison]').forEach((control) => {
                const selected = control === button;
                control.classList.toggle('btn-primary', selected);
                control.classList.toggle('btn-ghost', !selected);
                control.setAttribute('aria-pressed', String(selected));
            });
            render();
        });
    });
    section.querySelectorAll('[data-trend-mode]').forEach((button) => {
        button.addEventListener('click', () => {
            mode = button.dataset.trendMode;
            section.querySelectorAll('[data-trend-mode]').forEach((control) => {
                const selected = control === button;
                control.classList.toggle('btn-primary', selected);
                control.classList.toggle('btn-ghost', !selected);
                control.setAttribute('aria-pressed', String(selected));
            });
            render();
        });
    });
    render();
    if (typeof ResizeObserver !== 'undefined') {
        const observer = new ResizeObserver(() => {
            const budget = budgets.find((candidate) => String(candidate.id) === selector.value) ?? budgets[0];
            const periods = selectTrendPeriods(budget.periods, range.value);
            if (periods.length) renderChart(periods, budget);
        });
        observer.observe(find('chart'));
    }
}

if (typeof document !== 'undefined') document.addEventListener('DOMContentLoaded', initializeBudgetTrends);
