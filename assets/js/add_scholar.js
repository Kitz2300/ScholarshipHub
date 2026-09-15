document.addEventListener('DOMContentLoaded', function () {
                const statusSelect = document.getElementById('academic_status');
                const loaFieldsContainer = document.getElementById('loa_fields_container');
                
                const birthTextInput = document.getElementById('birth_date');
                const birthPickerInput = document.getElementById('birth_date_picker');
                const btnBirthPicker = document.getElementById('btn_birth_picker');

                const loaTextInput = document.getElementById('loa_date');
                const loaSemesterInput = document.getElementById('loa_semester');
                const loaSchoolYearInput = document.getElementById('loa_school_year');
                const loaPickerInput = document.getElementById('loa_date_picker');
                const btnLoaPicker = document.getElementById('btn_loa_picker');

                // Get Today's Date in YYYY-MM-DD
                const today = new Date();
                const yyyy = today.getFullYear();
                const mm = String(today.getMonth() + 1).padStart(2, '0');
                const dd = String(today.getDate()).padStart(2, '0');
                const todayStr = `${yyyy}-${mm}-${dd}`;

                // Trigger Native Pickers On Icon Button Click
                btnBirthPicker.addEventListener('click', function() {
                    if (birthPickerInput.showPicker) {
                        birthPickerInput.showPicker();
                    } else {
                        birthPickerInput.focus();
                    }
                });

                btnLoaPicker.addEventListener('click', function() {
                    if (loaPickerInput.showPicker) {
                        loaPickerInput.showPicker();
                    } else {
                        loaPickerInput.focus();
                    }
                });

                // Sync Picker Selection to Text Input (YYYY-MM-DD)
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

                loaPickerInput.addEventListener('change', function() {
                    if (this.value) {
                        loaTextInput.value = this.value;
                    }
                });
                loaTextInput.addEventListener('change', function() {
                    if (this.value) {
                        loaPickerInput.value = this.value;
                    }
                });

                // Toggle LOA Fields Requirement
                function toggleLoaInput() {
                    if (statusSelect.value === 'Leave of Absence') {
                        loaFieldsContainer.style.display = 'flex';
                        loaTextInput.required = true;
                        loaSemesterInput.required = true;
                        loaSchoolYearInput.required = true;
                    } else {
                        loaFieldsContainer.style.display = 'none';
                        loaTextInput.required = false;
                        loaSemesterInput.required = false;
                        loaSchoolYearInput.required = false;

                        loaTextInput.value = '';
                        loaSemesterInput.value = '';
                        loaSchoolYearInput.value = '';
                        loaPickerInput.value = '';
                    }
                }

                statusSelect.addEventListener('change', toggleLoaInput);

                // Quick "Today" Button Action
                document.getElementById('btn_loa_today').addEventListener('click', function() {
                    loaTextInput.value = todayStr;
                    loaPickerInput.value = todayStr;
                });
            });