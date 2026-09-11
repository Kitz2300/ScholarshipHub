<?php 
include __DIR__ . '/../includes/conn.php';
include '../includes/check_permission.php';
session_start();

// Ensure user is logged in
if(!isset($_SESSION['user_id']) || ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'staff')) {
    $_SESSION['fail'] = "You are not logged in.";
    header("Location: login.php");
    exit();
}

if($_SESSION['role'] !== 'admin') {
    checkPageAccess($conn, $_SESSION['user_id'], 'scholars');
}

// get scholar id
$scholar_id = isset($_GET['scholar_id']) ? (int) $_GET['scholar_id'] : 0;

if($scholar_id === 0) {
    $_SESSION['fail'] = "No scholar selected.";
    header("Location: scholar_list.php");
    exit();
}

// ─── Delete Academic Record Process ───
if(isset($_POST['remove_academic']) && isset($_POST['record_id'])) {
    if($_SESSION['role'] === 'admin' || hasPermission($conn, $_SESSION['user_id'], 'scholars', 'GWA & units')) {
        $record_id = (int) $_POST['record_id'];

        if($record_id > 0) {
            $delete_sql = "DELETE FROM academic_performance WHERE id = ? AND scholar_id = ?";
            $delete_stmt = $conn->prepare($delete_sql);
            $delete_stmt->bind_param('ii', $record_id, $scholar_id);
            
            if($delete_stmt->execute()) {
                $_SESSION['success'] = "Academic record removed successfully!";
            } else {
                $_SESSION['fail'] = "Failed to remove academic record.";
            }
        }
    } else {
        $_SESSION['fail'] = "You do not have permission to delete academic records.";
    }
    header("Location: scholar_profile.php?scholar_id=" . $scholar_id);
    exit();
}

// Handle proper return URL
$current_referrer = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '';

if (!empty($current_referrer) && strpos($current_referrer, 'edit_scholar.php') === false && strpos($current_referrer, 'academic_performance.php') === false && strpos($current_referrer, 'edit_academic_performance.php') === false) {
    $_SESSION['redirect_back_url'] = $current_referrer;
}

$back_url = 'scholar_list.php';
if (isset($_SESSION['redirect_back_url'])) {
    $back_url = $_SESSION['redirect_back_url'];
}

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

// Scholar info query with Funding Provider JOIN
$scholar_sql = "SELECT scholar_info.*, 
                scholarship_program.name AS scholarship_program_name,
                scholarship_program.min_gwa_req AS gwa_req,
                program.name AS program_name,
                college_name,
                providers.name AS provider_name,
                providers.code AS provider_code
                FROM scholar_info 
                INNER JOIN scholarship_program ON scholar_info.scholarship_program_id = scholarship_program.id
                LEFT JOIN providers ON scholarship_program.provider_id = providers.id
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
$sex = "";
$birthdate = "";
$scholarship_program = "";
$provider_display = "";
$gwa_req = 0.00;
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

$philsys_id = "";
$lrn = "";
$four_ps_id = "";
$street = "";
$town_city = "";
$province = "";
$country = "";

$barangay_id = 0;
$city_id = 0;
$province_id = 0;
$region_id = 0;

$barangay_name = "";
$city_name = "";
$province_name = "";
$region_name = "";

if($row = $scholar_result->fetch_assoc()) {
    $last_name = $row['last_name'];
    $given_name = $row['given_name'];
    $raw_ext = trim($row['ext_name'] ?? '');
    $ext_name = (in_array(strtoupper($raw_ext), ['N/A', 'NONE', '']) || empty($raw_ext)) ? '' : $raw_ext;
    $middle_name = $row['middle_name'];
    $id_num = $row['student_id'];
    $sex = $row['sex'] === 0 ? "Male" : "Female";
    $birthdate = $row['birthdate'];
    $scholarship_program = $row['scholarship_program_name'];
    $provider_display = !empty($row['provider_name']) ? $row['provider_name'] . ' (' . $row['provider_code'] . ')' : 'Not Specified';
    $gwa_req = (float) $row['gwa_req'];
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
    
    $philsys_id = trim($row['philsys_id'] ?? '');
    $lrn = trim($row['lrn'] ?? '');
    $four_ps_id = trim($row['four_ps_id'] ?? '');
    $street = $row['street'] ?? '';
    $town_city = $row['town_city'] ?? '';
    $province = $row['province'] ?? '';
    $country = $row['country'] ?? 'Philippines';

    $barangay_id = (int) ($row['barangay_id'] ?? 0);
    $city_id = (int) ($row['city_id'] ?? 0);
    $province_id = (int) ($row['province_id'] ?? 0);
    $region_id = (int) ($row['region_id'] ?? 0);

    if($barangay_id > 0) {
        $psgc_sql = "SELECT name FROM psgc WHERE id = ?";
        $psgc_stmt = $conn->prepare($psgc_sql);
        $psgc_stmt->bind_param("i", $barangay_id);
        $psgc_stmt->execute();
        if($psgc_row = $psgc_stmt->get_result()->fetch_assoc()) {
            $barangay_name = $psgc_row['name'];
        }
    }
    if($city_id > 0) {
        $psgc_sql = "SELECT name FROM psgc WHERE id = ?";
        $psgc_stmt = $conn->prepare($psgc_sql);
        $psgc_stmt->bind_param("i", $city_id);
        $psgc_stmt->execute();
        if($psgc_row = $psgc_stmt->get_result()->fetch_assoc()) {
            $city_name = $psgc_row['name'];
        }
    }
    if($province_id > 0) {
        $psgc_sql = "SELECT name FROM psgc WHERE id = ?";
        $psgc_stmt = $conn->prepare($psgc_sql);
        $psgc_stmt->bind_param("i", $province_id);
        $psgc_stmt->execute();
        if($psgc_row = $psgc_stmt->get_result()->fetch_assoc()) {
            $province_name = $psgc_row['name'];
        }
    }
    if($region_id > 0) {
        $psgc_sql = "SELECT name FROM psgc WHERE id = ?";
        $psgc_stmt = $conn->prepare($psgc_sql);
        $psgc_stmt->bind_param("i", $region_id);
        $psgc_stmt->execute();
        if($psgc_row = $psgc_stmt->get_result()->fetch_assoc()) {
            $region_name = $psgc_row['name'];
        }
    }

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

// ─── Fetch Chronological Academic Data for Chart ───
$chart_labels = [];
$chart_gwa = [];
$chart_sql = "SELECT * FROM academic_performance WHERE scholar_id = ? ORDER BY school_year ASC, semester ASC";
$chart_stmt = $conn->prepare($chart_sql);
$chart_stmt->bind_param('i', $scholar_id);
$chart_stmt->execute();
$chart_res = $chart_stmt->get_result();
while($c_row = $chart_res->fetch_assoc()) {
    $chart_labels[] = $c_row['school_year'] . ' (' . $c_row['semester'] . ')';
    $chart_gwa[] = (float)$c_row['gwa'];
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Scholar Profile - PIT ScholarshipHub</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../assets/css/scholar_profile.css?v=<?= filemtime('../assets/css/scholar_profile.css') ?>">
    <link rel="icon" href="../assets/images/PIT%20LOGO.png">
    <link rel="stylesheet" href="../assets/css/modals.css">
    
    <!-- Chart.js Library -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <script src="../assets/js/scholar_notes.js"></script>
    <script src="../assets/js/scholar_profile.js?v=<?= filemtime('../assets/js/scholar_profile.js') ?>" defer></script>
    <script src="../assets/js/gwa_chart.js?v=<?= filemtime('../assets/js/gwa_chart.js') ?>" defer></script>
</head>
<body>
    <!-- Modal Alert System (Success & Fail Notifications) -->
    <?php include __DIR__ . '/../includes/modal_alerts.php'; ?>

    <div class="container">
        <!-- Header -->
        <div class="header">
            <div class="header-left">
                <img src="../assets/images/PIT%20LOGO.png" alt="PIT Logo">
                <div>
                    <h1>PIT ScholarshipHub</h1>
                    <p>Scholar Profile</p>
                </div>
            </div>
            <div class="header-right">
                <span class="welcome-text">Welcome, <?= $_SESSION['role'] === 'admin' ? 'Admin!' : 'Staff!' ?></span>
                <form action="../functions/logout.php" method="post" style="display: inline;">
                    <button type="submit" class="logout-btn" onclick="return confirmAction(event, 'Are you sure you want to log out?', 'Logout', true)">Logout</button>
                </form>
            </div>
        </div>

        <?php include __DIR__ . "/../includes/navigation.php"; ?>

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
                        
                        <div class="info-row">
                            <span class="info-label">PHILSYS ID</span>
                            <span class="info-value">
                                <?php if (!empty($philsys_id)) : ?>
                                    <?= htmlspecialchars($philsys_id) ?>
                                <?php else : ?>
                                    <span class="info-value">N/A</span>
                                <?php endif; ?>
                            </span>
                        </div>

                        <div class="info-row">
                            <span class="info-label">LRN</span>
                            <span class="info-value">
                                <?php if (!empty($lrn)) : ?>
                                    <?= htmlspecialchars($lrn) ?>
                                <?php else : ?>
                                    <span class="info-value">N/A</span>
                                <?php endif; ?>
                            </span>
                        </div>

                        <div class="info-row">
                            <span class="info-label">4P's ID</span>
                            <span class="info-value">
                                <?php if (!empty($four_ps_id)) : ?>
                                    <?= htmlspecialchars($four_ps_id) ?>
                                <?php else : ?>
                                    <span class="info-value">N/A</span>
                                <?php endif; ?>
                            </span>
                        </div>

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
                            <span class="info-label">Funding Provider</span>
                            <span class="info-value"><?= htmlspecialchars($provider_display) ?></span>
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

                <!-- Combined Academic Performance & Progress Profile Card -->
                <div class="profile-card">
                    <div class="card-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                        <h3 style="margin: 0;">Academic Performance & Progress</h3>
                        <?php if(hasPermission($conn, $_SESSION['user_id'], 'scholars', 'GWA & units')) : ?>
                            <button type="button" class="btn btn-sm btn-primary" onclick="openAddAcadModal()">+ Add Record</button>
                        <?php endif; ?>
                    </div>

                    <!-- GWA Trend Line Chart -->
                    <div style="position: relative; height: 220px; width: 100%; margin-bottom: 20px;">
                        <?php if(!empty($chart_gwa)) : ?>
                            <canvas id="gwaProgressChart" 
                                data-labels='<?= json_encode($chart_labels, JSON_HEX_APOS | JSON_HEX_QUOT) ?>'
                                data-gwa='<?= json_encode($chart_gwa) ?>'
                                data-req='<?= $gwa_req ?>'>
                            </canvas>
                        <?php else : ?>
                            <p class="no-data">No academic performance records available to plot progress.</p>
                        <?php endif; ?>
                    </div>

                    <h4 style="border-bottom: 1px solid rgba(0,0,0,0.08); padding-bottom: 6px; margin-bottom: 12px; font-size: 15px;">Historical Records</h4>

                    <!-- Academic Performance Table -->
                    <div class="academic-table-wrapper">
                        <?php 
                        $academic_sql = "SELECT * FROM academic_performance WHERE scholar_id = ? ORDER BY school_year DESC, semester DESC";
                        $academic_stmt = $conn->prepare($academic_sql);
                        $academic_stmt->bind_param('i', $scholar_id);
                        $academic_stmt->execute();
                        $academic_result = $academic_stmt->get_result();
                        ?>

                        <?php if($academic_result->num_rows > 0) : ?>
                            <table>
                                <thead>
                                    <tr>
                                        <th>Semester</th>
                                        <th>GWA</th>
                                        <th>Units</th>
                                        <th>Year Level</th>
                                        <th>School Year</th>
                                        <?php if(hasPermission($conn, $_SESSION['user_id'], 'scholars', 'GWA & units')) : ?>
                                            <th>Actions</th>
                                        <?php endif; ?>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while($acad_row = $academic_result->fetch_assoc()) : ?>
                                        <tr>
                                            <td><?= htmlspecialchars($acad_row['semester']) ?></td>
                                            <td>
                                                <?= htmlspecialchars($acad_row['gwa']) ?>
                                                <?php if($gwa_req > 0) : ?>
                                                    <?php if((float)$acad_row['gwa'] > $gwa_req) : ?>
                                                        <span style="color: #dc3545; font-size: 11px; font-weight: bold; display: block;">
                                                            (Req: <?= htmlspecialchars($gwa_req) ?> - Not Met)
                                                        </span>
                                                    <?php else : ?>
                                                        <span style="color: #28a745; font-size: 11px; display: block;">
                                                            (Req: <?= htmlspecialchars($gwa_req) ?> - Met)
                                                        </span>
                                                    <?php endif; ?>
                                                <?php endif; ?>
                                            </td>
                                            <td><?= htmlspecialchars($acad_row['units']) ?></td>
                                            <td><?= htmlspecialchars($acad_row['year_level']) ?></td>
                                            <td><?= htmlspecialchars($acad_row['school_year']) ?></td>
                                            <?php if(hasPermission($conn, $_SESSION['user_id'], 'scholars', 'GWA & units')) : ?>
                                                <td>
                                                    <div class="action-buttons">
                                                        <button type="button" class="btn-sm btn-edit" 
                                                            onclick="openEditAcadModal(<?= $acad_row['id'] ?>, '<?= htmlspecialchars($acad_row['semester'], ENT_QUOTES) ?>', '<?= htmlspecialchars($acad_row['gwa'], ENT_QUOTES) ?>', '<?= htmlspecialchars($acad_row['units'], ENT_QUOTES) ?>')">
                                                            Edit
                                                        </button>
                                                        <form action="scholar_profile.php?scholar_id=<?= $scholar_id ?>" method="POST" style="display: inline;">
                                                            <input type="hidden" name="remove_academic" value="1">
                                                            <input type="hidden" name="record_id" value="<?= $acad_row['id'] ?>">
                                                            <button type="submit" class="btn-sm btn-delete" onclick="return confirmAction(event, 'Are you sure you want to delete this academic record?', 'Delete', true)">Delete</button>
                                                        </form>
                                                    </div>
                                                </td>
                                            <?php endif; ?>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        <?php else : ?>
                            <div class="no-data">
                                <p>No academic performance records found for this scholar.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Contact Information -->
                <div class="profile-card">
                    <h3>Contact Information</h3>
                    <div class="info-group">
                        <div class="info-row">
                            <span class="info-label">Full Address</span>
                            <span class="info-value"><?= htmlspecialchars($address) ?></span>
                        </div>
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

                <!-- Family Background -->
                <div class="profile-card">
                    <h3>Family Background</h3>
                    <div class="family-section">
                        <div class="info-group">
                            <div class="info-row">
                                <span class="info-label">Father's Name</span>
                                <?php if(!empty($father_last) && !empty($father_given)) : ?>
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
                                <?php if(!empty($mother_last) && !empty($mother_given)) : ?>
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
                                        <form action="../functions/admin/delete_note_process.php" method="POST">
                                            <input type="hidden" name="scholar_id" value="<?= $scholar_id ?>">
                                            <input type="hidden" name="note_title" value="<?= htmlspecialchars($key, ENT_QUOTES) ?>">
                                            <?php if(hasPermission($conn, $_SESSION['user_id'], 'scholars', 'notes')) : ?>
                                                <button type="submit" class="btn-sm btn-danger" onclick="return confirmAction(event, 'Are you sure you want to delete the note \'<?= htmlspecialchars($key, ENT_QUOTES) ?>\'?', 'Delete', true)">Delete</button>
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

    <!-- Add Academic Performance Modal -->
    <?php if(hasPermission($conn, $_SESSION['user_id'], 'scholars', 'GWA & units')) : ?>
        <div id="addAcadModal" class="modal">
            <div class="modal-content">
                <div class="modal-header">
                    <h3>Add Academic Performance</h3>
                    <span class="modal-close" onclick="closeAcadModals()">&times;</span>
                </div>
                <form action="../functions/admin/add_academic_performance_process.php" method="POST">
                    <input type="hidden" name="scholar_id" value="<?= $scholar_id ?>">
                    <div class="form-group">
                        <label for="add_sem">Semester <span class="required">*</span></label>
                        <select name="sem" id="add_sem" required>
                            <option value="" selected disabled>--Select Semester--</option>
                            <option value="1st Semester">1st Semester</option>
                            <option value="2nd Semester">2nd Semester</option>
                            <option value="3rd Semester">3rd Semester</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="add_gwa">General Weighted Average (GWA) <span class="required">*</span></label>
                        <input type="number" name="gwa" id="add_gwa" step="0.01" min="1.00" max="4.00" placeholder="e.g., 1.75" required/>
                    </div>
                    <div class="form-group">
                        <label for="add_units">Units <span class="required">*</span></label>
                        <input type="number" name="units" id="add_units" step="0.5" min="1" placeholder="e.g., 20.0" required/>
                    </div>
                    <div class="form-actions">
                        <button type="button" class="btn btn-secondary" onclick="closeAcadModals()">Cancel</button>
                        <button type="submit" name="add" class="btn btn-success">Add Record</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Edit Academic Performance Modal -->
        <div id="editAcadModal" class="modal">
            <div class="modal-content">
                <div class="modal-header">
                    <h3>Edit Academic Performance</h3>
                    <span class="modal-close" onclick="closeAcadModals()">&times;</span>
                </div>
                <form action="../functions/admin/edit_academic_performance_process.php" method="POST">
                    <input type="hidden" name="scholar_id" value="<?= $scholar_id ?>">
                    <input type="hidden" name="record_id" id="edit_acad_record_id">
                    <div class="form-group">
                        <label for="edit_sem">Semester <span class="required">*</span></label>
                        <select name="sem" id="edit_sem" required>
                            <option value="1st Semester">1st Semester</option>
                            <option value="2nd Semester">2nd Semester</option>
                            <option value="3rd Semester">3rd Semester</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="edit_gwa">General Weighted Average (GWA) <span class="required">*</span></label>
                        <input type="number" name="gwa" id="edit_gwa" step="0.01" min="1.00" max="4.00" placeholder="e.g., 1.75" required/>
                    </div>
                    <div class="form-group">
                        <label for="edit_units">Units <span class="required">*</span></label>
                        <input type="number" name="units" id="edit_units" step="0.5" min="1" placeholder="e.g., 20.0" required/>
                    </div>
                    <div class="form-actions">
                        <button type="button" class="btn btn-secondary" onclick="closeAcadModals()">Cancel</button>
                        <button type="submit" name="update" class="btn btn-success">Update Record</button>
                    </div>
                </form>
            </div>
        </div>
    <?php endif; ?>
</body>
</html>