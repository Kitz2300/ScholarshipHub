document.addEventListener('DOMContentLoaded', function() {
    const toggleBtn = document.querySelector('.toggle-filters');
    const filterRow = document.getElementById('filter-row');

    if (toggleBtn && filterRow) {
        toggleBtn.addEventListener('click', function() {
            filterRow.classList.toggle('open');
            // Update button text
            const isOpen = filterRow.classList.contains('open');
            toggleBtn.textContent = isOpen ? 'Filters ▲' : 'Filters ▼';
        });
    }
});