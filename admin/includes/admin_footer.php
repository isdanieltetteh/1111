<!-- Bootstrap 5 JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Custom Admin JS -->
    <script>
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
            if (e.target.classList.contains('btn-danger') || e.target.closest('.btn-danger')) {
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
