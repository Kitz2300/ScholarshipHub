<?php 
include __DIR__ . '/../includes/conn.php';
include '../includes/check_permission.php';
session_start();

// Handle proper return URL ---
$current_referrer = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '';

// If we are coming from a valid List page (not the edit page), save it into the session.
if (!empty($current_referrer) && strpos($current_referrer, 'edit_scholar.php') === false && strpos($current_referrer, 'academic_performance.php') === false) {
    $_SESSION['redirect_back_url'] = $current_referrer;
}

// Determine which URL to use for the "Back" button
$back_url = 'scholar_list.php'; // Default fallback if no session exists
if (isset($_SESSION['redirect_back_url'])) {
    $back_url = $_SESSION['redirect_back_url'];
}

// get scholar id
$scholar_id = isset($_GET['scholar_id']) ? (int) $_GET['scholar_id'] : 0;

// Fetch scholar notes
$notes_sql = "SELECT notes FROM scholar_notes WHERE scholar_id = ?";
$notes_stmt = $conn->prepare($notes_sql);
$notes_stmt->bind_param("i", $scholar_id);
$notes_stmt->execute();
$notes_result = $notes_stmt->get_result();

$scholar_notes = [];
while($note_row = $notes_result->fetch_assoc()) {
    $note_content = json_decode($note_row['notes'], true);
    if(!is_array($note_content)) {
        $note_content = ['Note' => $note_row['notes']];
    }
    $scholar_notes[] = $note_content;
}

// scholar info query (include the PSGC ID fields)
$scholar_sql = "SELECT scholar_info.*, 
                scholarship_program.name AS scholarship_program_name,
                scholarship_program.min_gwa_req AS gwa_req,
                program.name AS program_name,
                college_name
                FROM scholar_info 
                INNER JOIN scholarship_program ON scholar_info.scholarship_program_id = scholarship_program.id
                INNER JOIN program ON scholar_info.program_id = program.id
                INNER JOIN college ON program.college_id = college.id
                WHERE scholar_info.id = ?";
$scholar_stmt = $conn->prepare($scholar_sql);
$scholar_stmt->bind_param('i', $scholar_id);
$scholar_stmt->execute();
$scholar_result = $scholar_stmt->get_result();

// initialize variables
$last_name = "";
$given_name = "";
$ext_name = "";
$middle_name = "";
$id_num = "";
$sex = 0;
$birthdate = "";
$scholarship_program = "";
$program = "";
$year_lvl = "";
$college = "";
$academic_status = "";
$school_year = "";
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

// ─── New fields ───
$philsys_id = "";
$lrn = "";
$four_ps_id = "";
$street = "";
$town_city = "";
$province = "";
$country = "";

// PSGC IDs for address breakdown
$barangay_id = 0;
$city_id = 0;
$province_id = 0;
$region_id = 0;

// Placeholders for PSGC names
$barangay_name = "";
$city_name = "";
$province_name = "";
$region_name = "";

if($row = $scholar_result->fetch_assoc()) {
    $last_name = $row['last_name'];
    $given_name = $row['given_name'];
    $ext_name = $row['ext_name'] === "N/A" ? "" : $row['ext_name'];
    $middle_name = $row['middle_name'];
    $id_num = $row['student_id'];
    $sex = $row['sex'] === 0 ? "Male" : "Female";
    $birthdate = $row['birthdate'];
    $scholarship_program = $row['scholarship_program_name'];
    $gwa_req = $row['gwa_req'];
    $program = $row['program_name'];
    $year = $row['year_level'] ?? 0;
    $college = $row['college_name'];
    $academic_status = $row['academic_status'];
    $school_year = $row['school_year'];
    $address = $row['permanent_address'];
    $zipcode = $row['zipcode'];
    $disability = empty($row['disability']) ? "Not Applicable" : $row['disability'];
    $contact_num = $row['contact_num'];
    $email = $row['email'];
    $ip_group = empty($row['ip_group']) ? "Not Applicable" : $row['ip_group'];
    $father_last = $row['father_last_name'];
    $father_given = $row['father_given_name'];
    $father_middle = $row['father_middle_name'];
    $mother_last = $row['mother_last_name'];
    $mother_given = $row['mother_given_name'];
    $mother_middle = $row['mother_middle_name'];
    
    // ─── New fields ───
    $philsys_id = $row['philsys_id'] ?? '';
    $lrn = $row['lrn'] ?? '';
    $four_ps_id = $row['four_ps_id'] ?? '';
    $street = $row['street'] ?? '';
    $town_city = $row['town_city'] ?? '';
    $province = $row['province'] ?? '';
    $country = $row['country'] ?? 'Philippines';

    // PSGC IDs
    $barangay_id = (int) ($row['barangay_id'] ?? 0);
    $city_id = (int) ($row['city_id'] ?? 0);
    $province_id = (int) ($row['province_id'] ?? 0);
    $region_id = (int) ($row['region_id'] ?? 0);

    // Fetch PSGC names for address breakdown
    if($barangay_id > 0) {
        $psgc_sql = "SELECT name FROM psgc WHERE id = $barangay_id";
        $psgc_result = $conn->query($psgc_sql);
        if($psgc_row = $psgc_result->fetch_assoc()) {
            $barangay_name = $psgc_row['name'];
        }
    }
    if($city_id > 0) {
        $psgc_sql = "SELECT name FROM psgc WHERE id = $city_id";
        $psgc_result = $conn->query($psgc_sql);
        if($psgc_row = $psgc_result->fetch_assoc()) {
            $city_name = $psgc_row['name'];
        }
    }
    if($province_id > 0) {
        $psgc_sql = "SELECT name FROM psgc WHERE id = $province_id";
        $psgc_result = $conn->query($psgc_sql);
        if($psgc_row = $psgc_result->fetch_assoc()) {
            $province_name = $psgc_row['name'];
        }
    }
    if($region_id > 0) {
        $psgc_sql = "SELECT name FROM psgc WHERE id = $region_id";
        $psgc_result = $conn->query($psgc_sql);
        if($psgc_row = $psgc_result->fetch_assoc()) {
            $region_name = $psgc_row['name'];
        }
    }

    // conditions for year level
    if($year == 1) {
        $year_lvl = "1st Year";
    } elseif($year == 2) {
        $year_lvl = "2nd Year";
    } elseif($year == 3) {
        $year_lvl = "3rd Year";
    } elseif($year >= 4 && $year <= 6) {
        $year_lvl = $year . "th Year";
    } else {
        $year_lvl = "Not specified";
    }

} else {
    $_SESSION['fail'] = "Scholar not available";
    header("Location: scholar_list.php");
    exit();
}


?>

<!DOCTYPE html>
<html>
<head>
    <title>Scholar Profile - PIT ScholarshipHub</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../assets/css/scholar_profile.css?v=<?= filemtime('../assets/css/scholar_profile.css') ?>">
    <link rel="icon" href="../assets/images/PIT%20LOGO.png">
    <script src="../assets/js/scholar_notes.js"></script>
</head>
<body>
    <?php 
    if(isset($_SESSION['success'])) {
        echo "<script>alert('" . $_SESSION['success'] . "')</script>";
        unset($_SESSION['success']);
    } else if(isset($_SESSION['fail'])) {
        echo "<script>alert('" . $_SESSION['fail'] . "')</script>";
        unset($_SESSION['fail']);
    }
    ?>
    <?php if(!isset($_SESSION['user_id']) || ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'staff')) : ?>
        <script>alert('You are not logged in')</script>
        <script>window.location.href='login.php'</script>
    <?php else : ?>
        <div class="container">
            <!-- Header -->
            <div class="header" >
                <div class="header-left">
                    <img src="../assets/images/PIT%20LOGO.png" alt="PIT Logo">
                    <div>
                        <h1>PIT ScholarshipHub</h1>
                        <p>Scholar Profile</p>
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
                    <?php if(canAccessPage($conn, $_SESSION['user_id'], 'scholars') || canAccessPage($conn, $_SESSION['user_id'], 'scholarship_programs')) : ?>
                        <li class="dropdown">
                            <a href="#" class="dropdown-toggle">Records <div class="arrow-down">▼</div></a>
                            <ul class="dropdown-menu">
                                <?php if(canAccessPage($conn, $_SESSION['user_id'], 'scholars')) : ?>
                                    <li><a href="scholar_list.php" class="active">Scholars</a></li>
                                <?php endif; ?>
                                <?php if(canAccessPage($conn, $_SESSION['user_id'], 'scholarship_programs')) : ?>
                                    <li><a href="scholarship_program.php">Scholarship Programs</a></li>
                                <?php endif; ?>
                                <?php if($_SESSION['role'] == 'admin') : ?>
                                    <li><a href="archive.php">Archives</a></li>
                                <?php endif; ?>
                            </ul>
                        </li>
                    <?php endif; ?>

                    <!-- Import/Export Dropdown -->
                    <?php //if(canAccessPage($conn, $_SESSION['user_id'], 'import_export')) : ?>
                    <?php if(hasPermission($conn, $_SESSION['user_id'], 'import_export', 'import') || hasPermission($conn, $_SESSION['user_id'], 'import_export', 'export')) : ?>
                        <li class="dropdown">
                            <a href="#" class="dropdown-toggle">Import / Export <div class="arrow-down">▼</div></a>
                            <ul class="dropdown-menu">
                                <?php if(hasPermission($conn, $_SESSION['user_id'], 'import_export', 'import')) : ?>
                                    <li><a href="upload_excel.php">Import Excel</a></li>
                                <?php endif; ?>
                                <?php if(hasPermission($conn, $_SESSION['user_id'], 'import_export', 'export')) : ?>
                                    <li><a href="export_scholars.php">Export Excel</a></li>
                                <?php endif; ?>
                            </ul>
                        </li>
                    <?php endif; ?>
                    
                    <!-- Announcements -->
                    <?php if(canAccessPage($conn, $_SESSION['user_id'], 'announcements')) : ?>
                        <li><a href="announcements.php">Announcements</a></li>
                    <?php endif; ?>

                    <!-- Program / College Management -->
                    <?php if(canAccessPage($conn, $_SESSION['user_id'], 'program_management')) : ?>
                        <li><a href="program_management.php">Program & College Management</a></li>
                    <?php endif; ?>
                    
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
                    <h2>Scholar Profile / <?= htmlspecialchars($last_name) ?>, <?= htmlspecialchars($given_name) ?> <?= htmlspecialchars($middle_name) ?> <?= htmlspecialchars($ext_name) ?></h2>
                    <div class="page-actions">
                        <a href="<?= htmlspecialchars($back_url) ?>" class="btn btn-secondary">Back to List</a>
                        <?php if(hasPermission($conn, $_SESSION['user_id'], 'scholars', 'edit')) : ?>
                            <a href="edit_scholar.php?id=<?= $scholar_id ?>" class="btn btn-warning">Edit Scholar</a>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Scholar Profile Content -->
                <div class="profile-grid">
                    <!-- Personal Information -->
                    <div class="profile-card">
                        <h3>Personal Information</h3>
                        <div class="info-group">
                            <div class="info-row">
                                <span class="info-label">Student ID</span>
                                <span class="info-value"><?= htmlspecialchars($id_num) ?></span>
                            </div>
                            <?php if(!empty($philsys_id)) : ?>
                            <div class="info-row">
                                <span class="info-label">PHILSYS ID</span>
                                <span class="info-value"><?= htmlspecialchars($philsys_id) ?></span>
                            </div>
                            <?php endif; ?>
                            <?php if(!empty($lrn)) : ?>
                            <div class="info-row">
                                <span class="info-label">LRN</span>
                                <span class="info-value"><?= htmlspecialchars($lrn) ?></span>
                            </div>
                            <?php endif; ?>
                            <?php if(!empty($four_ps_id)) : ?>
                            <div class="info-row">
                                <span class="info-label">4P's ID</span>
                                <span class="info-value"><?= htmlspecialchars($four_ps_id) ?></span>
                            </div>
                            <?php endif; ?>
                            <div class="info-row">
                                <span class="info-label">Last Name</span>
                                <span class="info-value"><?= htmlspecialchars($last_name) ?></span>
                            </div>
                            <div class="info-row">
                                <span class="info-label">Given Name</span>
                                <span class="info-value"><?= htmlspecialchars($given_name) ?></span>
                            </div>
                            <div class="info-row">
                                <span class="info-label">Middle Name</span>
                                <span class="info-value"><?= htmlspecialchars($middle_name) ?></span>
                            </div>
                            <div class="info-row">
                                <span class="info-label">Extended Name</span>
                                <span class="info-value"><?= empty($ext_name) ? "N/A" : htmlspecialchars($ext_name) ?></span>
                            </div>
                            <div class="info-row">
                                <span class="info-label">Sex</span>
                                <span class="info-value"><?= htmlspecialchars($sex) ?></span>
                            </div>
                            <div class="info-row">
                                <span class="info-label">Birthdate</span>
                                <span class="info-value"><?= htmlspecialchars($birthdate) ?></span>
                            </div>
                            <div class="info-row">
                                <span class="info-label">Disability</span>
                                <span class="info-value"><?= htmlspecialchars($disability) ?></span>
                            </div>
                            <div class="info-row">
                                <span class="info-label">IP Group</span>
                                <span class="info-value"><?= htmlspecialchars($ip_group) ?></span>
                            </div>
                        </div>
                    </div>

                    <!-- Academic Information -->
                    <div class="profile-card">
                        <h3>Scholarship & Academic Information</h3>
                        <div class="info-group">
                            <div class="info-row">
                                <span class="info-label">Scholarship Program</span>
                                <span class="info-value"><?= htmlspecialchars($scholarship_program) ?></span>
                            </div>
                            <div class="info-row">
                                <span class="info-label">Program</span>
                                <span class="info-value"><?= htmlspecialchars($program) ?></span>
                            </div>
                            <div class="info-row">
                                <span class="info-label">College</span>
                                <span class="info-value"><?= htmlspecialchars($college) ?></span>
                            </div>
                            <div class="info-row">
                                <span class="info-label">Year Level</span>
                                <span class="info-value"><?= htmlspecialchars($year_lvl) ?></span>
                            </div>
                            <div class="info-row">
                                <span class="info-label">School Year</span>
                                <span class="info-value"><?= htmlspecialchars($school_year) ?></span>
                            </div>
                            <div class="info-row">
                                <span class="info-label">Academic Status</span>
                                <span class="info-value"><?= htmlspecialchars($academic_status) ?></span>
                            </div>
                        </div>
                    </div>

                    <!-- Contact Information with address breakdown including barangay and region -->
                    <div class="profile-card">
                        <h3>Contact Information</h3>
                        <div class="info-group">
                            <!-- Full address summary -->
                            <div class="info-row">
                                <span class="info-label">Full Address</span>
                                <span class="info-value"><?= htmlspecialchars($address) ?></span>
                            </div>
                            <!-- Address breakdown with barangay and region -->
                            <?php if(!empty($street)) : ?>
                            <div class="info-row">
                                <span class="info-label">Street</span>
                                <span class="info-value"><?= htmlspecialchars($street) ?></span>
                            </div>
                            <?php endif; ?>
                            <?php if(!empty($barangay_name)) : ?>
                            <div class="info-row">
                                <span class="info-label">Barangay</span>
                                <span class="info-value"><?= htmlspecialchars($barangay_name) ?></span>
                            </div>
                            <?php endif; ?>
                            <?php if(!empty($city_name)) : ?>
                            <div class="info-row">
                                <span class="info-label">City / Municipality</span>
                                <span class="info-value"><?= htmlspecialchars($city_name) ?></span>
                            </div>
                            <?php endif; ?>
                            <?php if(!empty($province_name)) : ?>
                            <div class="info-row">
                                <span class="info-label">Province</span>
                                <span class="info-value"><?= htmlspecialchars($province_name) ?></span>
                            </div>
                            <?php endif; ?>
                            <?php if(!empty($region_name)) : ?>
                            <div class="info-row">
                                <span class="info-label">Region</span>
                                <span class="info-value"><?= htmlspecialchars($region_name) ?></span>
                            </div>
                            <?php endif; ?>
                            <!-- Also show the string town_city and province if they exist (fallback) -->
                            <?php if(!empty($town_city) && empty($city_name)) : ?>
                            <div class="info-row">
                                <span class="info-label">Town / City</span>
                                <span class="info-value"><?= htmlspecialchars($town_city) ?></span>
                            </div>
                            <?php endif; ?>
                            <?php if(!empty($province) && empty($province_name)) : ?>
                            <div class="info-row">
                                <span class="info-label">Province</span>
                                <span class="info-value"><?= htmlspecialchars($province) ?></span>
                            </div>
                            <?php endif; ?>
                            <div class="info-row">
                                <span class="info-label">Zipcode</span>
                                <span class="info-value"><?= htmlspecialchars($zipcode) ?></span>
                            </div>
                            <div class="info-row">
                                <span class="info-label">Country</span>
                                <span class="info-value"><?= htmlspecialchars($country) ?></span>
                            </div>
                            <div class="info-row">
                                <span class="info-label">Contact Number</span>
                                <span class="info-value"><?= htmlspecialchars($contact_num) ?></span>
                            </div>
                            <div class="info-row">
                                <span class="info-label">Email</span>
                                <span class="info-value"><?= htmlspecialchars($email) ?></span>
                            </div>
                        </div>
                    </div>

                    <!-- Academic Performance -->
                    <div class="profile-card">
                        <div class="card-header">
                            <h3>Academic Performance</h3>
                            <?php if(hasPermission($conn, $_SESSION['user_id'], 'scholars', 'GWA & units')) : ?>
                                <a href="academic_performance.php?scholar_id=<?= $scholar_id ?>" class="btn btn-sm btn-info">Manage GWA & Units</a>
                            <?php endif; ?>
                        </div>
                        <div class="info-group">
                            <?php 
                            $academic_performance_sql = "SELECT * FROM academic_performance WHERE scholar_id = ? ORDER BY school_year DESC, semester DESC LIMIT 1";
                            $academic_performance_stmt = $conn->prepare($academic_performance_sql);
                            $academic_performance_stmt->bind_param('i', $scholar_id);
                            $academic_performance_stmt->execute();
                            $academic_performance_result = $academic_performance_stmt->get_result();
                            
                            if($academic_performance_result->num_rows > 0) : 
                                $row = $academic_performance_result->fetch_assoc(); ?>
                                <div class="info-row">
                                    <span class="info-label">Semester</span>
                                    <span class="info-value"><?= htmlspecialchars($row['semester']) ?></span>
                                </div>
                                <div class="info-row">
                                    <span class="info-label">GWA</span>
                                    <?php if($gwa_req > 0) : ?>
                                        <?php if($row['gwa'] > $gwa_req) : ?>
                                            <span class="info-value" style="color: red; font-weight: bold;">
                                                <?= htmlspecialchars($row['gwa']) ?> 
                                                <span style="font-size: 13px;">(Requirement: <?= htmlspecialchars($gwa_req) ?> – Not Met)</span>
                                            </span>
                                        <?php else : ?>
                                            <span class="info-value" style="color: green;">
                                                <?= htmlspecialchars($row['gwa']) ?> 
                                                <span style="font-size: 13px;">(Requirement: <?= htmlspecialchars($gwa_req) ?> – Met)</span>
                                            </span>
                                        <?php endif; ?>
                                    <?php else : ?>
                                        <span class="info-value"><?= htmlspecialchars($row['gwa']) ?></span>
                                    <?php endif; ?>
                                </div>
                                <div class="info-row">
                                    <span class="info-label">Units</span>
                                    <span class="info-value"><?= htmlspecialchars($row['units']) ?></span>
                                </div>
                            <?php else: ?>
                                <p class="no-data">No academic records found.</p>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Family Background -->
                    <div class="profile-card">
                        <h3>Family Background</h3>
                        <div class="family-section">
                            <div class="info-group">
                                <div class="info-row">
                                    <span class="info-label">Father's Name</span>
                                    <?php if(!empty($father_last) && !empty($father_given) && !empty($father_middle)) : ?>
                                        <span class="info-value"><?= htmlspecialchars($father_last) ?>, <?= htmlspecialchars($father_given) ?> <?= htmlspecialchars($father_middle) ?></span>
                                    <?php else : ?>
                                        <span class="info-value" style="color: red;">Not Provided</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <div class="family-section">
                            <div class="info-group">
                                <div class="info-row">
                                    <span class="info-label">Mother's Maiden Name</span>
                                    <?php if(!empty($mother_last) && !empty($mother_given) && !empty($mother_middle)) : ?>
                                        <span class="info-value"><?= htmlspecialchars($mother_last) ?>, <?= htmlspecialchars($mother_given) ?> <?= htmlspecialchars($mother_middle) ?></span>
                                    <?php else : ?>
                                        <span class="info-value" style="color: red;">Not Provided</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Scholar Notes Section -->
                    <div class="profile-card notes-card">
                        <div class="card-header">
                            <h3>Notes</h3>
                            <?php if(hasPermission($conn, $_SESSION['user_id'], 'scholars', 'notes')) : ?>
                                <button class="btn btn-sm btn-primary open-modal-btn">+ Add Notes</button>
                            <?php endif; ?>
                        </div>
                        <div class="card-body">
                            <?php if(!empty($scholar_notes)) : ?>
                                <?php foreach($scholar_notes as $note_array) : 
                                    foreach($note_array as $key => $value) : ?>
                                    <div class="note-item">
                                        <div class="note-content">
                                            <strong><?= htmlspecialchars($key) ?>:</strong> <?= htmlspecialchars($value) ?>
                                        </div>
                                        <div class="note-actions">
                                            <form action="../functions/admin/delete_note_process.php" method="POST" 
                                                onsubmit="return confirm('Delete the note \'<?= htmlspecialchars($key) ?>\'?')">
                                                <input type="hidden" name="scholar_id" value="<?= $scholar_id ?>">
                                                <input type="hidden" name="note_title" value="<?= htmlspecialchars($key) ?>">
                                                <?php if(hasPermission($conn, $_SESSION['user_id'], 'scholars', 'notes')) : ?>
                                                    <button type="submit" class="btn-sm btn-danger">Delete</button>
                                                <?php endif; ?>
                                            </form>
                                        </div>
                                    </div>
                                <?php endforeach; endforeach; ?>
                            <?php else : ?>
                                <p class="no-data">No additional notes found for this scholar.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Footer -->
            <div class="footer">
                © 2026 PIT ScholarshipHub. All rights reserved.
            </div>
        </div>

        <!-- Modal for Adding Notes -->
        <div id="noteModal" class="modal">
            <div class="modal-content">
                <div class="modal-header">
                    <h3>Add New Note</h3>
                    <span class="modal-close">&times;</span>
                </div>
                <form id="addNoteForm" action="../functions/admin/add_notes_process.php" method="POST">
                    <input type="hidden" name="scholar_id" value="<?= $scholar_id ?>">
                    <div class="form-group">
                        <label for="note_title">Title</label>
                        <input type="text" id="note_title" name="title" placeholder="e.g., Remarks, Old ID, etc." required>
                    </div>
                    <div class="form-group">
                        <label for="note_content">Content</label>
                        <textarea id="note_content" name="content" rows="4" placeholder="Enter note content here..." required></textarea>
                    </div>
                    <div class="form-actions">
                        <button type="button" class="btn btn-secondary modal-close-btn">Cancel</button>
                        <button type="submit" class="btn btn-primary">Add Note</button>
                    </div>
                </form>
            </div>
        </div>
    <?php endif; ?>
</body>
</html>