<?php 
session_start();
include __DIR__ . '/../includes/conn.php';
include '../includes/check_permission.php';

if(!(hasPermission($conn, $_SESSION['user_id'], 'scholars', 'add'))) {
    header("Location: scholar_list.php");
    exit();
}

// initialize year variable
$year = "";

// Get all regions
$regions_sql = "SELECT id, name FROM psgc WHERE is_reg = 1 ORDER BY name";
$regions_result = $conn->query($regions_sql);

// Get provinces under a specific region
$region_id = $_POST['region_id'] ?? 0;
$provinces_sql = "SELECT id, name FROM psgc WHERE is_prov = 1 AND parent_id = ? ORDER BY name";
$stmt = $conn->prepare($provinces_sql);
$stmt->bind_param("i", $region_id);
$stmt->execute();
$provinces_result = $stmt->get_result();

// Get cities/municipalities under a specific province
$province_id = $_POST['province_id'] ?? 0;
$cities_sql = "SELECT id, name FROM psgc WHERE (is_city = 1 OR is_municipality = 1) AND parent_id = ? ORDER BY name";
$stmt = $conn->prepare($cities_sql);
$stmt->bind_param("i", $province_id);
$stmt->execute();
$cities_result = $stmt->get_result();

// Get barangays under a specific city/municipality
$city_id = $_POST['city_id'] ?? 0;
$barangays_sql = "SELECT id, name FROM psgc WHERE is_bgy = 1 AND parent_id = ? ORDER BY name";
$stmt = $conn->prepare($barangays_sql);
$stmt->bind_param("i", $city_id);
$stmt->execute();
$barangays_result = $stmt->get_result();
?>

<!DOCTYPE html>
<html>
<head>
    <title>Add Scholar - PIT ScholarshipHub</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../assets/css/form.css">
    <link rel="stylesheet" href="../assets/css/modals.css?v=<?= filemtime('../assets/css/modals.css') ?>">
    <link rel="icon" href="../assets/images/PIT%20LOGO.png">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="../assets/js/address.js"></script>
</head>
<body>
    <?php include __DIR__ . "/../includes/modal_alerts.php"; ?>
    <?php if(!isset($_SESSION['user_id']) || ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'staff')) :
        $_SESSION['fail'] = "You are no logged in!";
        header("Location: ../portal.php");
        exit();
    else : ?>
        <div class="container">
            <!-- Header -->
            <div class="header">
                <div class="header-left">
                    <img src="../assets/images/PIT%20LOGO.png" alt="PIT Logo">
                    <div>
                        <h1>PIT ScholarshipHub</h1>
                        <p>Add New Scholar</p>
                    </div>
                </div>
                <div class="header-right">
                    <span class="welcome-text">Welcome, Admin!</span>
                    <form action="../functions/logout.php" method="post" style="display: inline;">
                        <button type="submit" class="logout-btn" onclick="return confirmAction(event, 'Are you sure you want to logout?', 'Logout', true)">Logout</button>
                    </form>
                </div>
            </div>

            <?php include __DIR__ . "/../includes/navigation.php"; ?>

            <!-- Page Content -->
            <div class="page-content">
                <div class="page-header">
                    <h2>Add New Scholar</h2>
                    <div class="page-actions">
                        <a href="scholar_list.php" class="btn btn-secondary">Back to List</a>
                    </div>
                </div>

                <!-- Add Scholar Form -->
                <form action="../functions/admin/add_scholar_process.php" method="POST" class="scholar-form">
                    <!-- Student Information -->
                    <div class="form-section">
                        <h3>Student Information</h3>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="last_name">Last Name <span class="required">*</span></label>
                                <input type="text" name="last_name" id="last_name" placeholder="Enter last name" required>
                            </div>
                            <div class="form-group">
                                <label for="first_name">First Name <span class="required">*</span></label>
                                <input type="text" name="first_name" id="first_name" placeholder="Enter first name" required>
                            </div>
                            <div class="form-group">
                                <label for="middle_name">Middle Name</label>
                                <input type="text" name="middle_name" id="middle_name" placeholder="Enter middle name">
                            </div>
                            <div class="form-group">
                                <label for="ext_name">Extension Name</label>
                                <select name="ext_name" id="ext_name">
                                    <option value="N/A" selected>N/A</option>
                                    <option value="Jr.">Jr.</option>
                                    <option value="Sr.">Sr.</option>
                                    <option value="II">II</option>
                                    <option value="III">III</option>
                                </select>
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="email">Email Address <span class="required">*</span></label>
                                <input type="email" name="email" id="email" placeholder="example@gmail.com" pattern="[^\s@]+@[^\s@]+\.[^\s@]+" required>
                            </div>
                            <div class="form-group">
                                <label for="student_id">Student ID <span class="required">*</span></label>
                                <input type="tel" name="student_id" id="student_id" placeholder="12-3456-789" pattern="[0-9]{2}-[0-9]{4}-[0-9]{3}" required>
                            </div>
                            <div class="form-group">
                                <label for="student_contact">Contact Number <span class="required">*</span></label>
                                <input type="text" name="student_contact" id="student_contact" placeholder="09171234567" pattern="09[0-9]{9}" required>
                            </div>
                        </div>

                        <!-- PHILSYS ID, LRN, 4P's ID -->
                        <div class="form-row">
                            <div class="form-group">
                                <label for="philsys_id">PHILSYS ID <span class="required">*</span></label>
                                <input type="text" name="philsys_id" id="philsys_id" 
                                    placeholder="e.g., 1234-5678-9012-3456" pattern="[0-9]{4}-[0-9]{4}-[0-9]{4}-[0-9]{4}"
                                    required>
                            </div>
                            <div class="form-group">
                                <label for="lrn">LRN <span class="required">*</span></label>
                                <input type="number" name="lrn" id="lrn" 
                                    placeholder="e.g., 123456789021" pattern="[0-9]{12}"
                                    required>
                            </div>
                            <div class="form-group">
                                <label for="four_ps_id">4P's ID</label>
                                <input type="text" name="four_ps_id" id="four_ps_id" placeholder="e.g., 083740027-6222-00003" pattern="[A-Za-z0-9-]{17,21}">
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="sex">Sex <span class="required">*</span></label>
                                <select name="sex" id="sex" required>
                                    <option value="" selected disabled>Select Sex</option>
                                    <option value="Male">Male</option>
                                    <option value="Female">Female</option>
                                </select>
                            </div>

                            <!-- Birth Date Field with Clean Hidden Picker -->
                            <div class="form-group">
                                <label for="birth_date">Birth Date (YYYY-MM-DD) <span class="required">*</span></label>
                                <div style="display: flex; gap: 6px; align-items: center; position: relative;">
                                    <input type="text" name="birth_date" id="birth_date" placeholder="YYYY-MM-DD" pattern="\d{4}-(0[1-9]|1[0-2])-(0[1-9]|[12][0-9]|3[01])" required style="flex: 1;">
                                    <button type="button" id="btn_birth_picker" class="btn btn-secondary" style="padding: 8px 12px; font-size: 16px; cursor: pointer; background: #ffff; border: 1px solid black;" title="Select Birth Date">📅</button>
                                    <input type="date" id="birth_date_picker" style="position: absolute; opacity: 0; pointer-events: none; width: 0; height: 0;">
                                </div>
                            </div>

                            <div class="form-group">
                                <label for="disability">Disability</label>
                                <input type="text" name="disability" id="disability" placeholder="e.g., Visual impairment, Dyslexia, Mobility impairment" list="disability-list" />
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
                                <input type="text" name="ip_group" id="ip_group" placeholder="e.g., Ifugao, Tagbanwa, Maranao, B'laan, Teduray" list="ip-group-list" />
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
                                <input type="checkbox" name="is_child_of_solo_parent" id="is_child_of_solo_parent" value="1" style="width: 18px; height: 18px; cursor: pointer; accent-color: #1e4628;">
                                <label for="is_child_of_solo_parent" style="cursor: pointer; font-weight: bold; margin-bottom: 0;">Child of a Solo Parent</label>
                            </div>
                        </div>
                    </div>

                    <!-- Address Section -->
                    <div class="form-section">
                        <h3>Address</h3>
                        
                        <!-- Country -->
                        <div class="form-row">
                            <div class="form-group">
                                <label for="country">Country <span class="required">*</span></label>
                                <select name="country" id="country">
                                    <option value="Philippines" selected>Philippines</option>
                                    <option value="Other">Other</option>
                                </select>
                            </div>
                        </div>

                        <!-- Other Country Name -->
                        <div id="other-country-row" style="display: none;">
                            <div class="form-group">
                                <label for="other_country">Country Name <span class="required">*</span></label>
                                <input type="text" name="other_country" id="other_country" placeholder="Enter country name">
                            </div>
                        </div>

                        <!-- Philippine Address -->
                        <div id="philippines-address">
                            <div class="form-row">
                                <div class="form-group full-width">
                                    <label for="street">Street / Building / Unit <span class="required">*</span></label>
                                    <input type="text" name="street" id="street" placeholder="e.g., 123 Main St" required>
                                </div>
                            </div>
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="region">Region <span class="required">*</span></label>
                                    <select name="region" id="region">
                                        <option value="">Select Region</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label for="province">Province <span class="required">*</span></label>
                                    <select name="province" id="province">
                                        <option value="">Select Province</option>
                                    </select>
                                </div>
                            </div>
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="city">City / Municipality <span class="required">*</span></label>
                                    <select name="city" id="city">
                                        <option value="">Select City</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label for="barangay">Barangay <span class="required">*</span></label>
                                    <select name="barangay" id="barangay">
                                        <option value="">Select Barangay</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label for="zipcode">Zipcode <span class="required">*</span></label>
                                    <input type="text" name="zipcode" id="zipcode" placeholder="Zipcode">
                                </div>
                            </div>
                        </div>

                        <!-- Other Address -->
                        <div id="other-address" style="display: none;">
                            <div class="form-row">
                                <div class="form-group full-width">
                                    <label for="other_address1">Address Line 1 <span class="required">*</span></label>
                                    <input type="text" name="other_address1" id="other_address1" placeholder="Street, building">
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
                                    <input type="text" name="other_zip" id="other_zip" placeholder="Zipcode">
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Scholarship & Academic Information -->
                    <div class="form-section">
                        <h3>Scholarship & Academic Information</h3>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="scholarship_program">Scholarship Program <span class="required">*</span></label>
                                <select name="scholarship_program" id="scholarship_program" required>
                                    <option value="" selected disabled>Select Scholarship Program</option>
                                    <?php 
                                    $scholarship_program_sql = "SELECT id, name, code FROM scholarship_program WHERE is_archived = 0 ORDER BY name ASC";
                                    $stmt = $conn->prepare($scholarship_program_sql);
                                    $stmt->execute();
                                    $result = $stmt->get_result();
                                    while($row = $result->fetch_assoc()) : ?>
                                        <option value="<?= $row['id'] ?>"><?= htmlspecialchars($row['name']) ?> (<?= htmlspecialchars($row['code']) ?>)</option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="program">Program / Course <span class="required">*</span></label>
                                <select name="program" id="program" required>
                                    <option value="" selected disabled>Select Program</option>
                                    <?php 
                                    $program_sql = "SELECT id, name FROM program ORDER BY name ASC";
                                    $stmt = $conn->prepare($program_sql);
                                    $stmt->execute();
                                    $result = $stmt->get_result();
                                    while($row = $result->fetch_assoc()) : ?>
                                        <option value="<?= $row['id'] ?>"><?= htmlspecialchars($row['name']) ?></option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="year_lvl">Year Level <span class="required">*</span></label>
                                <select name="year_lvl" id="year_lvl" required>
                                    <option value="" selected disabled>Select Year Level</option>
                                    <?php for($i = 1; $i <= 6; $i++) : 
                                        switch ($i) {
                                            case 1: $year = "1st Year"; break;
                                            case 2: $year = "2nd Year"; break;
                                            case 3: $year = "3rd Year"; break;
                                            default: $year = $i . "th Year"; break;
                                        }
                                    ?>
                                        <option value="<?= $i ?>"><?= $year ?></option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="academic_status">Academic Status <span class="required">*</span></label>
                                <select name="academic_status" id="academic_status" required>
                                    <option value="Enrolled" selected>Enrolled</option>
                                    <option value="Not Enrolled">Not Enrolled</option>
                                    <option value="Graduated">Graduated</option>
                                    <option value="Transferred">Transferred</option>
                                    <option value="Dropped">Dropped</option>
                                    <option value="Leave of Absence">Leave of Absence</option>
                                </select>
                            </div>
                        </div>

                        <!-- Extended LOA Fields Container -->
                        <div class="form-row" id="loa_fields_container" style="display: none;">
                            <div class="form-group">
                                <label for="loa_semester">LOA Semester <span class="required">*</span></label>
                                <select name="loa_semester" id="loa_semester">
                                    <option value="" selected disabled>Select Semester</option>
                                    <option value="1st Semester">1st Semester</option>
                                    <option value="2nd Semester">2nd Semester</option>
                                    <option value="Summer">Summer</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="loa_school_year">LOA School Year <span class="required">*</span></label>
                                <select name="loa_school_year" id="loa_school_year">
                                    <option value="" selected disabled>Select School Year</option>
                                    <?php 
                                    $current_y = (int)date('Y');
                                    for ($y = $current_y - 5; $y <= $current_y + 5; $y++) : 
                                        $sy_option = $y . '-' . ($y + 1);
                                    ?>
                                        <option value="<?= $sy_option ?>"><?= $sy_option ?></option>
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
                                <input type="text" name="father_last" id="father_last" placeholder="Last Name" required>
                            </div>
                            <div class="form-group">
                                <label for="father_first">Father's First Name <span class="required">*</span></label>
                                <input type="text" name="father_first" id="father_first" placeholder="First Name" required>
                            </div>
                            <div class="form-group">
                                <label for="father_middle">Father's Middle Name <span class="required">*</span></label>
                                <input type="text" name="father_middle" id="father_middle" placeholder="Middle Name" required>
                            </div>
                            <div class="form-group">
                                <label for="father_contact">Father's Contact Number</label>
                                <input type="text" name="father_contact" id="father_contact" placeholder="09171234567" pattern="09[0-9]{9}">
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="mother_last">Mother's Maiden Last Name <span class="required">*</span></label>
                                <input type="text" name="mother_last" id="mother_last" placeholder="Last Name" required>
                            </div>
                            <div class="form-group">
                                <label for="mother_first">Mother's Maiden First Name <span class="required">*</span></label>
                                <input type="text" name="mother_first" id="mother_first" placeholder="First Name" required>
                            </div>
                            <div class="form-group">
                                <label for="mother_middle">Mother's Maiden Middle Name <span class="required">*</span></label>
                                <input type="text" name="mother_middle" id="mother_middle" placeholder="Middle Name" required>
                            </div>
                            <div class="form-group">
                                <label for="mother_contact">Mother's Contact Number</label>
                                <input type="text" name="mother_contact" id="mother_contact" placeholder="09171234567" pattern="09[0-9]{9}">
                            </div>
                        </div>
                    </div>

                    <!-- Form Actions -->
                    <div class="form-actions">
                        <button name="add" type="submit" class="btn btn-success">Add Scholar</button>
                        <a href="scholar_list.php" class="btn btn-secondary">Cancel</a>
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