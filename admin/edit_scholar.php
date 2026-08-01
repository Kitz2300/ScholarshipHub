<?php 
include __DIR__ . '/../includes/conn.php';
session_start();

// get scholar id
$scholar_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

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
$address = "";
$zipcode = "";
$disability = "";
$contact_num = "";
$email = "";
$ip_group = "";
$father_last = "";
$father_given = "";
$father_middle = "";
$mother_last = "";
$mother_given = "";
$mother_middle = "";

// insert values to initialized variables
if($row = $scholar_result->fetch_assoc()) {
    $last_name = $row['last_name'];
    $given_name = $row['given_name'];
    $ext_name = $row['ext_name'] === "N/A" ? "Not Applicable" : $row['ext_name']; // show Not Applicable if N/A
    $middle_name = $row['middle_name'];
    $id_num = $row['student_id'];
    $sex = $row['sex'];
    $birthdate = $row['birthdate'];
    $scholarship_program = $row['scholarship_program_id'];
    $program = $row['program_id'];
    $year_lvl = $row['year_level'];
    $academic_status = $row['academic_status'];
    $address = $row['permanent_address'];
    $zipcode = $row['zipcode'];
    $disability = $row['disability'];
    $contact_num = $row['contact_num'];
    $email = $row['email'];
    $ip_group = $row['ip_group'];
    $father_last = $row['father_last_name'];
    $father_given = $row['father_given_name'];
    $father_middle = $row['father_middle_name'];
    $mother_last = $row['mother_last_name'];
    $mother_given = $row['mother_given_name'];
    $mother_middle = $row['mother_middle_name'];
} else {
    // alert user if there are no results from query
    $_SESSION['fail'] = "Scholar not available";
    header("Location: scholar_list.php");
    exit();
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Edit Scholar - PIT ScholarshipHub</title>
    <link rel="stylesheet" href="../assets/css/form.css">
    <link rel="icon" href="../assets/images/PIT%20LOGO.png">
</head>
<body>
    <?php 
    // update fail message
    if(isset($_SESSION['fail'])) {
        echo "<script>alert('" . $_SESSION['fail'] . "')</script>";
        unset($_SESSION['fail']);
    }
    ?>
    <?php if(!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') : ?>
        <script>alert('You are not logged in')</script>
        <script>window.location.href='login.php'</script>
    <?php else : ?>
        <div class="container">
            <!-- Header -->
            <div class="header">
                <div class="header-left">
                    <img src="../assets/images/PIT%20LOGO.png" alt="PIT Logo">
                    <div>
                        <h1>PIT ScholarshipHub</h1>
                        <p>Edit Scholar</p>
                    </div>
                </div>
                <div class="header-right">
                    <span class="welcome-text">Welcome, Admin!</span>
                    <form action="../functions/logout.php" method="post" style="display: inline;">
                        <button type="submit" class="logout-btn" onclick="return confirm('Logout?')">Logout</button>
                    </form>
                </div>
            </div>

            <!-- Navigation -->
            <nav class="nav-bar">
                <ul>
                    <li><a href="dashboard.php">Dashboard</a></li>
                    <li><a href="scholar_list.php" class="active">Scholars</a></li>
                    <li><a href="announcements.php">Announcements</a></li>
                    <li><a href="scholarship_program.php">Scholarship Programs</a></li>
                    <li><a href="upload_excel.php">Import Excel</a></li>
                    <li><a href="export_scholars.php">Export Excel</a></li>
                </ul>
            </nav>

            <!-- Page Content -->
            <div class="page-content">
                <!-- Page Header -->
                <div class="page-header">
                    <h2>Edit Scholar</h2>
                    <div class="page-actions">
                        <a href="scholar_profile.php?scholar_id=<?= $scholar_id ?>" class="btn btn-secondary">Back to Profile</a>
                    </div>
                </div>

                <!-- Edit Form with it's current values-->
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
                                    <option value="N/A" <?= ($ext_name == 'N/A') ? 'selected' : '' ?>>N/A</option>
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
                                <input type="text" name="student_id" id="student_id" value="<?= htmlspecialchars($id_num) ?>" placeholder="12-3456-789" pattern="[0-9]{2}-[0-9]{4}-[0-9]{3}" required>
                            </div>
                            <div class="form-group">
                                <label for="student_contact">Contact Number <span class="required">*</span></label>
                                <input type="text" name="student_contact" id="student_contact" value="<?= htmlspecialchars($contact_num) ?>" placeholder="09171234567" pattern="09[0-9]{9}" required>
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
                            <div class="form-group">
                                <label for="birth_date">Birth Date <span class="required">*</span></label>
                                <input type="date" name="birth_date" id="birth_date" value="<?= htmlspecialchars($birthdate) ?>" required>
                            </div>
                            <div class="form-group">
                                <label for="disability">Disability (Optional)</label>
                                <input type="text" name="disability" id="disability" placeholder="e.g., Visual impairment, Dyslexia, Mobility impairment" value="<?= $disability ?>" list="disability-list"/>
                                <datalist id="disability-list">
                                    <option value="Not applicable"></option>
                                    <option value="Visual Impairment"></option>
                                    <option value="Hearing Impairment"></option>
                                    <option value="Mobility Impairment"></option>
                                    <option value="Cognitive Impairment"></option>
                                </datalist>
                            </div>
                            <div class="form-group">
                                <label for="ip_group">IP Group (Optional)</label>
                                <input type="text" name="ip_group" id="ip_group" placeholder="e.g., Ifugao, Tagbanwa, Maranao, B'laan, Teduray" value="<?= $ip_group ?>" list="ip-group-list"/>
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

                        <div class="form-row">
                            <div class="form-group full-width">
                                <label for="address">Permanent Address <span class="required">*<span></span></label>
                                <input type="text" name="address" id="address" value="<?= htmlspecialchars($address) ?>">
                            </div>
                            <div class="form-group">
                                <label for="zipcode">Zipcode <span class="required">*<span></span></label>
                                <input type="number" name="zipcode" id="zipcode" placeholder="Brgy, Town/City, Province" value="<?= htmlspecialchars($zipcode) ?>" placeholder="6538" min="0" max="9999" required>
                            </div>
                        </div>
                    </div>

                    <!-- Scholarship Information -->
                    <div class="form-section">
                        <h3>Scholarship Information</h3>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="scholarship_program">Scholarship Program <span class="required">*</span></label>
                                <select name="scholarship_program" id="scholarship_program" required>
                                    <option value="" disabled>Select Scholarship Program</option>
                                    <?php 
                                    // scholarship programs query
                                    $scholarship_program_sql = "SELECT id, name, code FROM scholarship_program";
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
                                    // program's/course query
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
                                    <option value="Active" <?= ($academic_status == "Active") ? 'selected' : '' ?>>Active</option>
                                    <option value="Graduated" <?= ($academic_status == "Graduated") ? 'selected' : '' ?>>Graduated</option>
                                    <option value="Transferred" <?= ($academic_status == "Transferred") ? 'selected' : '' ?>>Transferred</option>
                                    <option value="Dropped" <?= ($academic_status == "Dropped") ? 'selected' : '' ?>>Dropped</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- Family Background -->
                    <div class="form-section">
                        <h3>Family Background</h3>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="father_last">Father's Last Name</label>
                                <input type="text" name="father_last" id="father_last" value="<?= htmlspecialchars($father_last) ?>">
                            </div>
                            <div class="form-group">
                                <label for="father_first">Father's First Name</label>
                                <input type="text" name="father_first" id="father_first" value="<?= htmlspecialchars($father_given) ?>">
                            </div>
                            <div class="form-group">
                                <label for="father_middle">Father's Middle Name</label>
                                <input type="text" name="father_middle" id="father_middle" value="<?= htmlspecialchars($father_middle) ?>">
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="mother_last">Mother's Maiden Last Name</label>
                                <input type="text" name="mother_last" id="mother_last" value="<?= htmlspecialchars($mother_last) ?>">
                            </div>
                            <div class="form-group">
                                <label for="mother_first">Mother's Maiden First Name</label>
                                <input type="text" name="mother_first" id="mother_first" value="<?= htmlspecialchars($mother_given) ?>">
                            </div>
                            <div class="form-group">
                                <label for="mother_middle">Mother's Maiden Middle Name</label>
                                <input type="text" name="mother_middle" id="mother_middle" value="<?= htmlspecialchars($mother_middle) ?>">
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
    <?php endif; ?>
</body>
</html>