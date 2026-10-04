document.addEventListener('DOMContentLoaded', () => {
    const panel = document.querySelector('[data-notification-feed]');
    if (!panel) return;
    let loading = false;
    async function refresh() {
        if (loading || document.hidden || panel.querySelector('details[open]') || panel.contains(document.activeElement)) return;
        loading = true;
        try {
            const response = await fetch(panel.dataset.notificationFeed, { headers: { Accept: 'application/json' }, signal: AbortSignal.timeout(10000) });
            if (response.ok) {
                const result = await response.json();
                if (!panel.querySelector('details[open]') && !panel.contains(document.activeElement)) panel.innerHTML = result.html;
            }
        } catch {
            // Keep the current notifications available when the connection is interrupted.
        } finally {
            loading = false;
        }
    }
    setInterval(refresh, 60000);
    window.addEventListener('focus', refresh);
    document.addEventListener('visibilitychange', refresh);
});
