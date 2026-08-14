// Dropdown Toggle with JS
document.addEventListener('DOMContentLoaded', function() {
    const dropdowns = document.querySelectorAll('.dropdown');

    dropdowns.forEach(function(dropdown) {
        // Show on hover
        dropdown.addEventListener('mouseenter', function() {
            const menu = this.querySelector('.dropdown-menu');
            if (menu) {
                menu.style.display = 'block';
            }
        });

        // Hide on mouse leave
        dropdown.addEventListener('mouseleave', function() {
            const menu = this.querySelector('.dropdown-menu');
            if (menu) {
                menu.style.display = 'none';
            }
        });

        // Toggle on click (for touch devices / mobile)
        const toggle = dropdown.querySelector('.dropdown-toggle');
        if (toggle) {
            toggle.addEventListener('click', function(e) {
                e.preventDefault();
                const menu = dropdown.querySelector('.dropdown-menu');
                if (menu) {
                    const isOpen = menu.style.display === 'block';
                    // Close all other dropdowns
                    document.querySelectorAll('.dropdown-menu').forEach(function(m) {
                        m.style.display = 'none';
                    });
                    menu.style.display = isOpen ? 'none' : 'block';
                }
            });
        }
    });

    // Close dropdowns when clicking outside
    document.addEventListener('click', function(e) {
        if (!e.target.closest('.dropdown')) {
            document.querySelectorAll('.dropdown-menu').forEach(function(menu) {
                menu.style.display = 'none';
            });
        }
    });
});