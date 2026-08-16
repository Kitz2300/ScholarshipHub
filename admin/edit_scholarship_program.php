<?php 
include __DIR__ . "/../includes/conn.php";
session_start();

// get id from url
$scholarship_id = (int) ($_GET['id'] ?? 0);

// initialize variables
$name = "";
$code = "";
$start_year = "";
$end_year = "";
$description = "";
$gwa_req = 0.00;
$pay = 0.00;
$sc_type = "";
// $is_grant = 0;
$is_active = 0;

$scholarship_sql = "SELECT * FROM scholarship_program WHERE id = ?";
$scholarship_stmt = $conn->prepare($scholarship_sql);
$scholarship_stmt->bind_param('i', $scholarship_id);
$scholarship_stmt->execute();
$scholarship_result = $scholarship_stmt->get_result();

if($scholarship_result->num_rows > 0) {
    if($row = $scholarship_result->fetch_assoc()) {
        $name = $row['name'];
        $code = $row['code'];
        $school_year = explode(" - ", $row['school_year']);
        $start_year = $school_year[0];
        $end_year = $school_year[1];
        $description = $row['description'];
        $gwa_req = $row['min_gwa_req'];
        $pay = $row['pay_amount'];
        $sc_type = $row['scholarship_type'];
        // $is_grant = $row['financial_grant'];
        $is_active = $row['is_active'];
    }
} else {
    // alert user if query returned 0 results
    $_SESSION['fail'] = "Scholarship program does not exist!";
    header("Location: scholarship_program.php");
    exit();
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Edit Scholarship Program - PIT ScholarshipHub</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../assets/css/form.css?v=<?= filemtime('../assets/css/form.css') ?>">
    <link rel="icon" href="../assets/images/PIT%20LOGO.png">
    <script src="../assets/js/school_year.js"></script>
</head>
<body>
    <?php 
    // if edit failed alert users
    if(isset($_SESSION['fail'])) {
        echo "<script>alert('" . $_SESSION['fail'] . "')</script>";
        unset($_SESSION['fail']);
    }
    ?>

    <!-- check if logged in or not -->
    <?php if(!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') :?>
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
                        <p>Edit Scholarship Program</p>
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
                    <!-- Dashboard -->
                    <li><a href="dashboard.php">Dashboard</a></li>

                    <!-- Records Dropdown -->
                    <li class="dropdown">
                        <a href="#" class="dropdown-toggle active">Records <div class="arrow-down">▼</div></a>
                        <ul class="dropdown-menu">
                            <li><a href="scholar_list.php">Scholars</a></li>
                            <li><a href="scholarship_program.php" class="active">Scholarship Programs</a></li>
                            <li><a href="archive.php">Archives</a></li>
                        </ul>
                    </li>

                    <!-- Import/Export Dropdown -->
                    <li class="dropdown">
                        <a href="#" class="dropdown-toggle">Import / Export <div class="arrow-down">▼</div></a>
                        <ul class="dropdown-menu">
                            <li><a href="upload_excel.php">Import Excel</a></li>
                            <li><a href="export_scholars.php">Export Excel</a></li>
                        </ul>
                    </li>

                    <!-- Announcements -->
                    <li><a href="announcements.php">Announcements</a></li>

                    <!-- Program / College Management -->
                    <li><a href="program_management.php">Program & College Management</a></li>

                    <!-- Account Management -->
                    <?php if($_SESSION['role'] == "admin") : ?>
                        <li><a href="account_management.php">Account Management</a></li>
                    <?php endif; ?>
                </ul>
            </nav>

            <!-- Page Content -->
            <div class="page-content">
                <!-- Page Header -->
                <div class="page-header">
                    <h2>Edit Scholarship Program</h2>
                    <div class="page-actions">
                        <a href="scholarship_program.php" class="btn btn-secondary">Back to Programs</a>
                    </div>
                </div>

                <!-- edit form -->
                <form action="../functions/admin/edit_scholarship_process.php" method="POST" class="reusable-form">
                    <div class="form-section">
                        <div class="form-row">
                            <div class="form-group">
                                <label for="name">Scholarship Name <span class="required">*</span></label>
                                <input type="text" name="name" id="name" value="<?= htmlspecialchars($name) ?>" required/>
                            </div>
                            <div class="form-group">
                                <label for="code">Scholarship Code <span class="required">*</span></label>
                                <input type="text" name="code" id="code" value="<?= htmlspecialchars($code) ?>" required/>
                            </div>
                            <div class="form-group">
                                <label for="start_year">School Year <span class="required">*</span></label>
                                <div class="school-year-group">
                                    <select name="start_year" id="start_year" required>
                                        <?php for($y = date('Y') - 5; $y <= date('Y'); $y++) : ?>
                                            <option value="<?= $y ?>" <?= $start_year == $y ? "selected" : "" ?>><?= $y ?></option>
                                        <?php endfor; ?>
                                    </select>
                                    <span class="dash">–</span>
                                    <input type="text" id="end_year_display" value="" readonly class="end-year-display">
                                    <input type="hidden" name="end_year" id="end_year_hidden" value="">
                                </div>
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group full-width">
                                <label for="desc">Description <span class="required">*</span></label>
                                <textarea name="desc" id="desc" rows="5" required><?= htmlspecialchars($description) ?></textarea>
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="pay_amount">Allowance <span class="required">*</span></label>
                                <input type="number" name="pay_amount" id="pay_amount" step="0.01" min="0" placeholder="e.g., 5000.00" value="<?= htmlspecialchars($pay) ?>" required/>
                            </div>
                            <div class="form-group">
                                <label for="gwa_req">Minimum GWA Requirement</label>
                                <input type="number" name="gwa_req" id="gwa_req" step="0.01" min="0.0" max="4.0" placeholder="e.g., 2.5 or leave blank" value="<?= htmlspecialchars($gwa_req) ?>"/>
                                <small class="help-text">Leave blank if no GWA requirement.</small>
                            </div>
                            <!-- <div class="form-group">
                                <label for="is_active">Status <span class="required">*</span></label>
                                <select name="is_active" id="is_active" required>
                                    <option value="1" <?= $is_active == 1 ? "selected" : "" ?>>Active</option>
                                    <option value="0" <?= $is_active == 0 ? "selected" : "" ?>>Inactive</option>
                                </select>
                            </div> -->
                        </div>

                        <!-- Scholarship Type Selection -->
                        <div class="form-row">
                            <div class="form-group full-width">
                                <!-- <label for="scholarship_type">Type <span class="required">*</span></label>
                                <div class="radio-group">
                                    <div class="radio-option">
                                        <input type="radio" name="is_grant" id="scholarship_type" value="0" <temp?=// $is_grant == 0 ? "checked" : "" ?>>
                                        <label for="scholarship_type">Scholarship</label>
                                    </div>
                                    <div class="radio-option">
                                        <input type="radio" name="is_grant" id="financial_grant_type" value="1" <temp?=// $is_grant == 1 ? "checked" : "" ?>>
                                        <label for="financial_grant_type">Financial Grant</label>
                                    </div>
                                </div> -->

                                <label for="type">Type <span class="required">*</span></label>
                                <select name="sc_type" required>
                                    <option value="Not Specified" <?= $sc_type == "Not Specified" ? "selected" : "" ?>>Not Specified</option>
                                    <optgroup label="By Qualifications">
                                        <option value="Merit-Based" <?= $sc_type == "Merit-Based" ? "selected" : "" ?>>Merit-Based</option>
                                        <option value="Need-Based" <?= $sc_type == "Need-Based" ? "selected" : "" ?>>Need-Based</option>
                                        <option value="Career-specific" <?= $sc_type == "Career-specific" ? "selected" : "" ?>>Career-specific</option>
                                        <option value="Athletic" <?= $sc_type == "Athletic" ? "selected" : "" ?>>Athletic</option>
                                        <option value="Identity or Group-Based" <?= $sc_type == "Identity or Group-Based" ? "selected" : "" ?>>Identity or Group-Based</option>
                                    </optgroup>
                                    <optgroup label="By Funding Source">
                                        <option value="Government Scholarship" <?= $sc_type == "Government Scholarship" ? "selected" : "" ?>>Government Scholarship</option>
                                        <option value="Private / Corporate Scholarship" <?= $sc_type == "Private / Corporate Scholarship" ? "selected" : "" ?>>Private / Corporate Scholarship</option>
                                        <option value="Institutional / University Grant" <?= $sc_type == "Institutional / University Grant" ? "selected" : "" ?>>Institutional / University Grant</option>
                                        <option value="International Scholarship" <?= $sc_type == "International Scholarship" ? "selected" : "" ?>>International Scholarship</option>
                                    </optgroup>
                                    <optgroup label="Other">
                                        <option value="Academic Excellence Grant" <?= $sc_type == "Academic Excellence Grant" ? "selected" : "" ?>>Academic Excellence Grant</option>
                                        <option value="Leadership / Community Services" <?= $sc_type == "Leadership / Community Services" ? "selected" : "" ?>>Leadership / Community Service</option>
                                        <option value="Research Scholarship" <?= $sc_type == "Research Scholarship" ? "selected" : "" ?>>Research Scholarship</option>
                                        <option value="External Partner Scholarship" <?= $sc_type == "External Partner Scholarship" ? "selected" : "" ?>>External Partner Scholarship</option>
                                    </optgroup>
                                </select>
                            </div>
                        </div>
                    </div>

                    <input type="hidden" name="scholarship_id" value="<?= $scholarship_id ?>"/>

                    <div class="form-actions">
                        <button type="submit" name="update" class="btn btn-success">Update Scholarship Program</button>
                        <a href="scholarship_program.php" class="btn btn-secondary">Cancel</a>
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