document.addEventListener('DOMContentLoaded', function () {
    const statusSelect = document.getElementById('academic_status');
    const loaFieldsContainer = document.getElementById('loa_fields_container');
    
    const birthTextInput = document.getElementById('birth_date');
    const birthPickerInput = document.getElementById('birth_date_picker');
    const btnBirthPicker = document.getElementById('btn_birth_picker');

    const loaSemesterInput = document.getElementById('loa_semester');
    const loaSchoolYearInput = document.getElementById('loa_school_year');

    // Trigger Native Birth Date Picker On Icon Button Click
    if (btnBirthPicker && birthPickerInput) {
        btnBirthPicker.addEventListener('click', function() {
            if (birthPickerInput.showPicker) {
                birthPickerInput.showPicker();
            } else {
                birthPickerInput.focus();
            }
        });
    }

    // Sync Birth Date Picker Selection to Text Input (YYYY-MM-DD)
    if (birthPickerInput && birthTextInput) {
        birthPickerInput.addEventListener('change', function() {
            if (this.value) {
                birthTextInput.value = this.value;
            }
        });
        birthTextInput.addEventListener('change', function() {
            if (this.value) {
                birthPickerInput.value = this.value;
            }
        });
    }

    // Toggle LOA Fields Requirement
    function toggleLoaInput() {
        if (!statusSelect || !loaFieldsContainer) return;

        if (statusSelect.value === 'Leave of Absence') {
            loaFieldsContainer.style.display = 'flex';
            if (loaSemesterInput) loaSemesterInput.required = true;
            if (loaSchoolYearInput) loaSchoolYearInput.required = true;
        } else {
            loaFieldsContainer.style.display = 'none';
            if (loaSemesterInput) {
                loaSemesterInput.required = false;
                loaSemesterInput.value = '';
            }
            if (loaSchoolYearInput) {
                loaSchoolYearInput.required = false;
                loaSchoolYearInput.value = '';
            }
        }
    }

    if (statusSelect) {
        statusSelect.addEventListener('change', toggleLoaInput);
        toggleLoaInput(); // Initial execution on load
    }
});