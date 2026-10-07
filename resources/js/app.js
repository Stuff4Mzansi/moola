import './notifications';
import './subscription-analytics';
import './budgets';
import './budget-trends';
import './category-trends';
import './dashboard-charts';
import './dashboard-layout';
import './debts';
import './goals';
import './net-worth';
import './liquidity';
import './confirm-actions';

document.addEventListener('DOMContentLoaded', () => {
    const sidebarToggle = document.querySelector('[data-sidebar-toggle]');
    const drawer = document.getElementById('my-drawer-4');
    if (sidebarToggle && drawer) {
        const reflectSidebar = () => sidebarToggle.setAttribute('aria-expanded', String(drawer.checked));
        sidebarToggle.addEventListener('click', () => {
            drawer.checked = !drawer.checked;
            drawer.dispatchEvent(new Event('change', { bubbles: true }));
        });
        drawer.addEventListener('change', () => {
            reflectSidebar();
            try {
                localStorage.setItem('moola.sidebar.expanded', String(drawer.checked));
            } catch {}
        });
        reflectSidebar();
    }

    const themeToggle = document.getElementById('theme-toggle');
    if (!themeToggle) return;

    const sunIcon = document.getElementById('sun-icon');
    const moonIcon = document.getElementById('moon-icon');

    // Function to visually switch the icon visibility manually
    function reflectThemeDisplay(theme) {
        const label = theme === 'dark' ? 'Switch to light mode' : 'Switch to dark mode';
        themeToggle.setAttribute('aria-label', label);
        themeToggle.title = label;
        if (theme === 'dark') {
            sunIcon.classList.remove('hidden');
            sunIcon.classList.add('block');

            moonIcon.classList.remove('block');
            moonIcon.classList.add('hidden');
        } else {
            sunIcon.classList.remove('block');
            sunIcon.classList.add('hidden');

            moonIcon.classList.remove('hidden');
            moonIcon.classList.add('block');
        }
    }

    // Read whatever theme was determined by the layout's <head> script
    const currentActiveTheme = document.documentElement.getAttribute('data-theme') || 'light';
    reflectThemeDisplay(currentActiveTheme);

    // Event listener for button click
    themeToggle.addEventListener('click', () => {
        const activeTheme = document.documentElement.getAttribute('data-theme');
        const nextTheme = activeTheme === 'dark' ? 'light' : 'dark';

        // Apply theme to the HTML tag
        document.documentElement.setAttribute('data-theme', nextTheme);
        document.documentElement.style.colorScheme = nextTheme;
        // Persist manual preference to local storage
        try {
            localStorage.setItem('theme', nextTheme);
        } catch {}

        // Change the icon visibility
        reflectThemeDisplay(nextTheme);
    });
});
