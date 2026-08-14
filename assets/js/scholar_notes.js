document.addEventListener('DOMContentLoaded', function() {
    // Get modal elements
    const modal = document.getElementById('noteModal');
    const openBtn = document.querySelector('.open-modal-btn');
    const closeBtns = document.querySelectorAll('.modal-close, .modal-close-btn');
    const form = document.getElementById('addNoteForm');

    // Ensure elements exist before adding listeners
    if (!modal || !openBtn || !form) return;

    // Open modal
    openBtn.addEventListener('click', function(e) {
        e.preventDefault();
        modal.classList.add('show');
        form.reset();
    });

    // Close modal via close buttons or ×
    closeBtns.forEach(btn => {
        btn.addEventListener('click', function() {
            modal.classList.remove('show');
        });
    });

    // Close modal when clicking outside the content
    window.addEventListener('click', function(e) {
        if (e.target === modal) {
            modal.classList.remove('show');
        }
    });

    // Prevent modal from closing if click inside the content
    document.querySelector('.modal-content').addEventListener('click', function(e) {
        e.stopPropagation();
    });
});