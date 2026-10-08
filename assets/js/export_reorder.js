document.addEventListener('DOMContentLoaded', function() {

    const form = document.getElementById('exportForm');
    const checkboxes = document.querySelectorAll('input[name="columns[]"]');
    const previewSection = document.getElementById('preview-section');
    const previewHeaders = document.getElementById('preview-headers');
    const previewBody = document.getElementById('preview-body');
    const previewModal = document.getElementById('exportPreviewModal');
    const proceedBtn = document.getElementById('proceed-btn');
    const exportBtn = document.getElementById('export-btn');
    const refreshBtn = document.getElementById('refresh-preview');
    const columnOrderInput = document.getElementById('column_order');
    let sortableInstance = null;
    let previewData = null; // store the latest preview data

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
        const selected = getSelectedColumns();
        if (selected.length === 0) {
            previewSection.style.display = 'none';
            if (previewModal) previewModal.classList.remove('sys-show');
            return;
        }

        // Use FormData from form to pass ALL filters (school_year, semester, scholar_program, college, program, academic_status)
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
                    // After drag, update the column order and re‑render
                    const newOrder = getCurrentHeaderOrder();
                    reorderColumns(newOrder);
                }
            });
        }

        // Show preview section and update hidden input with initial order
        previewSection.style.display = 'block';
        updateHiddenInput();

        // Debug: log the order to console
        console.log('Column order set to:', columnOrderInput.value);
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

        // Update columns array
        previewData.columns = newOrder;

        // Reorder each row to match new column order
        const reorderedRows = previewData.rows.map(row => {
            const newRow = {};
            newOrder.forEach(col => {
                newRow[col] = row[col] !== undefined ? row[col] : '';
            });
            return newRow;
        });
        previewData.rows = reorderedRows;

        // Re‑render the table with the new order
        renderPreview(previewData);
    }

    // ===== Event: "Preview & Rearrange" button =====
    proceedBtn.addEventListener('click', function(e) {
        e.preventDefault();
        const selected = getSelectedColumns();
        if (selected.length === 0) {
            alert('Please select at least one column to export.');
            return;
        }
        fetchPreview();
    });

    // ===== Event: "Refresh Preview" button =====
    refreshBtn.addEventListener('click', function(e) {
        e.preventDefault();
        fetchPreview();
    });

    // ===== Event: "Export Now" button =====
    exportBtn.addEventListener('click', function(e) {
        e.preventDefault();
        const order = columnOrderInput.value;
        if (!order || JSON.parse(order).length === 0) {
            alert('No columns selected. Please rearrange or refresh the preview.');
            return;
        }
        // Debug: log the order being submitted
        console.log('Submitting column order:', order);
        // Submit the form directly (the hidden input already contains the order)
        form.submit();
    });

});