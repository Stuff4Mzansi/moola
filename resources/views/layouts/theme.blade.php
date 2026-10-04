<script>
    (() => {
        let savedTheme = null;
        try {
            savedTheme = localStorage.getItem('theme');
        } catch {}
        const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
        const theme = ['light', 'dark'].includes(savedTheme) ? savedTheme : (prefersDark ? 'dark' : 'light');
        document.documentElement.setAttribute('data-theme', theme);
        document.documentElement.style.colorScheme = theme;
    })();
</script>
