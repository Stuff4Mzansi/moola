export function dashboardPaceGeometry(points, planned, received, viewportWidth = 680) {
    const width = Math.max(320, viewportWidth);
    const left = 32, right = width, top = 16, bottom = 132;
    const maximum = Math.max(100, planned, received, ...points.map((point) => point.spent ?? 0), ...points.map((point) => point.planned)) * 1.1;
    const x = (index) => left + (index / Math.max(1, points.length - 1)) * (right - left);
    const y = (amount) => bottom - amount / maximum * (bottom - top);
    return { width, left, right, top, bottom, maximum, x, y };
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
    const budget = budgets[0];
    const host = section.querySelector('[data-pace-chart]');
    const geometry = dashboardPaceGeometry(budget.points, budget.planned, budget.received, host.clientWidth || 680);
    const { width, left, right, top, bottom, maximum, x, y } = geometry;
    const svg = svgElement('svg', { viewBox: `0 0 ${width} 176`, class: 'block h-auto w-full', role: 'img', 'aria-labelledby': 'dashboard-budget-pace-title dashboard-budget-pace-svg-title dashboard-budget-pace-description' });
    svg.append(svgElement('title', { id: 'dashboard-budget-pace-svg-title' }, `${budget.name} spending pace`));
    svg.append(svgElement('desc', { id: 'dashboard-budget-pace-description' }, `Cumulative recorded spending, straight-line budget pace, and total income received by ${shortDate(budget.start)} to ${shortDate(budget.end)}.`));
    for (let tick = 0; tick <= 3; tick++) {
        const amount = maximum * tick / 3;
        const position = y(amount);
        svg.append(svgElement('line', { x1: left, x2: right, y1: position, y2: position, class: 'stroke-base-content', opacity: 0.1 }));
        svg.append(svgElement('text', { x: left - 8, y: position + 3, 'text-anchor': 'end', 'font-size': 9, class: 'fill-base-content', opacity: 0.65 }, new Intl.NumberFormat('en-ZA', { notation: 'compact', maximumFractionDigits: 1 }).format(amount / 100)));
    }
    if (budget.received > 0) {
        svg.append(svgElement('line', { x1: left, x2: right, y1: y(budget.received), y2: y(budget.received), class: 'stroke-success', 'stroke-width': 1.5, 'stroke-dasharray': '2 4', opacity: 0.8 }));
    }
    svg.append(svgElement('path', { d: linePath(budget.points, 'planned', geometry), fill: 'none', class: 'stroke-sky-500', 'stroke-width': 2, 'stroke-dasharray': '1 4', 'stroke-linecap': 'round' }));
    svg.append(svgElement('path', { d: linePath(budget.points, 'spent', geometry), fill: 'none', class: 'stroke-orange-600', 'stroke-width': 2.5, 'stroke-linejoin': 'round', 'stroke-linecap': 'round' }));
    const targetTicks = width < 380 ? 3 : width < 600 ? 4 : 6;
    const tickInterval = Math.max(1, Math.ceil((budget.points.length - 1) / targetTicks));
    budget.points.forEach((point, index) => {
        if (index === 0 || index === budget.points.length - 1 || index % tickInterval === 0) {
            svg.append(svgElement('text', { x: x(index), y: bottom + 18, 'text-anchor': index === 0 ? 'start' : index === budget.points.length - 1 ? 'end' : 'middle', 'font-size': 9, class: 'fill-base-content', opacity: 0.65 }, shortDate(point.date)));
        }
    });
    host.replaceChildren(svg);
    section.querySelector('[data-pace-summary]').textContent = `${money(budget.spent)} spent · ${money(budget.planned)} planned · ${money(budget.received)} income received to date`;
    const link = section.querySelector('[data-pace-link]');
    link.href = budget.url;
    link.textContent = `Review ${budget.name}`;
}

export function registerDashboardCharts(Alpine) {
    Alpine.data('dashboardPaceChart', () => ({
        budgets: [],
        observer: null,

        init() {
            const data = this.$el.querySelector('[data-pace-data]');
            if (!data) {
                return;
            }
            this.budgets = JSON.parse(data.textContent);
            this.$nextTick(() => {
                this.render();
                const host = this.$el.querySelector('[data-pace-chart]');
                if (typeof ResizeObserver !== 'undefined' && host) {
                    this.observer = new ResizeObserver(() => this.render());
                    this.observer.observe(host);
                }
            });
        },

        destroy() {
            this.observer?.disconnect();
        },

        render() {
            if (this.budgets.length) {
                renderPace(this.$el, this.budgets);
            }
        },
    }));
}
