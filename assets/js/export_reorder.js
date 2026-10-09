document.addEventListener('DOMContentLoaded', function() {

    const form = document.getElementById('exportForm');
    const checkboxes = document.querySelectorAll('input[name="columns[]"]');
    const previewSection = document.getElementById('preview-section');
    const previewHeaders = document.getElementById('preview-headers');
    const previewBody = document.getElementById('preview-body');
    const previewModal = document.getElementById('exportPreviewModal');
    const proceedBtn = document.getElementById('proceed-btn');
    const proceedWrapper = document.getElementById('proceed-btn-wrapper');
    const exportBtn = document.getElementById('export-btn');
    const refreshBtn = document.getElementById('refresh-preview');
    const columnOrderInput = document.getElementById('column_order');
    let sortableInstance = null;
    let previewData = null; // store the latest preview data

    // Helper: Toggle Steps 1 - 3 visibility
    function toggleInitialSteps(show) {
        const stepSections = document.querySelectorAll('#exportForm > .form-section:not(#preview-section)');
        stepSections.forEach(sec => {
            sec.style.display = show ? 'block' : 'none';
        });
        if (proceedWrapper) {
            proceedWrapper.style.display = show ? 'block' : 'none';
        }
    }

    // ===== Helper: Get selected column names =====
    function getSelectedColumns() {
        const selected = [];
        checkboxes.forEach(cb => {
            if (cb.checked) selected.push(cb.value);
        });
        return selected;
    }

    // ===== Fetch preview from server =====
    function fetchPreview() {
        const sySelect = document.getElementById('school_year');
        const semSelect = document.getElementById('semester');
        const spSelect = document.getElementById('scholar_program');

        // Validation: Block proceeding if required filters are unselected
        if (!sySelect?.value || !semSelect?.value || !spSelect?.value) {
            alert('Please select a School Year, Semester, and Scholarship Program before generating a preview.');
            if (previewModal) previewModal.classList.remove('sys-show');
            if (!spSelect?.value) spSelect?.focus();
            else if (!sySelect?.value) sySelect?.focus();
            else if (!semSelect?.value) semSelect?.focus();
            return;
        }

        const selected = getSelectedColumns();
        if (selected.length === 0) {
            alert('Please select at least one column to export.');
            previewSection.style.display = 'none';
            if (previewModal) previewModal.classList.remove('sys-show');
            return;
        }

        // Use FormData from form to pass ALL filters
        const data = new FormData(form);
        data.append('action', 'preview');

        fetch('../functions/admin/export_scholars_process.php', {
            method: 'POST',
            body: data
        })
        .then(response => response.json())
        .then(result => {
            if (result.error) {
                alert('Preview error: ' + result.error);
                if (previewModal) previewModal.classList.remove('sys-show');
                return;
            }
            previewData = result;
            renderPreview(result);
        })
        .catch(err => {
            alert('Failed to fetch preview data.');
            console.error(err);
            if (previewModal) previewModal.classList.remove('sys-show');
        });
    }

    // ===== Render the preview table =====
    function renderPreview(data) {
        const columns = data.columns || [];
        const rows = data.rows || [];

        // Build headers
        let headerHtml = '<tr>';
        columns.forEach(col => {
            const label = data.display_headers[col] || col.replace(/_/g, ' ');
            headerHtml += `<th data-column="${col}">${label}</th>`;
        });
        headerHtml += '</tr>';
        previewHeaders.innerHTML = headerHtml;

        // Build body (limit to 5 rows)
        let bodyHtml = '';
        if (rows.length === 0) {
            bodyHtml = `<tr><td colspan="${columns.length}">No data matches the selected filters.</td></tr>`;
        } else {
            const maxRows = Math.min(rows.length, 5);
            for (let i = 0; i < maxRows; i++) {
                const row = rows[i];
                bodyHtml += '<tr>';
                columns.forEach(col => {
                    const val = row[col] !== undefined ? row[col] : '';
                    bodyHtml += `<td>${val}</td>`;
                });
                bodyHtml += '</tr>';
            }
        }
        previewBody.innerHTML = bodyHtml;

        // Destroy old Sortable instance if any
        if (sortableInstance) {
            sortableInstance.destroy();
        }

        // Make headers sortable
        const headerRow = previewHeaders.querySelector('tr');
        if (headerRow) {
            sortableInstance = new Sortable(headerRow, {
                animation: 150,
                onEnd: function() {
                    const newOrder = getCurrentHeaderOrder();
                    reorderColumns(newOrder);
                }
            });
        }

        // Hide Steps 1–3 and show Step 4 (Preview Section)
        toggleInitialSteps(false);
        previewSection.style.display = 'block';
        updateHiddenInput();
    }

    // ===== Get current header order from DOM =====
    function getCurrentHeaderOrder() {
        const headers = previewHeaders.querySelectorAll('th');
        const order = [];
        headers.forEach(th => {
            const col = th.dataset.column;
            if (col) order.push(col);
        });
        return order;
    }

    // ===== Update hidden input with current header order =====
    function updateHiddenInput() {
        const order = getCurrentHeaderOrder();
        columnOrderInput.value = JSON.stringify(order);
    }

    // ===== Reorder columns and rows, then re‑render =====
    function reorderColumns(newOrder) {
        if (!previewData) return;

        previewData.columns = newOrder;

        const reorderedRows = previewData.rows.map(row => {
            const newRow = {};
            newOrder.forEach(col => {
                newRow[col] = row[col] !== undefined ? row[col] : '';
            });
            return newRow;
        });
        previewData.rows = reorderedRows;

        renderPreview(previewData);
    }

    // ===== Event: "Preview & Rearrange" button =====
    if (proceedBtn) {
        proceedBtn.addEventListener('click', function(e) {
            e.preventDefault();
            fetchPreview();
        });
    }

    // ===== Event: "Reset" button =====
    if (refreshBtn) {
        refreshBtn.addEventListener('click', function(e) {
            e.preventDefault();
            // Hide preview section and restore Steps 1–3
            previewSection.style.display = 'none';
            toggleInitialSteps(true);
        });
    }

    // ===== Event: "Export Now" button =====
    if (exportBtn) {
        exportBtn.addEventListener('click', function(e) {
            const order = columnOrderInput.value;
            if (!order || JSON.parse(order).length === 0) {
                e.preventDefault();
                e.stopPropagation();
                alert('No columns selected. Please rearrange or refresh the preview.');
                return false;
            }
        });
    }

});