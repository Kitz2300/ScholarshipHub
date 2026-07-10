<?php 

function validateStudentId($id, $conn) {
    $errors = [];
    
    // Check if empty
    if (empty($id)) {
        $errors[] = "Student ID is required.";
        return $errors;
    }
    
    // Check format: must be like 23-0223-403
    if (!preg_match('/^\d{2}-\d{4}-\d{3}$/', $id)) {
        $errors[] = "Student ID must be in format: 23-0223-403";
        return $errors;
    }
    
    // Check duplicate in database
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

    // Check if empty
    if (empty($value)) {
        $errors[] = "$fieldName is required.";
        return $errors;
    }

    
    // Check if contains only letters, spaces, hyphens, apostrophes, periods
    if (!preg_match('/^[A-Za-z\s\-\.\']+$/', $value)) {
        $errors[] = "$fieldName contains invalid characters. Use only letters, spaces, or hyphens.";
    }
    return $errors;
    
}

function validateExtName($value) {
    $errors = [];

    if(empty($value)) {
            return $errors;
    }

    if(!preg_match('/^[A-Za-z\s\.]+$/', $value)) {
        $errors[] = "Extension Name contains invalid characters. Use only letters, spaces, or periods.";
    }
    return $errors;
}

function validateParentName($fieldName, $value) {
    $errors = [];

    if(empty($value)) {
        return $errors;
    }

    if(!preg_match('/^[A-Za-z\s\-\.\']+$/', $value)) {
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
    
    // Convert to integer if it's a string
    $value = (int)$value;
    
    if (!in_array($value, [0, 1])) {
        $errors[] = "Sex must be 0 (Male) or 1 (Female).";
    }
    return $errors;
    
}

function validateBirthdate($value) {
    $errors = [];
    
    if (empty($value)) {
        $errors[] = "Birthdate is required.";
        return $errors;
    }
    
    // Try to parse the date
    $date = DateTime::createFromFormat('Y-m-d', $value);
    if (!$date || $date->format('Y-m-d') !== $value) {
        $errors[] = "Birthdate must be in YYYY-MM-DD format.";
        return $errors;
    }
    
    // Check if date is in the future
    if ($date->getTimestamp() > time()) {
        $errors[] = "Birthdate cannot be in the future.";
    }
    return $errors;
    
}

function validateProgram($value, $conn) {
    $errors = [];
   
    // check if empty
    if($value == "" || $value == null) {
        $errors[] = "Program is required";
        return $errors;
    }

    // check if program exists in the db
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

function validatePermanentAddress($value) {
    $errors = [];

    if(empty($value)) {
        return $errors;
    }

    if (!preg_match('/^[A-Za-z\s\.\']+$/', $value)) {
        $errors[] = "Permanent Address contains invalid characters. Use only letters, spaces, ., '";
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

function validateFullRow($rowData, $conn) {
    $allErrors = [];
    
    // Map your Excel columns to field names (adjust indexes as needed)
    // Example: column 0 = student_id, column 1 = last_name, etc.
    $student_id = $rowData[1] ?? '';
    $last_name = $rowData[2] ?? '';
    $given_name = $rowData[3] ?? '';
    $ext_name = $rowData[4] ?? '';
    $middle_name = $rowData[5] ?? '';
    $sex = $rowData[6] ?? '';
    $birthdate = $rowData[7] ?? '';
    $program = $rowData[8] ?? '';
    $year_lvl = $rowData[9] ?? '';
    $father_last_name = $rowData[10] ?? '';
    $father_given_name = $rowData[11] ?? '';
    $father_middle_name = $rowData[12] ?? '';
    $mother_last_name = $rowData[13] ?? '';
    $mother_given_name = $rowData[14] ?? '';
    $mother_middle_name = $rowData[15] ?? '';
    $permanent_address = $rowData[16] ?? '';
    $zipcode = $rowData[17] ?? '';
    $disability = $rowData[18] ?? '';
    $contact_num = $rowData[19] ?? '';
    $email = $rowData[20] ?? '';
    $ip_group = $rowData[21] ?? '';
    
    // Run each validation
    $allErrors['student_id'] = validateStudentId($student_id, $conn);
    $allErrors['last_name'] = validateName('Last Name', $last_name);
    $allErrors['given_name'] = validateName('Given Name', $given_name);
    $allErrors['ext_name'] = validateExtName($ext_name);
    $allErrors['middle_name'] = validateName('Middle Name', $middle_name);
    $allErrors['father_last_name'] = validateParentName('Father Last Name', $father_last_name);
    $allErrors['father_given_name'] = validateParentName('Father Given Name', $father_given_name);
    $allErrors['father_middle_name'] = validateParentName('Father Middle Name', $father_middle_name);
    $allErrors['mother_last_name'] = validateParentName('Mother Last Name', $mother_last_name);
    $allErrors['mother_given_name'] = validateParentName('Mother Given Name', $mother_given_name);
    $allErrors['mother_middle_name'] = validateParentName('Mother Middle Name', $mother_middle_name);
    $allErrors['sex'] = validateSex($sex);
    $allErrors['birthdate'] = validateBirthdate($birthdate);
    $allErrors['program'] = validateProgram($program, $conn);
    $allErrors['year_level'] = validateYearLevel($year_lvl);
    $allErrors['permanent_address'] = validatePermanentAddress($permanent_address);
    $allErrors['zipcode'] = validateZipcode($zipcode);
    $allErrors['disability'] = validateDisability($disability);
    $allErrors['email'] = validateEmail($email);
    $allErrors['contact_num'] = validateContactNumber($contact_num);
    $allErrors['ip_group'] = validateIPgroup($ip_group);
    
    // Remove fields that have no errors (keep only fields with errors)
    $allErrors = array_filter($allErrors, function($fieldErrors) {
        return !empty($fieldErrors);
    });
    
    return $allErrors;
}

?>