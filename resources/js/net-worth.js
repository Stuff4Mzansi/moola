export function selectWorthSnapshots(snapshots, range = '6') {
    return range === 'all' ? [...snapshots] : snapshots.slice(-(range === '12' ? 12 : 6));
}

export function worthLinePath(points, key, y) {
    if (points.length < 2) {
        return '';
    }

    const coordinates = points.map((point) => ({ x: point.x, y: y(point.snapshot[key]) }));
    const intervals = coordinates.slice(1).map((point, index) => point.x - coordinates[index].x);
    const slopes = coordinates.slice(1).map((point, index) => (point.y - coordinates[index].y) / intervals[index]);
    const tangents = [slopes[0]];
    for (let index = 1; index < coordinates.length - 1; index++) {
        const previous = slopes[index - 1];
        const next = slopes[index];
        if (previous === 0 || next === 0 || Math.sign(previous) !== Math.sign(next)) {
            tangents.push(0);
            continue;
        }

        const previousInterval = intervals[index - 1];
        const nextInterval = intervals[index];
        const previousWeight = 2 * nextInterval + previousInterval;
        const nextWeight = nextInterval + 2 * previousInterval;
        tangents.push((previousWeight + nextWeight) / (previousWeight / previous + nextWeight / next));
    }
    tangents.push(slopes.at(-1));
    let path = `M ${coordinates[0].x} ${coordinates[0].y}`;

    coordinates.slice(1).forEach((point, index) => {
        const previous = coordinates[index];
        const interval = intervals[index];
        path += ` C ${previous.x + interval / 3} ${previous.y + tangents[index] * interval / 3}`;
        path += ` ${point.x - interval / 3} ${point.y - tangents[index + 1] * interval / 3}`;
        path += ` ${point.x} ${point.y}`;
    });

    return path;
}

export function worthGeometry(snapshots, breakdown = false, compact = false, viewportWidth = 0, mode = 'line') {
    const width = Math.max(compact ? 280 : 640, viewportWidth, snapshots.length * (compact ? 34 : 64) + (compact ? 70 : 96));
    const height = compact ? 186 : 220;
    const left = compact ? 48 : 64, right = width - (compact ? 8 : 16), top = compact ? 10 : 14, bottom = height - (compact ? 30 : 38);
    const includeBreakdown = breakdown || compact;
    const values = snapshots.flatMap((point) => includeBreakdown ? [point.netWorth, point.assets, point.debts] : [point.netWorth]);
    const lower = Math.min(0, ...values);
    const upper = Math.max(0, ...values);
    const span = upper - lower;
    const padding = span > 0 ? Math.max(100, span * 0.1) : Math.max(100, Math.abs(lower) * 0.05);
    const minimum = lower - padding, maximum = upper + padding;
    const times = snapshots.map((point) => Date.parse(`${point.date}T00:00:00Z`));
    const start = times[0] ?? 0, end = times.at(-1) ?? start;
    const y = (value) => bottom - (value - minimum) / (maximum - minimum) * (bottom - top);
    const points = snapshots.map((snapshot, index) => ({
        snapshot,
        x: mode === 'bar'
            ? left + (index + 0.5) / Math.max(snapshots.length, 1) * (right - left)
            : end === start ? (left + right) / 2 : left + (times[index] - start) / (end - start) * (right - left),
    }));
    return { width, height, left, right, top, bottom, minimum, maximum, points, y };
}

function initializeWorthCharts() {
    document.querySelectorAll('[data-net-worth-chart]').forEach((section) => {
        const snapshots = JSON.parse(section.querySelector('[data-worth-data]').textContent);
        const compact = section.dataset.compact === 'true';
        const range = section.querySelector('[data-worth-range]');
        const breakdown = section.querySelector('[data-worth-breakdown]');
        const chartModes = [
            ...section.querySelectorAll('[data-worth-chart-mode]'),
            ...document.querySelectorAll(`[data-worth-chart-for="${section.dataset.chartId}"]`),
        ];
        const canvas = section.querySelector('[data-worth-canvas]');
        const currencyPrefix = document.body.dataset.currencyPrefix || 'R ';
        const currencySymbol = document.body.dataset.currencySymbol || 'R';
        let mode = 'line';
        const money = (value) => `${currencyPrefix}${new Intl.NumberFormat('en-ZA', { maximumFractionDigits: 2 }).format(value / 100)}`;
        const date = (day) => new Intl.DateTimeFormat('en-ZA', { day: 'numeric', month: 'short', year: 'numeric', timeZone: 'Africa/Johannesburg' }).format(new Date(`${day}T12:00:00+02:00`));
        function svgElement(name, attributes = {}, text = null) {
            const element = document.createElementNS('http://www.w3.org/2000/svg', name);
            Object.entries(attributes).forEach(([key, value]) => element.setAttribute(key, String(value)));
            if (text !== null) element.textContent = text;
            return element;
        }
        function render() {
            const selected = selectWorthSnapshots(snapshots, range?.value || '6');
            if (!selected.length) {
                const empty = document.createElement('p');
                empty.className = 'py-6 text-center text-sm opacity-65';
                empty.textContent = 'Save your first snapshot to start a trend. No history is inferred from current balances.';
                canvas.replaceChildren(empty);
                return;
            }
            const showBreakdown = Boolean(breakdown?.checked) || compact;
            const geometry = worthGeometry(selected, showBreakdown, compact, canvas.clientWidth || 0, mode);
            const { width, height, left, right, top, bottom, points, y, minimum, maximum } = geometry;
            const id = section.dataset.chartId;
            const svg = svgElement('svg', { viewBox: `0 0 ${width} ${height}`, width, height, class: 'block min-w-full', style: `height:${height}px`, role: 'img', 'aria-labelledby': `${id}-title ${id}-description` });
            svg.append(svgElement('title', { id: `${id}-title` }, `${mode === 'bar' ? 'Bar chart' : 'Line chart'} of saved net worth snapshots over time`));
            svg.append(svgElement('desc', { id: `${id}-description` }, points.map(({ snapshot }) => `${date(snapshot.date)}: net worth ${money(snapshot.netWorth)}, assets ${money(snapshot.assets)}, obligations ${money(snapshot.debts)}`).join('. ')));
            for (let tick = 0; tick <= 4; tick++) {
                const value = minimum + (maximum - minimum) * tick / 4;
                svg.append(svgElement('line', { x1: left, x2: right, y1: y(value), y2: y(value), stroke: 'currentColor', opacity: 0.1 }));
                const tickRange = (maximum - minimum) / 4 / 100;
                const tickFormat = tickRange >= 1000
                    ? new Intl.NumberFormat('en-ZA', { notation: 'compact', maximumFractionDigits: 1 })
                    : new Intl.NumberFormat('en-ZA', { maximumFractionDigits: Math.abs(tickRange) < 1 ? 2 : Math.abs(tickRange) < 100 ? 1 : 0 });
                svg.append(svgElement('text', { x: left - 6, y: y(value) + 3, fill: 'currentColor', opacity: 0.6, 'font-size': compact ? 8 : 10, 'text-anchor': 'end' }, tickFormat.format(value / 100)));
            }
            svg.append(svgElement('text', { x: left - 6, y: top - 1, fill: 'currentColor', opacity: 0.6, 'font-size': 8, 'text-anchor': 'end' }, currencySymbol));
            if (minimum <= 0 && maximum >= 0) {
                svg.append(svgElement('line', { x1: left, x2: right, y1: y(0), y2: y(0), stroke: 'currentColor', opacity: 0.35, 'stroke-dasharray': '3 3' }));
            }
            const series = showBreakdown ? [['assets', 'Assets', 'success'], ['debts', 'Obligations', 'error'], ['netWorth', 'Net worth', 'primary']] : [['netWorth', 'Net worth', 'primary']];
            if (compact && mode === 'line' && points.length > 1) {
                const netWorthPath = worthLinePath(points, 'netWorth', y);
                const baseline = Math.max(top, Math.min(bottom, y(0)));
                const area = `${netWorthPath} L ${points.at(-1).x} ${baseline} L ${points[0].x} ${baseline} Z`;
                svg.append(svgElement('path', { d: area, fill: 'var(--color-primary)', opacity: 0.08 }));
            }
            series.forEach(([key, label, color], seriesIndex) => {
                if (mode === 'bar') {
                    const barWidth = Math.min(compact ? 10 : 18, (right - left) / Math.max(points.length, 1) / series.length * 0.82);
                    points.forEach((point) => {
                        const value = point.snapshot[key];
                        const baseline = y(0);
                        const bar = svgElement('rect', {
                            x: point.x + (seriesIndex - (series.length - 1) / 2) * barWidth,
                            y: Math.min(y(value), baseline),
                            width: Math.max(1, barWidth - 1),
                            height: Math.max(1, Math.abs(baseline - y(value))),
                            rx: compact ? 1 : 2,
                            fill: `var(--color-${color})`,
                            tabindex: '0',
                            'aria-label': `${date(point.snapshot.date)}: ${label} ${money(value)}`,
                        });
                        bar.append(svgElement('title', {}, `${date(point.snapshot.date)}: ${label} ${money(value)}`));
                        svg.append(bar);
                    });
                } else {
                    if (points.length > 1) svg.append(svgElement('path', { d: worthLinePath(points, key, y), fill: 'none', stroke: `var(--color-${color})`, 'stroke-width': key === 'netWorth' ? 2.5 : 1.5, 'stroke-linejoin': 'round', 'stroke-linecap': 'round', ...(key === 'debts' ? { 'stroke-dasharray': '4 3' } : {}) }));
                    points.forEach((point) => {
                        const circle = svgElement('circle', { cx: point.x, cy: y(point.snapshot[key]), r: compact && point === points.at(-1) ? 3.5 : 2.5, fill: `var(--color-${color})`, tabindex: '0', 'aria-label': `${date(point.snapshot.date)}: ${label} ${money(point.snapshot[key])}` });
                        circle.append(svgElement('title', {}, `${date(point.snapshot.date)}: ${label} ${money(point.snapshot[key])}`));
                        svg.append(circle);
                    });
                }
            });
            points.forEach((point, index) => svg.append(svgElement('text', { x: point.x, y: bottom + 17, fill: 'currentColor', opacity: 0.65, 'font-size': compact ? 8 : 10, 'text-anchor': index === 0 ? 'start' : index === points.length - 1 ? 'end' : 'middle' }, new Intl.DateTimeFormat('en-ZA', { day: 'numeric', month: compact ? 'numeric' : 'short', timeZone: 'Africa/Johannesburg' }).format(new Date(`${point.snapshot.date}T12:00:00+02:00`)))));
            const legend = document.createElement('div');
            legend.className = 'mt-0.5 flex flex-wrap gap-x-3 gap-y-0.5 text-[10px] opacity-75';
            legend.setAttribute('aria-label', compact ? 'Latest saved snapshot values by series' : 'Chart legend');
            series.forEach(([key, label, color]) => {
                const item = document.createElement('span');
                item.className = 'inline-flex items-center gap-1';
                const swatch = document.createElement('span');
                swatch.className = 'inline-block size-1.5 rounded-full';
                swatch.style.backgroundColor = `var(--color-${color})`;
                swatch.setAttribute('aria-hidden', 'true');
                item.append(swatch, document.createTextNode(compact ? `${label} ${money(points.at(-1).snapshot[key])}` : label));
                legend.append(item);
            });
            if (!compact) legend.append(document.createTextNode('· Dashed horizontal line = zero'));
            canvas.replaceChildren(svg, legend);
        }
        range?.addEventListener('change', render);
        breakdown?.addEventListener('change', render);
        chartModes.forEach((button) => button.addEventListener('click', () => {
            mode = button.dataset.worthChartMode === 'bar' ? 'bar' : 'line';
            chartModes.forEach((item) => {
                const active = item === button;
                item.classList.toggle('btn-primary', active);
                item.classList.toggle('btn-ghost', !active);
                item.setAttribute('aria-pressed', String(active));
            });
            render();
        }));
        render();
    });
}

function initializeWorthForms() {
    const page = document.querySelector('[data-net-worth-page]');
    if (!page) return;
    const assetDialog = document.getElementById('asset-edit');
    const valueDialog = document.getElementById('asset-value');
    const liabilityDialog = document.getElementById('liability-edit');
    const liabilityValueDialog = document.getElementById('liability-value');
    const snapshotDialog = document.getElementById('worth-snapshot');
    const fill = (form, data) => Object.entries(data).forEach(([name, value]) => {
        const field = form.elements.namedItem(name);
        if (field?.type === 'checkbox') field.checked = value === true || value === '1' || value === 1;
        else if (field) field.value = value ?? '';
    });
    function updateAccess() {
        const form = assetDialog.querySelector('form');
        const access = form.elements.namedItem('liquidity');
        if (!access) return;
        [['access_days', 'delayed', '[data-access-days]'], ['available_date', 'dated', '[data-access-date]']].forEach(([name, kind, selector]) => {
            const field = form.elements.namedItem(name);
            const active = access.value === kind;
            form.querySelector(selector).hidden = !active;
            field.disabled = !active;
            field.required = active;
        });
    }
    assetDialog.querySelector('form').elements.namedItem('liquidity')?.addEventListener('change', updateAccess);
    function openAsset(data = {}) {
        const form = assetDialog.querySelector('form');
        form.reset();
        form.action = data.action || form.dataset.createUrl;
        fill(form, data);
        const method = data.method || (data.action ? 'PUT' : 'POST');
        form.elements.namedItem('_method').value = method;
        form.querySelector('[data-asset-initial]').hidden = method === 'PUT';
        ['amount', 'date'].forEach((name) => { form.elements.namedItem(name).disabled = method === 'PUT'; });
        updateAccess();
        assetDialog.showModal();
    }
    function openLiability(data = {}) {
        const form = liabilityDialog.querySelector('form');
        form.reset();
        form.action = data.action || form.dataset.createUrl;
        fill(form, data);
        const method = data.method || (data.action ? 'PUT' : 'POST');
        form.elements.namedItem('_method').value = method;
        ['amount', 'date'].forEach((name) => { form.elements.namedItem(name).disabled = method === 'PUT'; });
        liabilityDialog.showModal();
    }
    function openValue(data) {
        const form = valueDialog.querySelector('form');
        form.reset();
        form.action = data.action;
        fill(form, data);
        valueDialog.showModal();
    }
    function openLiabilityValue(data) {
        const form = liabilityValueDialog.querySelector('form');
        form.reset();
        form.action = data.action;
        fill(form, data);
        liabilityValueDialog.showModal();
    }
    function openSnapshot(data = {}) {
        const form = snapshotDialog.querySelector('form');
        form.reset();
        fill(form, data);
        snapshotDialog.showModal();
    }
    page.addEventListener('click', (event) => {
        const button = event.target.closest('button');
        if (!button) return;
        if (button.hasAttribute('data-worth-close')) button.closest('dialog').close();
        if (button.hasAttribute('data-asset-new') || button.hasAttribute('data-asset-edit')) openAsset(button.dataset.assetEdit ? JSON.parse(button.dataset.assetEdit) : {});
        if (button.hasAttribute('data-liability-new') || button.hasAttribute('data-liability-edit')) openLiability(button.dataset.liabilityEdit ? JSON.parse(button.dataset.liabilityEdit) : {});
        if (button.hasAttribute('data-asset-value')) openValue(JSON.parse(button.dataset.assetValue));
        if (button.hasAttribute('data-liability-value')) openLiabilityValue(JSON.parse(button.dataset.liabilityValue));
        if (button.hasAttribute('data-worth-snapshot')) openSnapshot();
    });
    const filter = page.querySelector('[data-asset-filter]');
    function filterAssets() {
        if (!filter) return;
        let visible = 0;
        page.querySelectorAll('[data-asset-row]').forEach((row) => {
            row.hidden = Boolean(filter.value && row.dataset.assetRow !== filter.value);
            if (!row.hidden) visible++;
        });
        page.querySelector('[data-asset-filter-empty]').classList.toggle('hidden', visible > 0);
    }
    filter?.addEventListener('change', filterAssets);
    filterAssets();
    const liabilityFilter = page.querySelector('[data-liability-filter]');
    function filterLiabilities() {
        if (!liabilityFilter) return;
        let visible = 0;
        page.querySelectorAll('[data-liability-row]').forEach((row) => {
            row.hidden = Boolean(liabilityFilter.value && row.dataset.liabilityRow !== liabilityFilter.value);
            if (!row.hidden) visible++;
        });
        page.querySelector('[data-liability-filter-empty]').classList.toggle('hidden', !liabilityFilter.value || visible > 0);
    }
    liabilityFilter?.addEventListener('change', filterLiabilities);
    filterLiabilities();
    const reopen = JSON.parse(page.querySelector('[data-worth-reopen]')?.textContent || 'null');
    if (reopen?.kind === 'asset') openAsset(reopen.data);
    if (reopen?.kind === 'liability') openLiability(reopen.data);
    if (reopen?.kind === 'value') openValue(reopen.data);
    if (reopen?.kind === 'liability-value') openLiabilityValue(reopen.data);
    if (reopen?.kind === 'snapshot') openSnapshot(reopen.data);
    page.addEventListener('submit', (event) => {
        const form = event.target;
        if (form.method !== 'post') return;
        if (form.dataset.submitting) { event.preventDefault(); return; }
        form.dataset.submitting = 'true';
        form.querySelectorAll('button[type=submit]').forEach((button) => { button.disabled = true; });
    });
}
if (typeof document !== 'undefined') document.addEventListener('DOMContentLoaded', () => {
    initializeWorthCharts();
    initializeWorthForms();
});
