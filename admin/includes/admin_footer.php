    <footer class="footer-bar">
        <span>&copy; <?php echo date('Y'); ?> <?php echo SITE_NAME; ?> Admin Command Center</span>
        <div class="d-flex align-items-center gap-2">
            <span class="status-pill"><span class="status-indicator success"></span><?php echo SITE_NAME; ?> Live</span>
            <span class="status-pill"><i class="fas fa-code-branch"></i> <?php echo defined('ADMIN_PANEL_VERSION') ? ADMIN_PANEL_VERSION : 'v1.0'; ?></span>
        </div>
    </footer>

    <!-- Bootstrap 5 JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <!-- Custom Admin JS -->
    <script>
        (function () {
            const body = document.body;
            const themeToggle = document.getElementById('themeToggle');
            const sidebar = document.getElementById('sidebarMenu');
            const sidebarBackdrop = document.getElementById('sidebarBackdrop');
            const THEME_COOKIE = 'adminTheme';

            const setTheme = (theme) => {
                const resolved = theme === 'dark' ? 'dark' : 'light';
                body.setAttribute('data-theme', resolved);
                document.cookie = `${THEME_COOKIE}=${resolved};path=/;max-age=${60 * 60 * 24 * 365}`;
                if (themeToggle) {
                    const icon = themeToggle.querySelector('i');
                    if (icon) {
                        icon.className = resolved === 'dark' ? 'fas fa-sun' : 'fas fa-moon';
                    }
                }
            };

            setTheme(body.getAttribute('data-theme'));

            if (themeToggle) {
                themeToggle.addEventListener('click', () => {
                    const current = body.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
                    setTheme(current);
                });
            }

            if (sidebar && sidebarBackdrop) {
                sidebar.addEventListener('shown.bs.collapse', () => {
                    sidebar.classList.add('show');
                    sidebarBackdrop.classList.add('active');
                });

                sidebar.addEventListener('hidden.bs.collapse', () => {
                    sidebar.classList.remove('show');
                    sidebarBackdrop.classList.remove('active');
                });

                sidebarBackdrop.addEventListener('click', () => {
                    const sidebarInstance = bootstrap.Collapse.getInstance(sidebar) || new bootstrap.Collapse(sidebar, {toggle: false});
                    sidebarInstance.hide();
                });
            }
        })();

        // Auto-hide alerts after 5 seconds
        document.addEventListener('DOMContentLoaded', function() {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(function(alert) {
                setTimeout(function() {
                    alert.style.opacity = '0';
                    setTimeout(function() {
                        alert.remove();
                    }, 300);
                }, 5000);
            });
        });

        // Confirm delete actions
        document.addEventListener('click', function(e) {
            if (e.target.classList.contains('btn-danger') || (e.target.closest && e.target.closest('.btn-danger'))) {
                if (!confirm('Are you sure you want to delete this item? This action cannot be undone.')) {
                    e.preventDefault();
                }
            }
        });

        // Auto-refresh dashboard stats every 30 seconds
        if (window.location.pathname.includes('dashboard.php')) {
            setInterval(function() {
                fetch('ajax/refresh-stats.php')
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            // Update stats without page reload
                            Object.keys(data.stats).forEach(key => {
                                const element = document.querySelector(`[data-stat="${key}"]`);
                                if (element) {
                                    element.textContent = data.stats[key];
                                }
                            });
                        }
                    })
                    .catch(error => console.log('Stats refresh failed:', error));
            }, 30000);
        }
    </script>
</body>
</html>
