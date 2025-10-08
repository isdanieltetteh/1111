(function () {
    const body = document.body;
    if (!body || body.dataset.loggedIn !== 'true') {
        return;
    }

    const badge = document.querySelector('[data-notification-badge]');
    if (!badge) {
        return;
    }

    let lastCount = 0;
    let pollTimer = null;

    const updateBadge = (count) => {
        if (!count || count <= 0) {
            badge.hidden = true;
            badge.textContent = '0';
            return;
        }

        badge.hidden = false;
        badge.textContent = count > 99 ? '99+' : count.toString();
        badge.classList.toggle('pulse', count > lastCount);
        lastCount = count;
    };

    const fetchSummary = async () => {
        try {
            const response = await fetch('ajax/notifications-summary.php', {
                credentials: 'same-origin',
                headers: {
                    'Accept': 'application/json'
                }
            });

            if (!response.ok) {
                throw new Error('Failed to load notifications');
            }

            const payload = await response.json();
            if (!payload.success) {
                throw new Error(payload.message || 'Notification summary error');
            }

            updateBadge(payload.unread_count || 0);
        } catch (error) {
            console.error('Notification summary error:', error);
        }
    };

    const schedule = () => {
        if (pollTimer) {
            clearInterval(pollTimer);
        }
        pollTimer = setInterval(fetchSummary, 60000);
    };

    document.addEventListener('visibilitychange', () => {
        if (document.visibilityState === 'visible') {
            fetchSummary();
        }
    });

    fetchSummary();
    schedule();
})();
