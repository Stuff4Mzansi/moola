document.addEventListener('DOMContentLoaded', () => {
    const themeToggle = document.getElementById('theme-toggle');
    if (!themeToggle) return;

    const sunIcon = document.getElementById('sun-icon');
    const moonIcon = document.getElementById('moon-icon');

    // Function to visually switch the icon visibility manually
    function reflectThemeDisplay(theme) {
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
        // Persist manual preference to local storage
        localStorage.setItem('theme', nextTheme);

        // Change the icon visibility
        reflectThemeDisplay(nextTheme);
    });
});
