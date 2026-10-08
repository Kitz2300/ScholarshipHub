document.addEventListener('DOMContentLoaded', function() {
    const statusSelect = document.getElementById('academic_status');
    const loaSemCheckbox = document.getElementById('col_loa_semester');
    const loaSyCheckbox = document.getElementById('col_loa_school_year');
    const proceedBtn = document.getElementById('proceed-btn');
    const proceedWrapper = document.getElementById('proceed-btn-wrapper');
    const refreshBtn = document.getElementById('refresh-preview');
    const previewSection = document.getElementById('preview-section');
    const previewModal = document.getElementById('exportPreviewModal');
    const downloadModal = document.getElementById('exportDownloadModal');
    const sysConfirmBtn = document.getElementById('sysConfirmBtn');
    const exportForm = document.getElementById('exportForm');

    // LOA Checkbox logic: Always disabled, checked only if 'Leave of Absence' is selected
    function toggleLoaColumns() {
        const isLoa = (statusSelect && statusSelect.value === 'Leave of Absence');
        if (loaSemCheckbox) {
            loaSemCheckbox.disabled = true;
            loaSemCheckbox.checked = isLoa;
        }
        if (loaSyCheckbox) {
            loaSyCheckbox.disabled = true;
            loaSyCheckbox.checked = isLoa;
        }
    }

    if (statusSelect) {
        statusSelect.addEventListener('change', toggleLoaColumns);
        toggleLoaColumns();
    }

    // Show Preview Loading Modal when Preview button is clicked
    if (proceedBtn) {
        proceedBtn.addEventListener('click', function() {
            if (previewModal) previewModal.classList.add('sys-show');
        });
    }

    // MutationObserver to detect when Preview Section becomes visible and hide preview loading modal
    if (previewSection) {
        const observer = new MutationObserver(function(mutations) {
            mutations.forEach(function(mutation) {
                if (mutation.attributeName === 'style') {
                    if (previewSection.style.display !== 'none') {
                        if (previewModal) previewModal.classList.remove('sys-show');
                        if (proceedWrapper) proceedWrapper.style.display = 'none';
                    }
                }
            });
        });
        observer.observe(previewSection, { attributes: true });
    }

    // Restore Proceed button when Reset button is clicked
    if (refreshBtn) {
        refreshBtn.addEventListener('click', function() {
            setTimeout(function() {
                if (!previewSection || previewSection.style.display === 'none') {
                    if (proceedWrapper) proceedWrapper.style.display = 'block';
                }
            }, 100);
        });
    }

    // Show Download Loading Modal for 5 seconds and refresh page upon confirmation
    function triggerDownloadLoading() {
        if (downloadModal) {
            document.querySelectorAll('.sys-modal.sys-show').forEach(m => m.classList.remove('sys-show'));
            downloadModal.classList.add('sys-show');
        }
        setTimeout(function() {
            window.location.reload();
        }, 5000);
    }

    if (sysConfirmBtn) {
        sysConfirmBtn.addEventListener('click', function() {
            if (typeof pendingFormSubmission !== 'undefined' && pendingFormSubmission === exportForm) {
                setTimeout(triggerDownloadLoading, 100);
            }
        });
    }

    if (exportForm) {
        exportForm.addEventListener('submit', function() {
            setTimeout(triggerDownloadLoading, 100);
        });
    }
});