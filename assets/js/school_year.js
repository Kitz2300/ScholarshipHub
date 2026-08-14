function updateEndYear() {
    const startSelect = document.getElementById('start_year');
    const endDisplay = document.getElementById('end_year_display');
    const endHidden = document.getElementById('end_year_hidden');
    
    if (!startSelect || !endDisplay || !endHidden) return;
    
    const startYear = parseInt(startSelect.value);
    if (isNaN(startYear)) return;
    
    const endYear = startYear + 1;
    
    // Update both display and hidden value
    endDisplay.value = endYear;
    endHidden.value = endYear;
}

document.addEventListener('DOMContentLoaded', function() {
    updateEndYear();
    
    const startSelect = document.getElementById('start_year');
    if (startSelect) {
        startSelect.addEventListener('change', updateEndYear);
    }
});