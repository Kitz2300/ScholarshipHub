/**
 * Scholar Profile – Modal Controls
 */

document.addEventListener('DOMContentLoaded', function() {
    // ─── Note Modal ──────────────────────────────
    const noteModal = document.getElementById('noteModal');
    const openNoteBtn = document.querySelector('.open-modal-btn');
    const closeNoteBtns = document.querySelectorAll('.modal-close, .modal-close-btn');

    if (openNoteBtn) {
        openNoteBtn.addEventListener('click', function(e) {
            e.preventDefault();
            noteModal.classList.add('show');
            // Reset form if needed
            const form = document.getElementById('addNoteForm');
            if (form) form.reset();
        });
    }

    closeNoteBtns.forEach(function(btn) {
        btn.addEventListener('click', function() {
            noteModal.classList.remove('show');
        });
    });

    // Close on overlay click
    noteModal.addEventListener('click', function(e) {
        if (e.target === this) {
            this.classList.remove('show');
        }
    });

    // ─── Academic Modals ──────────────────────────
    window.openAddAcadModal = function() {
        const modal = document.getElementById('addAcadModal');
        if (modal) modal.classList.add('show');
    };

    window.openEditAcadModal = function(recordId, semester, gwa, units) {
        document.getElementById('edit_acad_record_id').value = recordId;
        document.getElementById('edit_sem').value = semester;
        document.getElementById('edit_gwa').value = gwa;
        document.getElementById('edit_units').value = units;
        const modal = document.getElementById('editAcadModal');
        if (modal) modal.classList.add('show');
    };

    window.closeAcadModals = function() {
        const addModal = document.getElementById('addAcadModal');
        const editModal = document.getElementById('editAcadModal');
        if (addModal) addModal.classList.remove('show');
        if (editModal) editModal.classList.remove('show');
    };

    // Close academic modals on overlay click
    const addAcadModal = document.getElementById('addAcadModal');
    const editAcadModal = document.getElementById('editAcadModal');
    if (addAcadModal) {
        addAcadModal.addEventListener('click', function(e) {
            if (e.target === this) this.classList.remove('show');
        });
    }
    if (editAcadModal) {
        editAcadModal.addEventListener('click', function(e) {
            if (e.target === this) this.classList.remove('show');
        });
    }
});