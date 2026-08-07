$(document).ready(function() {

    // ===== COUNTRY TOGGLE =====
    $('#country').change(function() {
        var country = $(this).val();
        if(country === 'Philippines') {
            $('#philippines-address').show();
            $('#other-address').hide();
            $('#other-country-row').hide();
            $('#region, #province, #city, #barangay').prop('required', true);
            $('#other_address_line1, #other_city, #other_state, #other_zipcode, #other_country').prop('required', false);
        } else {
            $('#philippines-address').hide();
            $('#other-address').show();
            $('#other-country-row').show();
            $('#region, #province, #city, #barangay').prop('required', false);
            $('#other_address_line1, #other_city, #other_state, #other_zipcode, #other_country').prop('required', true);
        }
    });

    // ===== LOAD FUNCTIONS (with callbacks) =====
    function loadRegions(selected_id, callback) {
        $.ajax({
            url: '../functions/get_locations.php',
            type: 'POST',
            data: {type: 'regions'},
            dataType: 'json',
            success: function(data) {
                $('#region').empty().append('<option value="">Select Region</option>');
                $.each(data, function(key, value) {
                    var selected = (selected_id == value.id) ? 'selected' : '';
                    $('#region').append('<option value="'+ value.id +'" '+ selected +'>'+ value.name +'</option>');
                });
                if(callback) callback();
            }
        });
    }

    function loadProvinces(region_id, selected_id, callback) {
        if(!region_id) {
            $('#province').empty().append('<option value="">Select Province</option>');
            if(callback) callback();
            return;
        }
        $.ajax({
            url: '../functions/get_locations.php',
            type: 'POST',
            data: {type: 'provinces', parent_id: region_id},
            dataType: 'json',
            success: function(data) {
                $('#province').empty().append('<option value="">Select Province</option>');
                $.each(data, function(key, value) {
                    var selected = (selected_id == value.id) ? 'selected' : '';
                    $('#province').append('<option value="'+ value.id +'" '+ selected +'>'+ value.name +'</option>');
                });
                if(callback) callback();
            }
        });
    }

    function loadCities(province_id, selected_id, callback) {
        if(!province_id) {
            $('#city').empty().append('<option value="">Select City</option>');
            if(callback) callback();
            return;
        }
        $.ajax({
            url: '../functions/get_locations.php',
            type: 'POST',
            data: {type: 'cities', parent_id: province_id},
            dataType: 'json',
            success: function(data) {
                $('#city').empty().append('<option value="">Select City</option>');
                $.each(data, function(key, value) {
                    var selected = (selected_id == value.id) ? 'selected' : '';
                    $('#city').append('<option value="'+ value.id +'" '+ selected +'>'+ value.name +'</option>');
                });
                if(callback) callback();
            }
        });
    }

    function loadBarangays(city_id, selected_id, callback) {
        if(!city_id) {
            $('#barangay').empty().append('<option value="">Select Barangay</option>');
            if(callback) callback();
            return;
        }
        $.ajax({
            url: '../functions/get_locations.php',
            type: 'POST',
            data: {type: 'barangays', parent_id: city_id},
            dataType: 'json',
            success: function(data) {
                $('#barangay').empty().append('<option value="">Select Barangay</option>');
                $.each(data, function(key, value) {
                    var selected = (selected_id == value.id) ? 'selected' : '';
                    $('#barangay').append('<option value="'+ value.id +'" '+ selected +'>'+ value.name +'</option>');
                });
                if(callback) callback();
            }
        });
    }

    // ===== EVENT HANDLERS =====
    $('#region').change(function() {
        var region_id = $(this).val();
        if(region_id) {
            loadProvinces(region_id);
        } else {
            $('#province').empty().append('<option value="">Select Province</option>');
            $('#city').empty().append('<option value="">Select City</option>');
            $('#barangay').empty().append('<option value="">Select Barangay</option>');
        }
    });

    $('#province').change(function() {
        var province_id = $(this).val();
        if(province_id) {
            loadCities(province_id);
        } else {
            $('#city').empty().append('<option value="">Select City</option>');
            $('#barangay').empty().append('<option value="">Select Barangay</option>');
        }
    });

    $('#city').change(function() {
        var city_id = $(this).val();
        if(city_id) {
            loadBarangays(city_id);
        } else {
            $('#barangay').empty().append('<option value="">Select Barangay</option>');
        }
    });

    // ===== INITIALIZE WITH PRE-SELECTED VALUES =====
    var selected_region = $('#region').data('selected') || '';
    var selected_province = $('#province').data('selected') || '';
    var selected_city = $('#city').data('selected') || '';
    var selected_barangay = $('#barangay').data('selected') || '';

    // Load regions, then chain the rest
    loadRegions(selected_region, function() {
        if(selected_province) {
            loadProvinces(selected_region, selected_province, function() {
                if(selected_city) {
                    loadCities(selected_province, selected_city, function() {
                        if(selected_barangay) {
                            loadBarangays(selected_city, selected_barangay);
                        }
                    });
                }
            });
        }
    });

    // Trigger country toggle on page load
    $('#country').trigger('change');
});