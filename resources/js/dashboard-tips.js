const rotationInterval = 9000;

export function dashboardTips() {
    return {
        activeIndex: 0,
        isPaused: false,
        interactionPaused: false,
        timer: null,
        tips: [
            {
                title: 'Adjust dates when your pay cycle changes',
                body: 'Open your budget options and choose Change dates. Preview the impact first; recorded expenses and received income must stay inside the period.',
            },
            {
                title: 'Create your next budget period',
                body: 'Choose Options, then Copy to next period. Set the period name and dates, then select Create next period.',
            },
            {
                title: 'Carry your plan forward, not old transactions',
                body: 'Copying a period brings over expected income and category allocations. Recorded payments and received income start fresh; unused allocations do not roll over.',
            },
            {
                title: 'Share a budget with your household',
                body: 'As the budget owner, open Sharing & settings to assign an existing app user viewer or editor access. Viewers can read; editors can update the budget.',
            },
            {
                title: 'Tune budget settings to fit your routine',
                body: 'Open Settings or Sharing & settings to choose whether subscription charges appear and how repeat pay cycles are suggested.',
            },
        ],

        init() {
            this.isPaused = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
            this.startRotation();
        },

        destroy() {
            this.stopRotation();
        },

        get currentTip() {
            return this.tips[this.activeIndex];
        },

        get tipNumber() {
            return String(this.activeIndex + 1).padStart(2, '0');
        },

        next() {
            this.activeIndex = (this.activeIndex + 1) % this.tips.length;
        },

        previous() {
            this.activeIndex = (this.activeIndex - 1 + this.tips.length) % this.tips.length;
        },

        togglePause() {
            this.isPaused = !this.isPaused;
            this.syncRotation();
        },

        pauseForInteraction() {
            this.interactionPaused = true;
            this.stopRotation();
        },

        resumeAfterInteraction(event) {
            if (event?.relatedTarget && this.$el.contains(event.relatedTarget)) {
                return;
            }
            if (this.$el.matches(':hover') || this.$el.contains(document.activeElement)) {
                return;
            }

            this.interactionPaused = false;
            this.syncRotation();
        },

        syncRotation() {
            this.stopRotation();
            if (this.isPaused || this.interactionPaused) {
                return;
            }

            this.timer = window.setInterval(() => this.next(), rotationInterval);
        },

        startRotation() {
            this.syncRotation();
        },

        stopRotation() {
            if (this.timer !== null) {
                window.clearInterval(this.timer);
                this.timer = null;
            }
        },
    };
}

export function registerDashboardTips(Alpine) {
    Alpine.data('dashboardTips', dashboardTips);
}
