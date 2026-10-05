import { selectTrendPeriods, trendGeometry } from './budget-trends.js';

export function summarizeCategoryTrends(periods) {
    const completed = periods.filter((period) => period.complete);
    const categories = new Map();
    completed.forEach((period) => (period.categories ?? []).forEach((category) => categories.set(category.key, category.name)));
    return [...categories].map(([key, name]) => {
        const points = completed.map((period) => {
            const category = (period.categories ?? []).find((item) => item.key === key);
            return { period, category: category ?? null, daily: category ? category.spent / period.days : null, share: category && period.received > 0 ? category.spent / period.received * 100 : null };
        });
        const latest = points.at(-1);
        const previous = points.at(-2);
        const allocated = points.filter((point) => point.category && point.category.planned > 0);
        const overCount = allocated.filter((point) => point.category.spent > point.category.planned).length;
        const recent = points.slice(-3);
        return {
            key, name, points, latest, previous,
            change: latest?.daily !== null && previous?.daily != null ? latest.daily - previous.daily : null,
            shareChange: latest?.share !== null && previous?.share != null ? latest.share - previous.share : null,
            overCount, allocatedCount: allocated.length,
            repeated: recent.length === 3 && recent.every((point) => point.category && point.category.planned > 0 && point.category.spent > point.category.planned),
        };
    }).sort((left, right) => Math.abs(right.change ?? 0) - Math.abs(left.change ?? 0) || left.name.localeCompare(right.name));
}

function initializeCategoryTrends() {
    const section = document.querySelector('[data-category-trends]');
    if (!section) return;
    const find = (name) => section.querySelector(`[data-category-${name}]`);
    const budgets = JSON.parse(find('data').textContent);
    if (!budgets.length) return;
    const budgetSelect = find('budget');
    const range = find('range');
    const categorySelect = find('select');
    budgetSelect.value = String((budgets.find((budget) => budget.periods.some((period) => period.complete)) ?? budgets[0]).id);
    find('controls').hidden = false;
    const money = (cents) => `ZAR ${new Intl.NumberFormat('en-ZA', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(cents / 100)}`;
    const node = (tag, content, classes = '') => { const element = document.createElement(tag); element.textContent = content; element.className = classes; return element; };
    const svgNode = (tag, attributes, content = null) => { const element = document.createElementNS('http://www.w3.org/2000/svg', tag); Object.entries(attributes).forEach(([key, value]) => element.setAttribute(key, value)); if (content !== null) element.textContent = content; return element; };
    let rows = [];

    function renderChart() {
        const row = rows.find((item) => item.key === categorySelect.value);
        if (!row) { find('chart').replaceChildren(); return; }
        const periods = row.points.map((point) => ({ planned: point.category ? point.category.planned / point.period.days : 0, spent: point.daily ?? 0 }));
        const geometry = trendGeometry(periods, find('chart').clientWidth || 680);
        const { width, left, right, top, bottom, points, maximum } = geometry;
        const svg = svgNode('svg', { viewBox: `0 0 ${width} 205`, width, height: 205, class: 'block max-w-none', role: 'img', 'aria-labelledby': 'category-chart-title category-chart-desc' });
        svg.append(svgNode('title', { id: 'category-chart-title' }, `${row.name}: recorded and allocated spending per day`));
        svg.append(svgNode('desc', { id: 'category-chart-desc' }, 'Completed periods, oldest to newest. Missing categories leave gaps. Each point opens its budget period. Exact daily averages and original totals are available on hover.'));
        for (let tick = 0; tick <= 4; tick++) {
            const value = maximum * tick / 4;
            const y = geometry.y(value);
            svg.append(svgNode('line', { x1: left, x2: right, y1: y, y2: y, class: 'stroke-base-content', opacity: 0.1 }));
            svg.append(svgNode('text', { x: left - 10, y: y + 4, 'text-anchor': 'end', 'font-size': 10, class: 'fill-base-content', opacity: 0.65 }, new Intl.NumberFormat('en-ZA', { notation: 'compact', maximumFractionDigits: 1 }).format(value / 100)));
        }
        svg.append(svgNode('text', { x: left - 10, y: top - 10, 'text-anchor': 'end', 'font-size': 10, class: 'fill-base-content' }, 'ZAR / day'));
        points.forEach((point, index) => {
            const entry = row.points[index];
            const previous = row.points[index - 1];
            if (entry.category && previous?.category) {
                for (const [coordinate, colour] of [['spentY', 'stroke-primary'], ['plannedY', 'stroke-base-content']]) {
                    svg.append(svgNode('line', { x1: points[index - 1].x, y1: points[index - 1][coordinate], x2: point.x, y2: point[coordinate], class: colour, 'stroke-width': 2.5, opacity: coordinate === 'plannedY' ? 0.4 : 1 }));
                }
            }
            svg.append(svgNode('text', { x: point.x, y: bottom + 20, 'text-anchor': 'middle', 'font-size': 10, class: 'fill-base-content' }, entry.period.start));
            if (!entry.category) { svg.append(svgNode('text', { x: point.x, y: bottom + 36, 'text-anchor': 'middle', 'font-size': 10, class: 'fill-base-content', opacity: 0.6 }, 'No category')); return; }
            const description = `${row.name}, ${entry.period.label}: ${money(entry.daily)} recorded per day, ${money(entry.category.planned / entry.period.days)} allocated per day. ${money(entry.category.spent)} recorded over ${entry.period.days} days. ${entry.share === null ? 'No received income for a share.' : `${entry.share.toFixed(1)}% of received income.`}`;
            const anchor = svgNode('a', { href: entry.period.url, tabindex: 0, 'aria-label': description });
            anchor.append(svgNode('title', {}, description), svgNode('circle', { cx: point.x, cy: point.plannedY, r: 4, class: 'fill-base-content', opacity: 0.4 }), svgNode('circle', { cx: point.x, cy: point.spentY, r: 5, class: entry.category.planned > 0 && entry.category.spent > entry.category.planned ? 'fill-error' : 'fill-primary' }));
            svg.append(anchor);
        });
        find('chart').replaceChildren(svg);
    }

    function render() {
        const budget = budgets.find((item) => String(item.id) === budgetSelect.value) ?? budgets[0];
        const periods = selectTrendPeriods(budget.periods.filter((period) => period.complete), range.value);
        rows = summarizeCategoryTrends(periods);
        find('content').hidden = !rows.length;
        find('empty').hidden = rows.length > 0;
        if (!rows.length) return;
        find('window').textContent = `${budget.name} | ${periods[0].start} to ${periods.at(-1).end} | ${periods.length} completed periods`;
        const biggest = rows.find((row) => row.change !== null && Math.abs(row.change) >= 0.5);
        const repeated = rows.filter((row) => row.repeated);
        const growing = [...rows].filter((row) => row.shareChange !== null && row.shareChange >= 0.05).sort((a, b) => b.shareChange - a.shareChange)[0];
        const cards = [
            ['Largest daily spending change', biggest ? `${biggest.name}: ${money(Math.abs(biggest.change))} / day ${biggest.change > 0 ? 'more' : 'less'}` : 'Two comparable completed periods are needed, or no daily spending change was recorded.'],
            ['Repeated overspending', repeated.length ? `${repeated.map((row) => row.name).join(', ')} exceeded allocation in each of the last 3 completed periods.` : 'No category exceeded a positive allocation in each of the last 3 completed periods.'],
            ['Growing share of income', growing ? `${growing.name}: up ${growing.shareChange.toFixed(1)} percentage points between the last two completed periods.` : 'No increase found, or comparable received income is missing.'],
        ].map(([title, detail]) => { const card = node('div', '', 'rounded-sm border border-base-300 bg-base-200/30 p-3'); card.append(node('h3', title, 'text-xs opacity-65'), node('p', detail, 'mt-2 text-sm font-medium')); return card; });
        find('insights').replaceChildren(...cards);
        const previousSelection = categorySelect.value;
        categorySelect.replaceChildren(...rows.map((row) => { const option = node('option', row.name); option.value = row.key; return option; }));
        categorySelect.value = rows.some((row) => row.key === previousSelection) ? previousSelection : rows[0].key;
        find('table').replaceChildren(...rows.map((row) => {
            const tr = node('tr', '');
            const name = node('td', '');
            const button = node('button', row.name, 'link link-primary text-left font-medium'); button.type = 'button'; button.addEventListener('click', () => { categorySelect.value = row.key; renderChart(); }); name.append(button);
            const change = row.change === null ? 'Not comparable' : `${row.change > 0 ? '+' : row.change < 0 ? '-' : ''}${money(Math.abs(row.change))}`;
            tr.append(name, node('td', row.latest.category ? money(row.latest.category.spent) : 'No category', 'whitespace-nowrap'), node('td', change, row.change > 0 ? 'text-error whitespace-nowrap' : 'whitespace-nowrap'), node('td', row.latest.share === null ? 'Needs received income or category' : `${row.latest.share.toFixed(1)}%`), node('td', `${row.overCount} of ${row.allocatedCount} allocated periods`));
            return tr;
        }));
        renderChart();
    }
    budgetSelect.addEventListener('change', render);
    range.addEventListener('change', render);
    categorySelect.addEventListener('change', renderChart);
    render();
    if (typeof ResizeObserver !== 'undefined') new ResizeObserver(renderChart).observe(find('chart'));
}

if (typeof document !== 'undefined') document.addEventListener('DOMContentLoaded', initializeCategoryTrends);
