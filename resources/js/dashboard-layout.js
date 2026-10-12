export function dashboardLayout() {
    return {
        editing: false,
        saving: false,
        statusMessage: '',
        draft: [],
        draggedId: null,
        dropTargetId: null,
        resizeState: null,

        init() {
            const data = this.$el.querySelector('[data-layout-data]');
            this.draft = JSON.parse(data.textContent);
        },

        get hasVisibleWidgets() {
            return this.draft.some((widget) => widget.visible);
        },

        widget(id) {
            return this.draft.find((item) => item.id === id);
        },

        position(id) {
            return this.draft.findIndex((item) => item.id === id);
        },

        setWidget(id, changes) {
            const widget = this.widget(id);
            if (widget) {
                Object.assign(widget, changes);
            }
        },

        beginEditing() {
            this.editing = true;
            this.$nextTick(() => this.$refs.saveButton?.focus());
        },

        async save() {
            if (this.saving) {
                return;
            }
            this.saving = true;
            this.statusMessage = 'Saving dashboard layout.';
            try {
                const response = await fetch(this.$el.dataset.layoutUrl, {
                    method: 'PUT',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
                    },
                    body: JSON.stringify({
                        widgets: this.draft.map(({ id, visible, width, height }) => ({ id, visible, width, height })),
                    }),
                });
                const result = await response.json();
                if (!response.ok || !Array.isArray(result.widgets)) {
                    throw new Error(result.message || 'The dashboard layout could not be saved.');
                }
                this.statusMessage = result.message || 'Dashboard layout saved.';
                this.editing = false;
                window.location.reload();
            } catch (error) {
                this.statusMessage = error instanceof Error ? error.message : 'The dashboard layout could not be saved.';
            } finally {
                this.saving = false;
            }
        },

        cancel() {
            this.editing = false;
            this.statusMessage = 'Changes discarded.';
            window.location.reload();
        },

        reset() {
            this.draft = JSON.parse(this.$el.querySelector('[data-layout-defaults]').textContent);
            this.statusMessage = 'Default layout restored. Save to keep these changes.';
        },

        handleClick(event) {
            const moveButton = event.target.closest('[data-layout-move]');
            if (moveButton) {
                event.preventDefault();
                this.moveWidget(moveButton.closest('[data-layout-widget]')?.dataset.layoutWidget, moveButton.dataset.layoutMove === 'up' ? -1 : 1);
                return;
            }
            const visibilityButton = event.target.closest('[data-layout-hide]');
            if (visibilityButton) {
                event.preventDefault();
                const id = visibilityButton.closest('[data-layout-widget]')?.dataset.layoutWidget;
                this.setWidget(id, { visible: !this.widget(id)?.visible });
            }
        },

        moveWidget(id, offset) {
            const from = this.position(id);
            const to = from + offset;
            if (from < 0 || to < 0 || to >= this.draft.length) {
                return;
            }
            const [widget] = this.draft.splice(from, 1);
            this.draft.splice(to, 0, widget);
            this.$nextTick(() => this.$el.querySelector(`[data-layout-widget="${id}"] [data-layout-move="${offset < 0 ? 'up' : 'down'}"]`)?.focus());
        },

        handleDragStart(event) {
            const item = event.target.closest('[data-layout-widget]');
            if (!item) {
                return;
            }
            this.draggedId = item.dataset.layoutWidget;
            event.dataTransfer.effectAllowed = 'move';
            event.dataTransfer.setData('text/plain', this.draggedId);
        },

        handleDragOver(event) {
            const item = event.target.closest('[data-layout-widget]');
            if (!item || !this.draggedId || item.dataset.layoutWidget === this.draggedId) {
                return;
            }
            event.preventDefault();
            this.dropTargetId = item.dataset.layoutWidget;
        },

        handleDrop(event) {
            const item = event.target.closest('[data-layout-widget]');
            const targetId = item?.dataset.layoutWidget;
            if (!targetId || !this.draggedId || targetId === this.draggedId) {
                return;
            }
            event.preventDefault();
            const [widget] = this.draft.splice(this.position(this.draggedId), 1);
            this.draft.splice(this.position(targetId), 0, widget);
            this.clearDrag();
        },

        handleDragEnd() {
            this.clearDrag();
        },

        clearDrag() {
            this.draggedId = null;
            this.dropTargetId = null;
        },

        handlePointerDown(event) {
            const handle = event.target.closest('[data-layout-resize]');
            const item = handle?.closest('[data-layout-widget]');
            if (!handle || !item || event.button !== 0) {
                return;
            }
            event.preventDefault();
            handle.setPointerCapture(event.pointerId);
            this.resizeState = {
                id: item.dataset.layoutWidget,
                startX: event.clientX,
                startY: event.clientY,
                startWidth: { small: 4, medium: 6, wide: 12 }[this.widget(item.dataset.layoutWidget).width],
                originalWidth: this.widget(item.dataset.layoutWidget).width,
                startHeight: this.widget(item.dataset.layoutWidget).height,
                target: handle,
            };
        },

        handlePointerMove(event) {
            if (!this.resizeState) {
                return;
            }
            const state = this.resizeState;
            const columns = window.matchMedia('(min-width: 1024px)').matches ? 12 : 1;
            const columnWidth = this.$el.querySelector('[data-layout-grid]').getBoundingClientRect().width / columns;
            const widthSpan = Math.max(1, Math.min(12, state.startWidth + Math.round((event.clientX - state.startX) / columnWidth)));
            const width = columns === 1 ? this.widget(state.id).width : widthSpan <= 4 ? 'small' : widthSpan <= 8 ? 'medium' : 'wide';
            const heightSteps = { compact: 1, auto: 2, regular: 2, tall: 3 };
            const heightStep = Math.max(1, Math.min(3, heightSteps[state.startHeight] + Math.round((event.clientY - state.startY) / 180)));
            const height = ['compact', 'regular', 'tall'][heightStep - 1];
            this.setWidget(state.id, { width, height });
        },

        handlePointerUp(event) {
            if (event && this.resizeState?.target?.hasPointerCapture?.(event.pointerId)) {
                this.resizeState.target.releasePointerCapture(event.pointerId);
            }
            this.resizeState = null;
        },

        handlePointerCancel() {
            if (this.resizeState) {
                this.setWidget(this.resizeState.id, {
                    width: this.resizeState.originalWidth,
                    height: this.resizeState.startHeight,
                });
            }
            this.resizeState = null;
        },

        handleBeforeUnload(event) {
            if (this.editing && this.draft.length) {
                event.preventDefault();
                event.returnValue = '';
            }
        },
    };
}

export function registerDashboardLayout(Alpine) {
    Alpine.data('dashboardLayout', dashboardLayout);
}
