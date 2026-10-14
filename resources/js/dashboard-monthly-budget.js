export function dashboardMonthlyBudget() {
    return {
        budgets: [],

        init() {
            const choices = this.$el.querySelector('[data-budget-choices]');
            this.budgets = JSON.parse(choices.textContent);
        },

        get selectedBudget() {
            return this.budgets[0];
        },
    };
}

export function registerDashboardMonthlyBudget(Alpine) {
    Alpine.data('dashboardMonthlyBudget', dashboardMonthlyBudget);
}
