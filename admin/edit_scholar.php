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
$scholar_sy = "";
$scholar_sem = "";
$program = 0;
$year_lvl = "";
$academic_status = "";
$loa_semester = "";
$loa_school_year = "";
$address = "";
$zipcode = "";
$disability = "";
$contact_num = "";
$email = "";
$ip_group = "";

$is_solo_parent = 0;
$is_raised_by_solo_parent = 0;
$is_gida = 0;
$gida_details = "";
$is_farmer_fisherfolk = 0;
$is_rebel_returnee = 0;

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
    $scholar_sy = $row['school_year'] ?? '';
    $scholar_sem = $row['semester'] ?? '';
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

    $is_solo_parent = (int) ($row['is_solo_parent'] ?? 0);
    $is_raised_by_solo_parent = (int) ($row['is_raised_by_solo_parent'] ?? $row['is_child_of_solo_parent'] ?? 0);
    $is_gida = (int) ($row['is_gida'] ?? 0);
    $gida_details = $row['gida_details'] ?? '';
    $is_farmer_fisherfolk = (int) ($row['is_farmer_fisherfolk'] ?? 0);
    $is_rebel_returnee = (int) ($row['is_rebel_returnee'] ?? 0);

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

    // If scholar's scholarship program details match existing program, fill missing term info
    if ($scholarship_program > 0 && (empty($scholar_sy) || empty($scholar_sem))) {
        $sp_info_stmt = $conn->prepare("SELECT school_year, semester FROM scholarship_program WHERE id = ?");
        $sp_info_stmt->bind_param('i', $scholarship_program);
        $sp_info_stmt->execute();
        $sp_info_res = $sp_info_stmt->get_result();
        if ($sp_info_row = $sp_info_res->fetch_assoc()) {
            if (empty($scholar_sy)) $scholar_sy = $sp_info_row['school_year'];
            if (empty($scholar_sem)) $scholar_sem = $sp_info_row['semester'];
        }
    }
    
} else {
    $_SESSION['fail'] = "Scholar not available";
    header("Location: scholar_list.php");
    exit();
}

// Fetch active scholarship programs with School Year & Semester info
$all_sp_sql = "SELECT id, name, code, school_year, REPLACE(school_year, ' ', '') AS clean_sy, semester 
               FROM scholarship_program 
               WHERE is_archived = 0 OR id = ? 
               ORDER BY name ASC";
$all_sp_stmt = $conn->prepare($all_sp_sql);
$all_sp_stmt->bind_param('i', $scholarship_program);
$all_sp_stmt->execute();
$all_sp_res = $all_sp_stmt->get_result();

$sp_list = [];
$available_sy_list = [];

while ($sp_row = $all_sp_res->fetch_assoc()) {
    $sp_list[] = $sp_row;
    if (!in_array($sp_row['school_year'], $available_sy_list)) {
        $available_sy_list[] = $sp_row['school_year'];
    }
}

if (!empty($scholar_sy) && !in_array($scholar_sy, $available_sy_list)) {
    $available_sy_list[] = $scholar_sy;
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
        $_SESSION['fail'] = "You are not logged in!";
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
                                <input type="text" 
                                       name="student_id" 
                                       id="student_id" 
                                       value="<?= htmlspecialchars($id_num) ?>" 
                                       placeholder="00-0000-000" 
                                       maxlength="11" 
                                       pattern="\d{2}-\d{4}-\d{3}" 
                                       title="e.g. 12-3456-789" 
                                       required>
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

                            <!-- Birth Date Field -->
                            <div class="form-group">
                                <label for="birth_date">Birth Date (YYYY-MM-DD) <span class="required">*</span></label>
                                <div style="display: flex; gap: 6px; align-items: center; position: relative;">
                                    <input type="text" name="birth_date" id="birth_date" value="<?= htmlspecialchars($birthdate) ?>" placeholder="YYYY-MM-DD" pattern="\d{4}-(0[1-9]|1[0-2])-(0[1-9]|[12][0-9]|3[01])" required style="flex: 1;">
                                    <button type="button" id="btn_birth_picker" class="btn btn-secondary" style="padding: 8px 12px; font-size: 16px; cursor: pointer; background: #ffff; border: 1px solid black;" title="Select Birth Date">📅</button>
                                    <input type="date" id="birth_date_picker" value="<?= htmlspecialchars($birthdate) ?>" style="position: absolute; opacity: 0; pointer-events: none; width: 0; height: 0;">
                                </div>
                            </div>

                            <div class="form-group">
                                <label for="disability">Disability (PWD)</label>
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
                        </div>

                        <!-- Special Classifications & Sectoral Groups -->
                        <div class="form-row" style="margin-top: 15px;">
                            <div class="form-group full-width">
                                <label style="font-weight: bold; margin-bottom: 10px; display: block; border-bottom: 1px solid #ccc; padding-bottom: 5px;">Special Classifications & Sectoral Groups</label>
                                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 12px; margin-top: 8px;">
                                    <div style="display: flex; align-items: center; gap: 8px;">
                                        <input type="checkbox" name="is_solo_parent" id="is_solo_parent" value="1" <?= $is_solo_parent === 1 ? 'checked' : '' ?> style="width: 18px; height: 18px; cursor: pointer; accent-color: #1e4628;">
                                        <label for="is_solo_parent" style="cursor: pointer; font-weight: 600; margin: 0;">Solo Parent</label>
                                    </div>
                                    <div style="display: flex; align-items: center; gap: 8px;">
                                        <input type="checkbox" name="is_raised_by_solo_parent" id="is_raised_by_solo_parent" value="1" <?= $is_raised_by_solo_parent === 1 ? 'checked' : '' ?> style="width: 18px; height: 18px; cursor: pointer; accent-color: #1e4628;">
                                        <label for="is_raised_by_solo_parent" style="cursor: pointer; font-weight: 600; margin: 0;">Raised by a Single / Solo Parent</label>
                                    </div>
                                    <div style="display: flex; align-items: center; gap: 8px;">
                                        <input type="checkbox" name="is_gida" id="is_gida" value="1" <?= $is_gida === 1 ? 'checked' : '' ?> onchange="toggleGidaDetails(this)" style="width: 18px; height: 18px; cursor: pointer; accent-color: #1e4628;">
                                        <label for="is_gida" style="cursor: pointer; font-weight: 600; margin: 0;">Living in GIDA (Geographically Isolated Area)</label>
                                    </div>
                                    <div style="display: flex; align-items: center; gap: 8px;">
                                        <input type="checkbox" name="is_farmer_fisherfolk" id="is_farmer_fisherfolk" value="1" <?= $is_farmer_fisherfolk === 1 ? 'checked' : '' ?> style="width: 18px; height: 18px; cursor: pointer; accent-color: #1e4628;">
                                        <label for="is_farmer_fisherfolk" style="cursor: pointer; font-weight: 600; margin: 0;">Family of Subsistence Farmer / Fisherfolk</label>
                                    </div>
                                    <div style="display: flex; align-items: center; gap: 8px;">
                                        <input type="checkbox" name="is_rebel_returnee" id="is_rebel_returnee" value="1" <?= $is_rebel_returnee === 1 ? 'checked' : '' ?> style="width: 18px; height: 18px; cursor: pointer; accent-color: #1e4628;">
                                        <label for="is_rebel_returnee" style="cursor: pointer; font-weight: 600; margin: 0;">Family of Rebel Returnee</label>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- GIDA Details Field -->
                        <div class="form-row" id="gida_details_container" style="display: <?= $is_gida === 1 ? 'flex' : 'none' ?>;">
                            <div class="form-group full-width">
                                <label for="gida_details">Please specify GIDA Area / Details</label>
                                <input type="text" name="gida_details" id="gida_details" value="<?= htmlspecialchars($gida_details) ?>" placeholder="e.g., Island Barangay / Mountainous Remote Area">
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

                        <!-- School Year & Semester Selection -->
                        <div class="form-row">
                            <div class="form-group half-width">
                                <label for="school_year_select">School Year <span class="required">*</span></label>
                                <select id="school_year_select" required>
                                    <?php foreach ($available_sy_list as $sy) : ?>
                                        <option value="<?= htmlspecialchars($sy) ?>" <?= (str_replace(' ', '', $scholar_sy) === str_replace(' ', '', $sy)) ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($sy) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group half-width">
                                <label for="semester_select">Semester <span class="required">*</span></label>
                                <select id="semester_select" required>
                                    <option value="1st Semester" <?= ($scholar_sem === '1st Semester') ? 'selected' : '' ?>>1st Semester</option>
                                    <option value="2nd Semester" <?= ($scholar_sem === '2nd Semester') ? 'selected' : '' ?>>2nd Semester</option>
                                    <option value="Summer" <?= ($scholar_sem === 'Summer') ? 'selected' : '' ?>>Summer</option>
                                </select>
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="scholarship_program">Scholarship Program <span class="required">*</span></label>
                                <select name="scholarship_program" id="scholarship_program" required>
                                    <option value="" disabled>Select Scholarship Program</option>
                                </select>
                                <small id="sp_error_msg" style="color: #dc3545; font-size: 12px; margin-top: 5px; display: none; font-weight: 500;">⚠️ No active scholarship program available for this term.</small>
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
                                    <option value="" disabled <?= empty($year_lvl) ? 'selected' : '' ?>>Select Year Level</option>
                                    <option value="1st Year" <?= ($year_lvl === '1st Year') ? 'selected' : '' ?>>1st Year</option>
                                    <option value="2nd Year" <?= ($year_lvl === '2nd Year') ? 'selected' : '' ?>>2nd Year</option>
                                    <option value="3rd Year" <?= ($year_lvl === '3rd Year') ? 'selected' : '' ?>>3rd Year</option>
                                    <option value="4th Year" <?= ($year_lvl === '4th Year') ? 'selected' : '' ?>>4th Year</option>
                                    <option value="Irregular" <?= ($year_lvl === 'Irregular') ? 'selected' : '' ?>>Irregular</option>
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
                                        $sy_option =$y . '-' . ($y + 1);$selected_sy = ($loa_school_year ==$sy_option) ? 'selected' : '';
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

        <script>
        const allScholarshipPrograms = <?= json_encode($sp_list) ?>;
        const currentScholarProgramId = <?= (int)$scholarship_program ?>;

        function updateScholarshipDropdown() {
            const sySelect = document.getElementById('school_year_select');
            const semSelect = document.getElementById('semester_select');
            const spSelect = document.getElementById('scholarship_program');
            const spErrorMsg = document.getElementById('sp_error_msg');

            if (!sySelect || !semSelect || !spSelect) return;

            const selectedSY = sySelect.value.replace(/\s+/g, '');
            const selectedSem = semSelect.value;

            spSelect.innerHTML = '<option value="" disabled>Select Scholarship Program</option>';

            const filtered = allScholarshipPrograms.filter(p => p.clean_sy === selectedSY && p.semester === selectedSem);

            if (filtered.length > 0) {
                if (spErrorMsg) spErrorMsg.style.display = 'none';
                spSelect.disabled = false;
                let hasMatch = false;

                filtered.forEach(p => {
                    const opt = document.createElement('option');
                    opt.value = p.id;
                    opt.textContent = `${p.name} (${p.code})`;
                    if (parseInt(p.id) === currentScholarProgramId) {
                        opt.selected = true;
                        hasMatch = true;
                    }
                    spSelect.appendChild(opt);
                });

                if (!hasMatch && spSelect.options.length > 1) {
                    spSelect.options[0].selected = true;
                }
            } else {
                if (spErrorMsg) spErrorMsg.style.display = 'block';
                spSelect.disabled = true;
            }
        }

        function toggleGidaDetails(checkbox) {
            const container = document.getElementById('gida_details_container');
            if (container) {
                container.style.display = checkbox.checked ? 'flex' : 'none';
                if (!checkbox.checked) {
                    document.getElementById('gida_details').value = '';
                }
            }
        }

        document.addEventListener("DOMContentLoaded", function() {
            const sySelect = document.getElementById('school_year_select');
            const semSelect = document.getElementById('semester_select');

            if (sySelect) sySelect.addEventListener('change', updateScholarshipDropdown);
            if (semSelect) semSelect.addEventListener('change', updateScholarshipDropdown);

            // Initial population
            updateScholarshipDropdown();

            const studentIdInput = document.getElementById("student_id");
            if (studentIdInput) {
                studentIdInput.addEventListener("input", function(e) {
                    let digits = e.target.value.replace(/\D/g, "");
                    if (digits.length > 9) {
                        digits = digits.substring(0, 9);
                    }
                    let formatted = "";
                    if (digits.length > 6) {
                        formatted = digits.substring(0, 2) + "-" + digits.substring(2, 6) + "-" + digits.substring(6);
                    } else if (digits.length > 2) {
                        formatted = digits.substring(0, 2) + "-" + digits.substring(2);
                    } else {
                        formatted = digits;
                    }
                    e.target.value = formatted;
                });
            }
        });
        </script>
        <script src="../assets/js/add_scholar.js"></script>
    <?php endif; ?>
</body>
</html>