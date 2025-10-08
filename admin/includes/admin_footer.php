    </div>
    <footer class="admin-footer">
        <div>
            &copy; <?php echo date('Y'); ?> <?php echo SITE_NAME; ?>. All rights reserved.
        </div>
        <div class="d-flex align-items-center gap-3">
            <span class="status-pill"><i class="fas fa-circle"></i> Live</span>
            <span>PHP <?php echo phpversion(); ?></span>
            <span>Server time: <?php echo date('H:i'); ?></span>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

    <script>
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

            document.addEventListener('click', function(e) {
                if (e.target.classList.contains('btn-danger') || (e.target.closest && e.target.closest('.btn-danger'))) {
                    if (!confirm('Are you sure you want to delete this item? This action cannot be undone.')) {
                        e.preventDefault();
                    }
                }
            });

            const themeToggle = document.getElementById('themeToggle');
            const updateThemeIcon = function(theme) {
                if (!themeToggle) return;
                const icon = themeToggle.querySelector('i');
                if (!icon) return;
                if (theme === 'dark') {
                    icon.classList.remove('fa-moon');
                    icon.classList.add('fa-sun');
                } else {
                    icon.classList.remove('fa-sun');
                    icon.classList.add('fa-moon');
                }
            };

            const applyTheme = function(theme) {
                document.documentElement.setAttribute('data-theme', theme);
                localStorage.setItem('admin-theme', theme);
                updateThemeIcon(theme);
            };

            if (themeToggle) {
                const initialTheme = document.documentElement.getAttribute('data-theme') || 'light';
                updateThemeIcon(initialTheme);
                themeToggle.addEventListener('click', function() {
                    const current = document.documentElement.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
                    applyTheme(current);
                });
            }

            const sidebar = document.getElementById('sidebarMenu');
            const sidebarToggle = document.getElementById('sidebarToggle');
            if (sidebar && sidebarToggle) {
                const overlay = document.createElement('div');
                overlay.className = 'position-fixed top-0 start-0 w-100 h-100 bg-dark bg-opacity-50 d-lg-none';
                overlay.style.zIndex = '1025';
                overlay.style.display = 'none';
                document.body.appendChild(overlay);

                const closeSidebar = function() {
                    sidebar.classList.remove('show');
                    overlay.style.display = 'none';
                    document.body.classList.remove('overflow-hidden');
                };

                const openSidebar = function() {
                    sidebar.classList.add('show');
                    overlay.style.display = 'block';
                    document.body.classList.add('overflow-hidden');
                };

                sidebarToggle.addEventListener('click', function() {
                    if (sidebar.classList.contains('show')) {
                        closeSidebar();
                    } else {
                        openSidebar();
                    }
                });

                overlay.addEventListener('click', closeSidebar);

                sidebar.querySelectorAll('.nav-link').forEach(function(link) {
                    link.addEventListener('click', function() {
                        if (window.innerWidth < 992) {
                            closeSidebar();
                        }
                    });
                });
            }

            if (window.location.pathname.includes('dashboard.php')) {
                setInterval(function() {
                    fetch('ajax/refresh-stats.php')
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
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
        });
    </script>
</body>
</html>
