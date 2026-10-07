export function liquidityGeometry(days, buffer = 0, viewportWidth = 0) {
    const width = Math.max(640, viewportWidth), height = 210;
    const left = 68, right = width - 20, top = 14, bottom = height - 32;
    const values = days.flatMap((day) => [day.low, day.closing]);
    const lower = Math.min(0, ...values), upper = Math.max(buffer, 0, ...values);
    const padding = Math.max(100, (upper - lower) * 0.08);
    const minimum = lower - padding, maximum = upper + padding;
    const y = (value) => bottom - (value - minimum) / (maximum - minimum) * (bottom - top);
    const points = days.map((day, index) => ({ day, x: days.length === 1 ? (left + right) / 2 : left + index / Math.max(1, days.length - 1) * (right - left) }));
    return { width, height, left, right, top, bottom, minimum, maximum, points, y };
}

const currencyPrefix = document.body.dataset.currencyPrefix || 'R ';
const currencySymbol = document.body.dataset.currencySymbol || 'R';
const money = (value) => `${currencyPrefix}${new Intl.NumberFormat('en-ZA', { maximumFractionDigits: 2 }).format(value / 100)}`;
const date = (value) => new Intl.DateTimeFormat('en-ZA', { day: 'numeric', month: 'short', timeZone: 'UTC' }).format(new Date(`${value}T12:00:00Z`));

function initializeCharts() {
    document.querySelectorAll('[data-liquidity-chart]').forEach((section) => {
        const days = JSON.parse(section.querySelector('[data-liquidity-points]').textContent);
        const canvas = section.querySelector('[data-liquidity-canvas]');
        const buffer = Number(section.dataset.buffer);
        const element = (name, attributes = {}, label = null) => {
            const node = document.createElementNS('http://www.w3.org/2000/svg', name);
            Object.entries(attributes).forEach(([key, value]) => node.setAttribute(key, String(value)));
            if (label !== null) node.textContent = label;
            return node;
        };
        function render() {
            const { width, height, left, right, top, bottom, minimum, maximum, points, y } = liquidityGeometry(days, buffer, canvas.clientWidth);
            const svg = element('svg', { viewBox: `0 0 ${width} ${height}`, width, height, class: 'block min-w-full', role: 'img', 'aria-label': 'Projected unreserved cash: daily low and closing balance' });
            svg.append(element('title', {}, 'Projected unreserved cash'));
            svg.append(element('desc', {}, days.map((day) => `${date(day.date)}: low ${money(day.low)}, closing ${money(day.closing)}`).join('. ')));
            for (let tick = 0; tick <= 4; tick++) {
                const value = minimum + (maximum - minimum) * tick / 4;
                svg.append(element('line', { x1: left, x2: right, y1: y(value), y2: y(value), stroke: 'currentColor', opacity: 0.1 }));
                svg.append(element('text', { x: left - 8, y: y(value) + 4, fill: 'currentColor', opacity: 0.65, 'font-size': 10, 'text-anchor': 'end' }, new Intl.NumberFormat('en-ZA', { notation: 'compact', maximumFractionDigits: 1 }).format(value / 100)));
            }
            svg.append(element('text', { x: left - 8, y: top - 3, fill: 'currentColor', 'font-size': 9, 'text-anchor': 'end' }, currencySymbol));
            [[0, 'error'], [buffer, 'warning']].forEach(([value, color]) => svg.append(element('line', { x1: left, x2: right, y1: y(value), y2: y(value), stroke: `var(--color-${color})`, 'stroke-dasharray': '3 4', opacity: 0.6 })));
            [['low', 'primary', false], ['closing', 'info', true]].forEach(([key, color, dashed]) => {
                svg.append(element('polyline', { points: points.map(({ day, x }) => `${x},${y(day[key])}`).join(' '), fill: 'none', stroke: `var(--color-${color})`, 'stroke-width': 2, ...(dashed ? { 'stroke-dasharray': '5 4' } : {}) }));
            });
            points.forEach(({ day, x }, index) => {
                const circle = element('circle', { cx: x, cy: y(day.low), r: 2.5, fill: 'var(--color-primary)', tabindex: 0, 'aria-label': `${date(day.date)}: low ${money(day.low)}, closing ${money(day.closing)}` });
                circle.append(element('title', {}, `${date(day.date)}: low ${money(day.low)}, closing ${money(day.closing)}`));
                svg.append(circle);
                if (index === 0 || index === points.length - 1 || index % Math.ceil(points.length / 6) === 0) svg.append(element('text', { x, y: bottom + 20, fill: 'currentColor', opacity: 0.65, 'font-size': 10, 'text-anchor': 'middle' }, date(day.date)));
            });
            canvas.replaceChildren(svg);
        }
        render();
        if (typeof ResizeObserver !== 'undefined') new ResizeObserver(render).observe(canvas);
    });
}

function initializeReserves() {
    const dialog = document.getElementById('liquidity-reserve');
    if (!dialog) return;
    const form = dialog.querySelector('form');
    const field = (name) => form.elements.namedItem(name);
    let editedAsset = '', editedAmount = 0;
    function update() {
        const goal = field('purpose').value === 'goal';
        form.querySelector('[data-reserve-goal]').hidden = !goal;
        field('goal_id').disabled = !goal;
        field('goal_id').required = goal;
        form.querySelector('[data-reserve-name]').hidden = goal;
        field('name').disabled = goal;
        field('name').required = field('purpose').value === 'other';
        const selected = field('asset_id').selectedOptions[0];
        const capacity = Number(selected?.dataset.free || 0) + (field('asset_id').value === editedAsset ? editedAmount : 0);
        form.querySelector('[data-reserve-capacity]').textContent = field('asset_id').value ? `Available for this allocation: ${money(capacity)}. Goal limits are checked when saving.` : 'Choose the asset where this money is held.';
    }
    function open(data = {}) {
        form.reset();
        Object.entries(data).forEach(([name, value]) => { if (field(name)) field(name).value = value ?? ''; });
        editedAsset = data.reserve_id ? String(data.asset_id) : '';
        editedAmount = data.reserve_id ? Math.round(Number(data.amount || 0) * 100) : 0;
        update();
        dialog.showModal();
    }
    form.addEventListener('change', update);
    document.querySelector('[data-net-worth-page]').addEventListener('click', (event) => {
        const button = event.target.closest('button');
        if (!button) return;
        if (button.hasAttribute('data-reserve-new')) open();
        if (button.hasAttribute('data-reserve-edit')) open(JSON.parse(button.dataset.reserveEdit));
        if (button.hasAttribute('data-reserve-close')) dialog.close();
    });
    const reopen = JSON.parse(document.querySelector('[data-reserve-reopen]')?.textContent || 'null');
    if (reopen) open(reopen);
}

if (typeof document !== 'undefined') document.addEventListener('DOMContentLoaded', () => {
    initializeCharts();
    initializeReserves();
});
