const chartColours = ['#6366f1', '#14b8a6', '#f59e0b', '#ec4899', '#0ea5e9', '#8b5cf6', '#84cc16', '#f97316'];

export function dashboardPaceGeometry(points, planned, received, viewportWidth = 680) {
    const width = Math.max(520, viewportWidth);
    const left = 72, right = width - 20, top = 16, bottom = 132;
    const maximum = Math.max(100, planned, received, ...points.map((point) => point.spent ?? 0), ...points.map((point) => point.planned)) * 1.1;
    const x = (index) => left + (index / Math.max(1, points.length - 1)) * (right - left);
    const y = (amount) => bottom - amount / maximum * (bottom - top);
    return { width, left, right, top, bottom, maximum, x, y };
}

export function categoryMixSegments(categories) {
    const total = categories.reduce((sum, category) => sum + category.amount, 0);
    let offset = 0;
    return { total, segments: categories.map((category, index) => {
        const share = total ? category.amount / total * 100 : 0;
        const segment = { ...category, share, offset, colour: chartColours[index % chartColours.length] };
        offset += share;
        return segment;
    }) };
}

function svgElement(name, attributes = {}, content = null) {
    const element = document.createElementNS('http://www.w3.org/2000/svg', name);
    Object.entries(attributes).forEach(([key, value]) => element.setAttribute(key, String(value)));
    if (content !== null) element.textContent = content;
    return element;
}

function money(cents) {
    const prefix = document.body.dataset.currencyPrefix || 'R ';
    return `${prefix}${new Intl.NumberFormat('en-ZA', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(cents / 100)}`;
}

function shortDate(day) {
    return new Intl.DateTimeFormat('en-ZA', { day: 'numeric', month: 'short', timeZone: 'Africa/Johannesburg' }).format(new Date(`${day}T12:00:00+02:00`));
}

function linePath(points, key, geometry) {
    let path = '';
    points.forEach((point, index) => {
        if (point[key] === null) return;
        path += `${path ? ' L ' : 'M '}${geometry.x(index)} ${geometry.y(point[key])}`;
    });
    return path;
}

function renderPace(section, budgets) {
    const selector = section.querySelector('[data-pace-budget]');
    const budget = budgets.find((item) => String(item.id) === selector.value) ?? budgets[0];
    const host = section.querySelector('[data-pace-chart]');
    const geometry = dashboardPaceGeometry(budget.points, budget.planned, budget.received, host.clientWidth || 680);
    const { width, left, right, top, bottom, maximum, x, y } = geometry;
    const svg = svgElement('svg', { viewBox: `0 0 ${width} 176`, width, height: 176, class: 'block max-w-none', style: `width:${width}px;height:176px`, role: 'img', 'aria-labelledby': 'dashboard-budget-pace-title dashboard-budget-pace-description' });
    svg.append(svgElement('title', { id: 'dashboard-budget-pace-description' }, `${budget.name}: cumulative spending, budget pace and received income from ${shortDate(budget.start)} to ${shortDate(budget.end)}.`));
    for (let tick = 0; tick <= 3; tick++) {
        const amount = maximum * tick / 3;
        const position = y(amount);
        svg.append(svgElement('line', { x1: left, x2: right, y1: position, y2: position, class: 'stroke-base-content', opacity: 0.1 }));
        svg.append(svgElement('text', { x: left - 8, y: position + 3, 'text-anchor': 'end', 'font-size': 9, class: 'fill-base-content', opacity: 0.65 }, new Intl.NumberFormat('en-ZA', { notation: 'compact', maximumFractionDigits: 1 }).format(amount / 100)));
    }
    if (budget.received > 0) {
        svg.append(svgElement('line', { x1: left, x2: right, y1: y(budget.received), y2: y(budget.received), class: 'stroke-success', 'stroke-width': 1.5, 'stroke-dasharray': '2 4', opacity: 0.8 }));
    }
    svg.append(svgElement('path', { d: linePath(budget.points, 'planned', geometry), fill: 'none', class: 'stroke-primary', 'stroke-width': 2, 'stroke-dasharray': '6 4' }));
    svg.append(svgElement('path', { d: linePath(budget.points, 'spent', geometry), fill: 'none', class: 'stroke-orange-600', 'stroke-width': 2.5, 'stroke-linejoin': 'round', 'stroke-linecap': 'round' }));
    const tickInterval = Math.max(1, Math.ceil((budget.points.length - 1) / 6));
    budget.points.forEach((point, index) => {
        if (index === 0 || index === budget.points.length - 1 || index % tickInterval === 0) {
            svg.append(svgElement('text', { x: x(index), y: bottom + 18, 'text-anchor': index === 0 ? 'start' : index === budget.points.length - 1 ? 'end' : 'middle', 'font-size': 9, class: 'fill-base-content', opacity: 0.65 }, shortDate(point.date)));
        }
    });
    host.replaceChildren(svg);
    section.querySelector('[data-pace-summary]').textContent = `${money(budget.spent)} spent · ${money(budget.planned)} planned · ${money(budget.received)} received`;
    const link = section.querySelector('[data-pace-link]');
    link.href = budget.url;
    link.textContent = `Review ${budget.name}`;
}

function renderMix(section, budgets) {
    const selector = section.querySelector('[data-mix-budget]');
    const budget = budgets.find((item) => String(item.id) === selector.value) ?? budgets[0];
    const host = section.querySelector('[data-mix-chart]');
    const legend = section.querySelector('[data-mix-legend]');
    const empty = section.querySelector('[data-mix-empty]');
    const totalNode = section.querySelector('[data-mix-total]');
    const { total, segments } = categoryMixSegments(budget.categories);
    const svg = svgElement('svg', { viewBox: '0 0 120 120', class: 'h-full w-full', role: 'img', 'aria-labelledby': 'dashboard-spending-mix-title dashboard-spending-mix-description' });
    svg.append(svgElement('title', { id: 'dashboard-spending-mix-description' }, `${budget.name}: spending by category from ${shortDate(budget.start)} to ${shortDate(budget.end)}.`));
    svg.append(svgElement('circle', { cx: 60, cy: 60, r: 43, fill: 'none', class: 'stroke-base-300', 'stroke-width': 14 }));
    segments.forEach((segment) => {
        if (segment.share <= 0) return;
        svg.append(svgElement('circle', { cx: 60, cy: 60, r: 43, fill: 'none', stroke: segment.colour, 'stroke-width': 14, pathLength: 100, 'stroke-dasharray': `${segment.share} ${100 - segment.share}`, 'stroke-dashoffset': -segment.offset, transform: 'rotate(-90 60 60)' }));
    });
    svg.append(svgElement('text', { x: 60, y: 57, 'text-anchor': 'middle', 'font-size': 11, class: 'fill-base-content', 'font-weight': 600 }, money(total)));
    svg.append(svgElement('text', { x: 60, y: 71, 'text-anchor': 'middle', 'font-size': 8, class: 'fill-base-content', opacity: 0.65 }, 'spent'));
    host.replaceChildren(svg);
    legend.replaceChildren(...segments.map((segment) => {
        const item = document.createElement('li');
        item.className = 'flex items-center justify-between gap-2 py-1';
        const name = document.createElement('span');
        name.className = 'flex min-w-0 items-start gap-1.5';
        const swatch = document.createElement('span');
        swatch.className = 'mt-1 size-2 shrink-0 rounded-sm';
        swatch.style.backgroundColor = segment.colour;
        swatch.setAttribute('aria-hidden', 'true');
        const label = document.createElement('span');
        label.className = 'break-words';
        label.textContent = segment.name;
        name.append(swatch, label);
        const value = document.createElement('span');
        value.className = 'shrink-0 text-right tabular-nums';
        value.textContent = `${money(segment.amount)} · ${segment.share.toFixed(1)}%`;
        item.append(name, value);
        return item;
    }));
    totalNode.textContent = `${shortDate(budget.start)}–${shortDate(budget.end)}`;
    empty.hidden = total > 0;
    host.hidden = total === 0;
    legend.hidden = total === 0;
}

function initializeDashboardCharts() {
    document.querySelectorAll('[data-dashboard-budget-pace]').forEach((section) => {
        const data = section.querySelector('[data-pace-data]');
        if (!data) return;
        const budgets = JSON.parse(data.textContent);
        const selector = section.querySelector('[data-pace-budget]');
        selector.value = String(budgets[0].id);
        selector.addEventListener('change', () => renderPace(section, budgets));
        renderPace(section, budgets);
        if (typeof ResizeObserver !== 'undefined') new ResizeObserver(() => renderPace(section, budgets)).observe(section.querySelector('[data-pace-chart]'));
    });
    document.querySelectorAll('[data-dashboard-spending-mix]').forEach((section) => {
        const data = section.querySelector('[data-mix-data]');
        if (!data) return;
        const budgets = JSON.parse(data.textContent);
        const selector = section.querySelector('[data-mix-budget]');
        selector.value = String(budgets[0].id);
        selector.addEventListener('change', () => renderMix(section, budgets));
        renderMix(section, budgets);
    });
}

if (typeof document !== 'undefined') document.addEventListener('DOMContentLoaded', initializeDashboardCharts);
