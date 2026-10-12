export function dashboardMonthlyBudget() {
    return {
        budgets: [],
        budgetId: '',

        init() {
            const choices = this.$el.querySelector('[data-budget-choices]');
            const selector = this.$el.querySelector('#dashboard-budget-period');

            this.budgets = JSON.parse(choices.textContent);
            this.budgetId = selector?.value || this.budgets[0]?.id || '';
        },

        get selectedBudget() {
            return this.budgets.find((budget) => budget.id === this.budgetId) ?? this.budgets[0];
        },

        selectBudget() {
            const selectedBudget = this.selectedBudget;
            if (!selectedBudget) {
                return;
            }

            const url = new URL(window.location.href);
            url.searchParams.set('budget', selectedBudget.id);
            window.history.replaceState({}, '', url);
        },
    };
}

export function registerDashboardMonthlyBudget(Alpine) {
    Alpine.data('dashboardMonthlyBudget', dashboardMonthlyBudget);
}
