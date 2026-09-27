<?php 
include __DIR__ . '/../includes/conn.php';
include __DIR__ . '/../includes/check_permission.php';
session_start();

// get scholar id
$scholar_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if(!hasPermission($conn, $_SESSION['user_id'], 'scholars', 'edit')) {
    header("Location: scholar_profile.php?scholar_id=" . $scholar_id);
    exit();
}

// scholar info query
$scholar_sql = "SELECT * FROM scholar_info WHERE id = ?";
$scholar_stmt = $conn->prepare($scholar_sql);
$scholar_stmt->bind_param("i", $scholar_id);
$scholar_stmt->execute();
$scholar_result = $scholar_stmt->get_result();

// initializing variables
$last_name = "";
$given_name = "";
$ext_name = "";
$middle_name = "";
$id_num = "";
$sex = 0;
$birthdate = "";
$scholarship_program = 0;
$program = 0;
$year_lvl = 0;
$academic_status = "";
$loa_semester = "";
$loa_school_year = "";
$address = "";
$zipcode = "";
$disability = "";
$contact_num = "";
$email = "";
$ip_group = "";
$is_child_of_solo_parent = 0;
$father_last = "";
$father_given = "";
$father_middle = "";
$father_contact = "";
$mother_last = "";
$mother_given = "";
$mother_middle = "";
$mother_contact = "";
$street = "";
$barangay_id = 0;
$city_id = 0;
$province_id = 0;
$region_id = 0;
$country = "";
$philsys_id = "";
$lrn = "";
$four_ps_id = "";

// insert values to initialized variables
if($row = $scholar_result->fetch_assoc()) {
    $last_name = $row['last_name'];
    $given_name = $row['given_name'];
    $ext_name = $row['ext_name'] === "N/A" ? "Not Applicable" : $row['ext_name'];
    $middle_name = $row['middle_name'];
    $id_num = $row['student_id'];
    $sex = $row['sex'];
    
    // Format strictly as YYYY-MM-DD
    $birthdate = (!empty($row['birthdate']) && $row['birthdate'] !== '0000-00-00') ? date('Y-m-d', strtotime($row['birthdate'])) : '';
    
    $scholarship_program = $row['scholarship_program_id'];
    $program = $row['program_id'];
    $year_lvl = $row['year_level'];
    $academic_status = $row['academic_status'];
    
    $loa_semester = $row['loa_semester'] ?? '';
    $loa_school_year = $row['loa_school_year'] ?? '';
    
    $address = $row['permanent_address'];
    $zipcode = $row['zipcode'];
    $disability = $row['disability'];
    $contact_num = $row['contact_num'];
    $email = $row['email'];
    $ip_group = $row['ip_group'];
    $is_child_of_solo_parent = (int) ($row['is_child_of_solo_parent'] ?? 0);
    $father_last = $row['father_last_name'];
    $father_given = $row['father_given_name'];
    $father_middle = $row['father_middle_name'];
    $father_contact = $row['father_contact'] ?? '';
    $mother_last = $row['mother_last_name'];
    $mother_given = $row['mother_given_name'];
    $mother_middle = $row['mother_middle_name'];
    $mother_contact = $row['mother_contact'] ?? '';
    $street = $row['street'] ?? '';
    $barangay_id = $row['barangay_id'] ?? 0;
    $city_id = $row['city_id'] ?? 0;
    $province_id = $row['province_id'] ?? 0;
    $region_id = $row['region_id'] ?? 0;
    $country = $row['country'] ?? 'Philippines';
    $zipcode = $row['zipcode'] ?? '';
    $philsys_id = $row['philsys_id'] ?? '';
    $lrn = $row['lrn'] ?? '';
    $four_ps_id = $row['four_ps_id'] ?? '';
    
} else {
    $_SESSION['fail'] = "Scholar not available";
    header("Location: scholar_list.php");
    exit();
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Edit Scholar - PIT ScholarshipHub</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" href="../assets/images/PIT%20LOGO.png">
    <link rel="stylesheet" href="../assets/css/form.css">
    <link rel="stylesheet" href="../assets/css/modals.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="../assets/js/address.js"></script>
    <script src="../assets/js/disable_button.js"></script>
</head>
<body>
    <?php include __DIR__ . "/../includes/modal_alerts.php"; ?>
    <?php if(!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') : 
        $_SESSION['fail'] = "You are no logged in!";
        header("Location: ../portal.php");
        exit();    
    else : ?>
        <div class="container">

            <?php include __DIR__ . "/../includes/navigation.php"; ?>

            <!-- Page Content -->
            <div class="page-content">
                <!-- Page Header -->
                <div class="page-header">
                    <h2>Edit Scholar</h2>
                    <div class="page-actions">
                        <a href="scholar_profile.php?scholar_id=<?= $scholar_id ?>" class="btn btn-secondary">Back to Profile</a>
                    </div>
                </div>

                <form action="../functions/admin/edit_scholar_process.php" method="POST" class="reusable-form">
                    <input type="hidden" name="scholar_id" value="<?= $scholar_id ?>">

                    <!-- Student Information -->
                    <div class="form-section">
                        <h3>Student Information</h3>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="last_name">Last Name <span class="required">*</span></label>
                                <input type="text" name="last_name" id="last_name" value="<?= htmlspecialchars($last_name) ?>" required>
                            </div>
                            <div class="form-group">
                                <label for="first_name">First Name <span class="required">*</span></label>
                                <input type="text" name="first_name" id="first_name" value="<?= htmlspecialchars($given_name) ?>" required>
                            </div>
                            <div class="form-group">
                                <label for="middle_name">Middle Name</label>
                                <input type="text" name="middle_name" id="middle_name" value="<?= htmlspecialchars($middle_name) ?>">
                            </div>
                            <div class="form-group">
                                <label for="ext_name">Extension Name</label>
                                <select name="ext_name" id="ext_name" required>
                                    <option value="N/A" <?= ($ext_name == 'N/A' || $ext_name == 'Not Applicable') ? 'selected' : '' ?>>N/A</option>
                                    <option value="Jr." <?= ($ext_name == 'Jr.') ? 'selected' : '' ?>>Jr.</option>
                                    <option value="Sr." <?= ($ext_name == 'Sr.') ? 'selected' : '' ?>>Sr.</option>
                                    <option value="II" <?= ($ext_name == 'II') ? 'selected' : '' ?>>II</option>
                                    <option value="III" <?= ($ext_name == 'III') ? 'selected' : '' ?>>III</option>
                                </select>
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="email">Email Address <span class="required">*</span></label>
                                <input type="email" name="email" id="email" value="<?= htmlspecialchars($email) ?>" pattern="[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}" required>
                            </div>
                            <div class="form-group">
                                <label for="student_id">Student ID <span class="required">*</span></label>
                                <input type="text" name="student_id" id="student_id" value="<?= htmlspecialchars($id_num) ?>" placeholder="12-3456-789" pattern="[0-9]{2}-[0-9]{4}-[0-9]{3}" title="e.g. 12-3456-789" required>
                            </div>
                            <div class="form-group">
                                <label for="student_contact">Contact Number <span class="required">*</span></label>
                                <input type="text" name="student_contact" id="student_contact" value="<?= htmlspecialchars($contact_num) ?>" placeholder="09171234567" pattern="09[0-9]{9}" required>
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="philsys_id">PHILSYS ID <span class="required">*</span></label>
                                <input type="text" name="philsys_id" id="philsys_id" value="<?= htmlspecialchars($philsys_id) ?>" placeholder="e.g., 4583-2739-5423-7829" required>
                            </div>
                            <div class="form-group">
                                <label for="lrn">LRN <span class="required">*</span></label>
                                <input type="text" name="lrn" id="lrn" value="<?= htmlspecialchars($lrn) ?>" placeholder="e.g., 121799100006" required>
                            </div>
                            <div class="form-group">
                                <label for="four_ps_id">4P's ID</label>
                                <input type="text" name="four_ps_id" id="four_ps_id" value="<?= htmlspecialchars($four_ps_id) ?>" placeholder="e.g., 083740027-6222-00003">
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="sex">Sex <span class="required">*</span></label>
                                <select name="sex" id="sex" required>
                                    <option value="Male" <?= ($sex == 0) ? 'selected' : '' ?>>Male</option>
                                    <option value="Female" <?= ($sex == 1) ? 'selected' : '' ?>>Female</option>
                                </select>
                            </div>

                            <!-- Birth Date Field with Clean Hidden Picker -->
                            <div class="form-group">
                                <label for="birth_date">Birth Date (YYYY-MM-DD) <span class="required">*</span></label>
                                <div style="display: flex; gap: 6px; align-items: center; position: relative;">
                                    <input type="text" name="birth_date" id="birth_date" value="<?= htmlspecialchars($birthdate) ?>" placeholder="YYYY-MM-DD" pattern="\d{4}-(0[1-9]|1[0-2])-(0[1-9]|[12][0-9]|3[01])" required style="flex: 1;">
                                    <button type="button" id="btn_birth_picker" class="btn btn-secondary" style="padding: 8px 12px; font-size: 16px; cursor: pointer; background: #ffff; border: 1px solid black;" title="Select Birth Date">📅</button>
                                    <input type="date" id="birth_date_picker" value="<?= htmlspecialchars($birthdate) ?>" style="position: absolute; opacity: 0; pointer-events: none; width: 0; height: 0;">
                                </div>
                            </div>

                            <div class="form-group">
                                <label for="disability">Disability</label>
                                <input type="text" name="disability" id="disability" placeholder="e.g., Visual impairment, Dyslexia" value="<?= htmlspecialchars($disability) ?>" list="disability-list"/>
                                <datalist id="disability-list">
                                    <option value="Not applicable"></option>
                                    <option value="Visual Impairment"></option>
                                    <option value="Hearing Impairment"></option>
                                    <option value="Mobility Impairment"></option>
                                    <option value="Cognitive Impairment"></option>
                                </datalist>
                            </div>
                        </div>

                        <!-- IP Group Aligned with Checkbox -->
                        <div class="form-row">
                            <div class="form-group">
                                <label for="ip_group">IP Group</label>
                                <input type="text" name="ip_group" id="ip_group" placeholder="e.g., Ifugao, Tagbanwa" value="<?= htmlspecialchars($ip_group) ?>" list="ip-group-list"/>
                                <datalist id="ip-group-list">
                                    <option value="Not applicable"></option>
                                    <option value="Ifugao"></option>
                                    <option value="Tagbanwa"></option>
                                    <option value="Maranao"></option>
                                    <option value="B'laan"></option>
                                    <option value="Teduray"></option>
                                </datalist>
                            </div>
                            
                            <div class="form-group checkbox-group" style="display: flex; align-items: center; gap: 8px; padding-top: 25px;">
                                <input type="checkbox" name="is_child_of_solo_parent" id="is_child_of_solo_parent" value="1" <?= $is_child_of_solo_parent === 1 ? 'checked' : '' ?> style="width: 18px; height: 18px; cursor: pointer; accent-color: #1e4628;">
                                <label for="is_child_of_solo_parent" style="cursor: pointer; font-weight: bold; margin-bottom: 0;">Child of a Solo Parent</label>
                            </div>
                        </div>
                    </div>

                    <!-- Address Section -->
                    <div class="form-section">
                        <h3>Address</h3>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="country">Country <span class="required">*</span></label>
                                <select name="country" id="country">
                                    <option value="Philippines" <?= $country == 'Philippines' ? 'selected' : '' ?>>Philippines</option>
                                    <option value="Other" <?= $country != 'Philippines' ? 'selected' : '' ?>>Other</option>
                                </select>
                            </div>
                        </div>

                        <div id="other-country-row" style="<?= $country != 'Philippines' ? 'display: block;' : 'display: none;' ?>">
                            <div class="form-group">
                                <label for="other_country">Country Name <span class="required">*</span></label>
                                <input type="text" name="other_country" id="other_country" value="<?= $country != 'Philippines' ? htmlspecialchars($country) : '' ?>" placeholder="Enter country name">
                            </div>
                        </div>

                        <div id="philippines-address" style="<?= $country == 'Philippines' ? 'display: block;' : 'display: none;' ?>">
                            <div class="form-row">
                                <div class="form-group full-width">
                                    <label for="street">Street / Building / Unit <span class="required">*</span></label>
                                    <input type="text" name="street" id="street" value="<?= htmlspecialchars($street) ?>" placeholder="e.g., 123 Main St, Bldg. A">
                                </div>
                            </div>
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="region">Region <span class="required">*</span></label>
                                    <select name="region" id="region" data-selected="<?= $region_id ?>" required>
                                        <option value="">Select Region</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label for="province">Province <span class="required">*</span></label>
                                    <select name="province" id="province" data-selected="<?= $province_id ?>" required>
                                        <option value="">Select Province</option>
                                    </select>
                                </div>
                            </div>
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="city">City / Municipality <span class="required">*</span></label>
                                    <select name="city" id="city" data-selected="<?= $city_id ?>" required>
                                        <option value="">Select City</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label for="barangay">Barangay <span class="required">*</span></label>
                                    <select name="barangay" id="barangay" data-selected="<?= $barangay_id ?>" required>
                                        <option value="">Select Barangay</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label for="zipcode">Zipcode</label>
                                    <input type="text" name="zipcode" id="zipcode" value="<?= htmlspecialchars($zipcode) ?>" placeholder="Zipcode">
                                </div>
                            </div>
                        </div>

                        <div id="other-address" style="<?= $country != 'Philippines' ? 'display: block;' : 'display: none;' ?>">
                            <div class="form-row">
                                <div class="form-group full-width">
                                    <label for="other_address1">Address Line 1 <span class="required">*</span></label>
                                    <input type="text" name="other_address1" id="other_address1" value="<?= htmlspecialchars($street) ?>" placeholder="Street, building">
                                </div>
                            </div>
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="other_city">City <span class="required">*</span></label>
                                    <input type="text" name="other_city" id="other_city" placeholder="City">
                                </div>
                                <div class="form-group">
                                    <label for="other_state">State / Province <span class="required">*</span></label>
                                    <input type="text" name="other_state" id="other_state" placeholder="State">
                                </div>
                                <div class="form-group">
                                    <label for="other_zip">Zipcode <span class="required">*</span></label>
                                    <input type="text" name="other_zip" id="other_zip" value="<?= htmlspecialchars($zipcode) ?>" placeholder="Zipcode">
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Scholarship Information -->
                    <div class="form-section">
                        <h3>Scholarship & Academic Information</h3>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="scholarship_program">Scholarship Program <span class="required">*</span></label>
                                <select name="scholarship_program" id="scholarship_program" required>
                                    <option value="" disabled>Select Scholarship Program</option>
                                    <?php 
                                    $scholarship_program_sql = "SELECT id, name, code FROM scholarship_program WHERE is_archived = 0";
                                    $stmt = $conn->prepare($scholarship_program_sql);
                                    $stmt->execute();
                                    $result = $stmt->get_result();
                                    while($row = $result->fetch_assoc()) {
                                        $selected = ($scholarship_program == $row['id']) ? 'selected' : '';
                                        echo "<option value='" . $row['id'] . "' $selected> " . htmlspecialchars($row['name']) . " (" . htmlspecialchars($row['code']) . ")</option>";
                                    }
                                    ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="program">Program / Course <span class="required">*</span></label>
                                <select name="program" id="program" required>
                                    <option value="" disabled>Select Program</option>
                                    <?php 
                                    $program_sql = "SELECT id, name FROM program";
                                    $stmt = $conn->prepare($program_sql);
                                    $stmt->execute();
                                    $result = $stmt->get_result();
                                    while($row = $result->fetch_assoc()) {
                                        $selected = ($program == $row['id']) ? 'selected' : '';
                                        echo "<option value='" . $row['id'] . "' $selected>" . htmlspecialchars($row['name']) . "</option>";
                                    }
                                    ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="year_lvl">Year Level <span class="required">*</span></label>
                                <select name="year_lvl" id="year_lvl" required>
                                    <option value="" disabled>Select Year Level</option>
                                    <?php for($i = 1; $i <= 6; $i++) : 
                                        switch ($i) {
                                            case 1: $year = "1st Year"; break;
                                            case 2: $year = "2nd Year"; break;
                                            case 3: $year = "3rd Year"; break;
                                            default: $year = $i . "th Year"; break;
                                        }
                                    ?>
                                        <option value="<?= $i ?>" <?= ($i == $year_lvl) ? 'selected' : '' ?>><?= $year ?></option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="academic_status">Academic Status <span class="required">*</span></label>
                                <select name="academic_status" id="academic_status" required>
                                    <option value="Enrolled" <?= ($academic_status == "Enrolled") ? 'selected' : '' ?>>Enrolled</option>
                                    <option value="Not Enrolled" <?= ($academic_status == "Not Enrolled") ? 'selected' : '' ?>>Not Enrolled</option>
                                    <option value="Graduated" <?= ($academic_status == "Graduated") ? 'selected' : '' ?>>Graduated</option>
                                    <option value="Transferred" <?= ($academic_status == "Transferred") ? 'selected' : '' ?>>Transferred</option>
                                    <option value="Dropped" <?= ($academic_status == "Dropped") ? 'selected' : '' ?>>Dropped</option>
                                    <option value="Leave of Absence" <?= ($academic_status == "Leave of Absence") ? 'selected' : '' ?>>Leave of Absence</option>
                                </select>
                            </div>
                        </div>

                        <!-- Extended LOA Fields Container -->
                        <div class="form-row" id="loa_fields_container" style="display: <?= ($academic_status == "Leave of Absence") ? 'flex' : 'none' ?>;">
                            <div class="form-group">
                                <label for="loa_semester">LOA Semester <span class="required">*</span></label>
                                <select name="loa_semester" id="loa_semester">
                                    <option value="" disabled <?= empty($loa_semester) ? 'selected' : '' ?>>Select Semester</option>
                                    <option value="1st Semester" <?= ($loa_semester == '1st Semester') ? 'selected' : '' ?>>1st Semester</option>
                                    <option value="2nd Semester" <?= ($loa_semester == '2nd Semester') ? 'selected' : '' ?>>2nd Semester</option>
                                    <option value="Summer" <?= ($loa_semester == 'Summer') ? 'selected' : '' ?>>Summer</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="loa_school_year">LOA School Year <span class="required">*</span></label>
                                <select name="loa_school_year" id="loa_school_year">
                                    <option value="" disabled <?= empty($loa_school_year) ? 'selected' : '' ?>>Select School Year</option>
                                    <?php 
                                    $current_y = (int)date('Y');
                                    for ($y = $current_y - 5; $y <= $current_y + 5; $y++) : 
                                        $sy_option = $y . '-' . ($y + 1);
                                        $selected_sy = ($loa_school_year == $sy_option) ? 'selected' : '';
                                    ?>
                                        <option value="<?= $sy_option ?>" <?= $selected_sy ?>><?= $sy_option ?></option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- Family Background -->
                    <div class="form-section">
                        <h3>Family Background</h3>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="father_last">Father's Last Name <span class="required">*</span></label>
                                <input type="text" name="father_last" id="father_last" value="<?= htmlspecialchars($father_last) ?>" required>
                            </div>
                            <div class="form-group">
                                <label for="father_first">Father's First Name <span class="required">*</span></label>
                                <input type="text" name="father_first" id="father_first" value="<?= htmlspecialchars($father_given) ?>" required>
                            </div>
                            <div class="form-group">
                                <label for="father_middle">Father's Middle Name <span class="required">*</span></label>
                                <input type="text" name="father_middle" id="father_middle" value="<?= htmlspecialchars($father_middle) ?>" required>
                            </div>
                            <div class="form-group">
                                <label for="father_contact">Father's Contact Number</label>
                                <input type="text" name="father_contact" id="father_contact" value="<?= htmlspecialchars($father_contact) ?>" placeholder="09171234567" pattern="09[0-9]{9}">
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="mother_last">Mother's Maiden Last Name <span class="required">*</span></label>
                                <input type="text" name="mother_last" id="mother_last" value="<?= htmlspecialchars($mother_last) ?>" required>
                            </div>
                            <div class="form-group">
                                <label for="mother_first">Mother's Maiden First Name <span class="required">*</span></label>
                                <input type="text" name="mother_first" id="mother_first" value="<?= htmlspecialchars($mother_given) ?>" required>
                            </div>
                            <div class="form-group">
                                <label for="mother_middle">Mother's Maiden Middle Name <span class="required">*</span></label>
                                <input type="text" name="mother_middle" id="mother_middle" value="<?= htmlspecialchars($mother_middle) ?>" required>
                            </div>
                            <div class="form-group">
                                <label for="mother_contact">Mother's Contact Number</label>
                                <input type="text" name="mother_contact" id="mother_contact" value="<?= htmlspecialchars($mother_contact) ?>" placeholder="09171234567" pattern="09[0-9]{9}">
                            </div>
                        </div>
                    </div>

                    <!-- Form Actions -->
                    <div class="form-actions">
                        <button type="submit" name="update" class="btn btn-success">Update Scholar</button>
                        <a href="scholar_profile.php?scholar_id=<?= $scholar_id ?>" class="btn btn-secondary">Cancel</a>
                    </div>
                </form>
            </div>

            <!-- Footer -->
            <div class="footer">
                © 2026 PIT ScholarshipHub. All rights reserved.
            </div>
        </div>

        <script src="../assets/js/add_scholar.js"></script>
    <?php endif; ?>
</body>
</html>