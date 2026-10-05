export function moveDashboardWidget(layout, id, targetId, after = false) {
    const widget = layout.find((item) => item.id === id);
    if (!widget || id === targetId || !layout.some((item) => item.id === targetId)) return layout.map((item) => ({ ...item }));
    const remaining = layout.filter((item) => item.id !== id).map((item) => ({ ...item }));
    const index = remaining.findIndex((item) => item.id === targetId);
    remaining.splice(index + (after ? 1 : 0), 0, { ...widget });
    return remaining;
}

export function dashboardTileSize(widthFraction, height) {
    return { width: widthFraction < 5 / 12 ? 'small' : widthFraction < 0.75 ? 'medium' : 'wide', height: height < 400 ? 'compact' : height < 560 ? 'regular' : 'tall' };
}

function initializeDashboardLayout() {
    const page = document.querySelector('[data-dashboard-customization]');
    if (!page) return;
    const find = (name) => page.querySelector(`[data-layout-${name}]`);
    const grid = find('grid');
    const tiles = new Map([...page.querySelectorAll('[data-layout-widget]')].map((tile) => [tile.dataset.layoutWidget, tile]));
    const clone = (value) => value.map((item) => ({ ...item }));
    let saved = JSON.parse(find('data').textContent);
    let draft = clone(saved);
    const defaults = JSON.parse(find('defaults').textContent);
    let editing = false;
    let saving = false;
    let draggedId = null;
    let resizing = null;

    function render() {
        const order = [...grid.children].map((tile) => tile.dataset.layoutWidget);
        const reordered = draft.some((widget, index) => widget.id !== order[index]);
        draft.forEach((widget) => {
            const tile = tiles.get(widget.id);
            tile.hidden = !widget.visible;
            tile.dataset.width = widget.width;
            tile.dataset.height = widget.height;
            tile.querySelector('[data-layout-width]').value = widget.width;
            tile.querySelector('[data-layout-height]').value = widget.height;
            const visibility = [...page.querySelectorAll('[data-layout-visible]')].find((input) => input.dataset.layoutVisible === widget.id);
            visibility.checked = widget.visible;
            tile.querySelectorAll('[data-layout-toolbar]').forEach((toolbar) => { toolbar.hidden = !editing; });
            if (reordered) grid.appendChild(tile);
        });
        find('empty').hidden = draft.some((widget) => widget.visible);
        find('panel').hidden = !editing;
        find('edit').hidden = editing;
        find('edit').setAttribute('aria-expanded', String(editing));
        page.querySelectorAll('[data-layout-panel] input, [data-layout-panel] button, [data-layout-toolbar] button, [data-layout-toolbar] select').forEach((control) => { control.disabled = saving; });
    }

    function setWidget(id, values) {
        draft = draft.map((widget) => widget.id === id ? { ...widget, ...values } : widget);
        render();
    }

    function endEditing(message) {
        editing = false;
        resizing = null;
        draggedId = null;
        tiles.forEach((tile) => { delete tile.dataset.dragging; delete tile.dataset.dropTarget; });
        find('status').textContent = message;
        render();
        find('edit').focus();
    }

    find('edit').addEventListener('click', () => { draft = clone(saved); editing = true; find('status').textContent = 'Editing your dashboard. Save to keep your changes.'; render(); find('save').focus(); });
    find('cancel').addEventListener('click', () => { if (saving) return; draft = clone(saved); endEditing('Layout changes cancelled.'); });
    find('reset').addEventListener('click', () => { if (saving) return; draft = clone(defaults); find('status').textContent = 'Default layout restored in this preview. Save to keep it.'; render(); });
    find('save').addEventListener('click', async () => {
        if (saving) return;
        saving = true; render(); find('status').textContent = 'Saving your dashboard layout…';
        try {
            const response = await fetch(page.dataset.layoutUrl, { method: 'PUT', headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': page.dataset.layoutToken }, body: JSON.stringify({ widgets: draft }) });
            const result = await response.json();
            if (!response.ok) throw new Error(result.errors ? Object.values(result.errors).flat().join(' ') : result.message || 'Please retry.');
            saved = clone(result.widgets); draft = clone(saved); saving = false; endEditing(result.message);
        } catch (error) {
            saving = false; find('status').textContent = `Layout not saved: ${error.message} Your changes are still here. Retry Save layout.`; render();
        }
    });

    page.addEventListener('change', (event) => {
        if (!editing || saving) return;
        const input = event.target;
        if (input.matches('[data-layout-visible]')) { setWidget(input.dataset.layoutVisible, { visible: input.checked }); return; }
        const tile = input.closest('[data-layout-widget]');
        if (!tile) return;
        if (input.matches('[data-layout-width]')) setWidget(tile.dataset.layoutWidget, { width: input.value });
        if (input.matches('[data-layout-height]')) setWidget(tile.dataset.layoutWidget, { height: input.value });
    });
    page.addEventListener('click', (event) => {
        if (!editing || saving) return;
        const button = event.target.closest('[data-layout-move], [data-layout-hide]');
        const tile = button?.closest('[data-layout-widget]');
        if (!tile) return;
        const id = tile.dataset.layoutWidget;
        if (button.matches('[data-layout-hide]')) { setWidget(id, { visible: false }); find('save').focus(); return; }
        const visible = draft.filter((widget) => widget.visible);
        const index = visible.findIndex((widget) => widget.id === id);
        const target = visible[index + (button.dataset.layoutMove === 'up' ? -1 : 1)];
        if (target) { draft = moveDashboardWidget(draft, id, target.id, button.dataset.layoutMove === 'down'); render(); button.focus(); }
    });

    page.addEventListener('dragstart', (event) => {
        const handle = event.target.closest('[data-layout-drag]');
        if (!editing || saving || !handle) { event.preventDefault(); return; }
        draggedId = handle.closest('[data-layout-widget]').dataset.layoutWidget;
        event.dataTransfer.effectAllowed = 'move'; event.dataTransfer.setData('text/plain', draggedId);
        tiles.get(draggedId).dataset.dragging = 'true';
    });
    page.addEventListener('dragover', (event) => {
        const tile = event.target.closest('[data-layout-widget]');
        if (!editing || saving || !draggedId || !tile || tile.dataset.layoutWidget === draggedId) return;
        event.preventDefault(); event.dataTransfer.dropEffect = 'move';
        tiles.forEach((item) => { item.dataset.dropTarget = String(item === tile); });
    });
    page.addEventListener('drop', (event) => {
        const tile = event.target.closest('[data-layout-widget]');
        if (!editing || saving || !draggedId || !tile) return;
        event.preventDefault();
        const rect = tile.getBoundingClientRect();
        draft = moveDashboardWidget(draft, draggedId, tile.dataset.layoutWidget, event.clientY > rect.top + rect.height / 2);
        render();
    });
    page.addEventListener('dragend', () => { draggedId = null; tiles.forEach((tile) => { delete tile.dataset.dragging; delete tile.dataset.dropTarget; }); });

    page.addEventListener('pointerdown', (event) => {
        const handle = event.target.closest('[data-layout-resize]');
        if (!editing || saving || !handle || event.button !== 0) return;
        event.preventDefault();
        const tile = handle.closest('[data-layout-widget]');
        resizing = { id: tile.dataset.layoutWidget, x: event.clientX, y: event.clientY, width: tile.getBoundingClientRect().width, height: tile.querySelector('.dashboard-widget-body').getBoundingClientRect().height, gridWidth: grid.getBoundingClientRect().width, original: { ...draft.find((widget) => widget.id === tile.dataset.layoutWidget) } };
        handle.setPointerCapture(event.pointerId);
    });
    page.addEventListener('pointermove', (event) => {
        if (!editing || saving || !resizing) return;
        const size = dashboardTileSize((resizing.width + event.clientX - resizing.x) / Math.max(1, resizing.gridWidth), resizing.height + event.clientY - resizing.y);
        if (window.matchMedia('(max-width: 1023px)').matches) size.width = resizing.original.width;
        setWidget(resizing.id, size);
    });
    page.addEventListener('pointerup', () => { resizing = null; });
    page.addEventListener('pointercancel', () => { if (resizing) { setWidget(resizing.id, resizing.original); resizing = null; } });
    window.addEventListener('beforeunload', (event) => { if (editing && JSON.stringify(draft) !== JSON.stringify(saved)) { event.preventDefault(); event.returnValue = ''; } });
    render();
}

if (typeof document !== 'undefined') document.addEventListener('DOMContentLoaded', initializeDashboardLayout);
