<?php
require_once __DIR__ . "/../../includes/address_helper.php";

// ─── Core Validation Functions ───

function validateStudentId($id, $conn) {
    $errors = [];
    if (empty($id)) {
        $errors[] = "Student ID is required.";
        return $errors;
    }
    if (!preg_match('/^\d{2}-\d{4}-\d{3}$/', $id)) {
        $errors[] = "Student ID must be in format: 12-1234-123";
        return $errors;
    }
    $sql = "SELECT student_id FROM scholar_info WHERE student_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $errors[] = "Student ID '$id' already exists in the database.";
    }
    return $errors;
}

function validateName($fieldName, $value) {
    $errors = [];
    if (empty($value)) {
        $errors[] = "$fieldName is required.";
        return $errors;
    }
    if (!preg_match('/^[A-Za-zñÑ\s\-\.\']+$/', $value)) {
        $errors[] = "$fieldName contains invalid characters. Use only letters, spaces, or hyphens.";
    }
    return $errors;
}

function validateExtName($value) {
    $errors = [];
    if(empty($value)) {
        return $errors;
    }
    if(!preg_match('/^[A-Za-zñÑ\s\.]+$/', $value)) {
        $errors[] = "Extension Name contains invalid characters. Use only letters, spaces, or periods.";
    }
    return $errors;
}

function validateParentName($fieldName, $value) {
    $errors = [];
    if(empty($value)) {
        return $errors;
    }
    if(!preg_match('/^[A-Za-zñÑ\s\-\.\']+$/', $value)) {
        $errors[] = "$fieldName contains invalid characters. Use only letters, spaces, or hyphens.";
    }
    return $errors;
}

function validateSex($value) {
    $errors = [];
    if ($value === '' || $value === null) {
        $errors[] = "Sex is required.";
        return $errors;
    }
    if (is_numeric($value)) {
        $value = (int)$value;
        if (!in_array($value, [0, 1])) {
            $errors[] = "Sex must be 0 (Male) or 1 (Female).";
        }
    } else {
        $value = trim($value);
        if (!in_array(strtolower($value), ['male', 'female'])) {
            $errors[] = "Sex must be 'Male' or 'Female'.";
        }
    }
    return $errors;
}

function validateBirthdate($value) {
    $errors = [];
    if (empty($value)) {
        $errors[] = "Birthdate is required.";
        return $errors;
    }
    $date = DateTime::createFromFormat('Y-m-d', $value);
    if (!$date || $date->format('Y-m-d') !== $value) {
        $errors[] = "Birthdate must be in YYYY-MM-DD format.";
        return $errors;
    }
    if ($date->getTimestamp() > time()) {
        $errors[] = "Birthdate cannot be in the future.";
    }
    return $errors;
}

function validateProgram($value, $conn) {
    $errors = [];
    if($value == "" || $value == null) {
        $errors[] = "Program is required";
        return $errors;
    }
    $sql = "SELECT name FROM program WHERE name = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $value);
    $stmt->execute();
    $result = $stmt->get_result();
    if($result->num_rows == 0) {
        $errors[] = "The program '$value' does not exist in this school";
    }
    return $errors;
}

function validateYearLevel($value) {
    $errors = [];
    if ($value === '' || $value === null) {
        $errors[] = "Year Level is required.";
        return $errors;
    }
    if (!is_numeric($value) || $value < 1 || $value > 6) {
        $errors[] = "Year Level must be a number between 1 and 6.";
    }
    return $errors;
}

function validateStreet($value) {
    $errors = [];
    if (empty($value)) {
        $errors[] = "Street & Barangay is required.";
        return $errors;
    }
    if (!preg_match('/^[0-9A-Za-zñÑ\s\.\,\'\-]+$/', $value)) {
        $errors[] = "Street contains invalid characters.";
    }
    return $errors;
}

function validateTownCity($value) {
    $errors = [];
    if(empty($value)) {
        return $errors;
    }
    if (!preg_match('/^[A-Za-zñÑ\s\.\,\'\-]+$/', $value)) {
        $errors[] = "Town/City contains invalid characters.";
    }
    return $errors;
}

function validateProvince($value) {
    $errors = [];
    if(empty($value)) {
        return $errors;
    }
    if (!preg_match('/^[A-Za-zñÑ\s\.\,\']+$/', $value)) {
        $errors[] = "Province contains invalid characters.";
    }
    return $errors;
}

function validateZipcode($value) {
    $errors = [];
    if(empty($value)) {
        return $errors;
    }
    if(!ctype_digit($value)) {
        $errors[] = "Zipcode must contain only digits.";
    }
    return $errors;
}

function validateDisability($value) {
    $errors = [];
    if(empty($value)) {
        return $errors;
    }
    if(!preg_match('/^[A-Za-z0-9\s\-\.\,\(\)\/]+$/', $value)) {
        $errors[] = "Disability contains invalid characters. Use only letters, spaces, commas, periods, parentheses, or hyphens.";
    }
    return $errors;
}

function validateEmail($value) {
    $errors = [];
    $value = str_replace(' ', '', trim($value));
    
    if(empty($value)) {
        return $errors;
    }
    if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Email format is invalid.";
    }   
    return $errors;
}

function validateContactNumber($value) {
    $errors = [];
    if(empty($value)) {
        return $errors;
    }
    if (!ctype_digit($value)) {
        $errors[] = "Contact Number must contain only digits.";
        return $errors;
    }
    return $errors;
}

function validateIPgroup($value) {
    $errors = [];
    if(empty($value)) {
        return $errors;
    }
    if(!preg_match('/^[A-Za-z0-9\s\-\.\,\(\)\/]+$/', $value)) {
        $errors[] = "IP Group contains invalid characters. Use only letters, spaces, commas, periods, parentheses, or hyphens.";
    }
    return $errors;
}

// ─── New optional field validations ───
function validatePhilsysId($value) {
    $errors = [];
    if(empty($value)) {
        return $errors;
    }
    if(!preg_match('/^[A-Za-z0-9\-]+$/', $value)) {
        $errors[] = "PHILSYS ID contains invalid characters.";
    }
    return $errors;
}

function validateLrn($value) {
    $errors = [];
    if(empty($value)) {
        return $errors;
    }
    if(!ctype_digit($value)) {
        $errors[] = "LRN must contain only digits.";
    }
    return $errors;
}

function validateFourPsId($value) {
    $errors = [];
    if(empty($value)) {
        return $errors;
    }
    if(!preg_match('/^[A-Za-z0-9\-\/]+$/', $value)) {
        $errors[] = "4P's ID contains invalid characters.";
    }
    return $errors;
}

// ─── Main validation using dynamic mapping ───
function validateFullRow($rowData, $conn, $mapped_columns) {
    $allErrors = [];
    
    // Build the data array using the mapping
    $data = [];
    foreach($mapped_columns as $field => $index) {
        $data[$field] = $rowData[$index] ?? '';
    }
    
    // ─── Required validations ───
    if(isset($data['student_id'])) {
        $errors = validateStudentId($data['student_id'], $conn);
        if(!empty($errors)) $allErrors['student_id'] = $errors;
    }
    
    if(isset($data['last_name'])) {
        $errors = validateName('Last Name', $data['last_name']);
        if(!empty($errors)) $allErrors['last_name'] = $errors;
    }
    
    if(isset($data['given_name'])) {
        $errors = validateName('Given Name', $data['given_name']);
        if(!empty($errors)) $allErrors['given_name'] = $errors;
    }
    
    if(isset($data['middle_name'])) {
        $errors = validateName('Middle Name', $data['middle_name']);
        if(!empty($errors)) $allErrors['middle_name'] = $errors;
    }
    
    if(isset($data['sex'])) {
        $errors = validateSex($data['sex']);
        if(!empty($errors)) $allErrors['sex'] = $errors;
    }
    
    if(isset($data['birthdate'])) {
        $errors = validateBirthdate($data['birthdate']);
        if(!empty($errors)) $allErrors['birthdate'] = $errors;
    }
    
    if(isset($data['program'])) {
        $errors = validateProgram($data['program'], $conn);
        if(!empty($errors)) $allErrors['program'] = $errors;
    }
    
    if(isset($data['year_level'])) {
        $errors = validateYearLevel($data['year_level']);
        if(!empty($errors)) $allErrors['year_level'] = $errors;
    }
    
    if(isset($data['street'])) {
        $errors = validateStreet($data['street']);
        if(!empty($errors)) $allErrors['street'] = $errors;
    }
    
    // ─── Optional validations ───
    if(isset($data['ext_name']) && !empty($data['ext_name'])) {
        $errors = validateExtName($data['ext_name']);
        if(!empty($errors)) $allErrors['ext_name'] = $errors;
    }
    
    if(isset($data['father_last']) && !empty($data['father_last'])) {
        $errors = validateParentName("Father's Last Name", $data['father_last']);
        if(!empty($errors)) $allErrors['father_last'] = $errors;
    }
    if(isset($data['father_given']) && !empty($data['father_given'])) {
        $errors = validateParentName("Father's Given Name", $data['father_given']);
        if(!empty($errors)) $allErrors['father_given'] = $errors;
    }
    if(isset($data['father_middle']) && !empty($data['father_middle'])) {
        $errors = validateParentName("Father's Middle Name", $data['father_middle']);
        if(!empty($errors)) $allErrors['father_middle'] = $errors;
    }
    if(isset($data['mother_last']) && !empty($data['mother_last'])) {
        $errors = validateParentName("Mother's Last Name", $data['mother_last']);
        if(!empty($errors)) $allErrors['mother_last'] = $errors;
    }
    if(isset($data['mother_given']) && !empty($data['mother_given'])) {
        $errors = validateParentName("Mother's Given Name", $data['mother_given']);
        if(!empty($errors)) $allErrors['mother_given'] = $errors;
    }
    if(isset($data['mother_middle']) && !empty($data['mother_middle'])) {
        $errors = validateParentName("Mother's Middle Name", $data['mother_middle']);
        if(!empty($errors)) $allErrors['mother_middle'] = $errors;
    }
    if(isset($data['town_city']) && !empty($data['town_city'])) {
        $errors = validateTownCity($data['town_city']);
        if(!empty($errors)) $allErrors['town_city'] = $errors;
    }
    if(isset($data['province']) && !empty($data['province'])) {
        $errors = validateProvince($data['province']);
        if(!empty($errors)) $allErrors['province'] = $errors;
    }
    if(isset($data['zipcode']) && !empty($data['zipcode'])) {
        $errors = validateZipcode($data['zipcode']);
        if(!empty($errors)) $allErrors['zipcode'] = $errors;
    }
    if(isset($data['disability']) && !empty($data['disability'])) {
        $errors = validateDisability($data['disability']);
        if(!empty($errors)) $allErrors['disability'] = $errors;
    }
    if(isset($data['contact_num']) && !empty($data['contact_num'])) {
        $errors = validateContactNumber($data['contact_num']);
        if(!empty($errors)) $allErrors['contact_num'] = $errors;
    }
    if(isset($data['email']) && !empty($data['email'])) {
        $errors = validateEmail($data['email']);
        if(!empty($errors)) $allErrors['email'] = $errors;
    }
    if(isset($data['ip_group']) && !empty($data['ip_group'])) {
        $errors = validateIPgroup($data['ip_group']);
        if(!empty($errors)) $allErrors['ip_group'] = $errors;
    }
    if(isset($data['philsys_id']) && !empty($data['philsys_id'])) {
        $errors = validatePhilsysId($data['philsys_id']);
        if(!empty($errors)) $allErrors['philsys_id'] = $errors;
    }
    if(isset($data['lrn']) && !empty($data['lrn'])) {
        $errors = validateLrn($data['lrn']);
        if(!empty($errors)) $allErrors['lrn'] = $errors;
    }
    if(isset($data['four_ps_id']) && !empty($data['four_ps_id'])) {
        $errors = validateFourPsId($data['four_ps_id']);
        if(!empty($errors)) $allErrors['four_ps_id'] = $errors;
    }

    // ─── Address lookup validations ───
    if(isset($data['province']) && !empty($data['province'])) {
        $province_data = getProvinceId($conn, $data['province']);
        if (!$province_data) {
            $allErrors['province'] = ['Province "' . $data['province'] . '" not found in the PSGC database.'];
        } else {
            if(isset($data['town_city']) && !empty($data['town_city'])) {
                $city_id = getCityId($conn, $data['town_city'], $province_data['id']);
                if (!$city_id) {
                    $allErrors['town_city'] = ['Town/City "' . $data['town_city'] . '" not found in the PSGC database.'];
                }
            }
        }
    }

    // Remove fields that have no errors
    $allErrors = array_filter($allErrors, function($fieldErrors) {
        return !empty($fieldErrors);
    });
    
    return $allErrors;
}