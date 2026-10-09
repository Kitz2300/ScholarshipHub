<?php 
include __DIR__ . '/../includes/conn.php';
session_start();

// Helper function to turn plain URLs into clickable links safely
if (!function_exists('makeLinksClickable')) {
    function makeLinksClickable($text) {
        $safe_text = htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
        $pattern = '/(https?:\/\/[^\s<]+)/i';
        $replacement = '<a href="$1" target="_blank" rel="noopener noreferrer">$1</a>';
        return preg_replace($pattern, $replacement, $safe_text);
    }
}

// Helper function to format attachment list (handles single string and JSON array paths)
if (!function_exists('getAttachmentList')) {
    function getAttachmentList($raw_path) {
        if (empty($raw_path)) return [];

        $decoded = json_decode($raw_path, true);
        $items = is_array($decoded) ? $decoded : [$raw_path];

        $attachments = [];
        foreach ($items as $path) {
            if (empty($path)) continue;

            $clean_path = ltrim(str_replace('../', '', $path), '/');
            $url = preg_match('~^https?://~i', $path) ? $path : '../' . $clean_path;
            $filename = basename($path);

            $attachments[] = [
                'url' => $url,
                'filename' => $filename
            ];
        }

        return $attachments;
    }
}

// ─── CHECK STUDENT AUTHORIZATION ───
if (!isset($_SESSION['role'])) {
    header("Location: login.php");
    exit();
}
if ($_SESSION['role'] !== 'student') {
    header("Location: login.php");
    exit();
}

$session_scholar_id = (int) ($_SESSION['user_id'] ?? 0);

if ($session_scholar_id === 0) {
    $_SESSION['fail'] = "Invalid user session.";
    header("Location: login.php");
    exit();
}

// ─── 1. FETCH STUDENT IDENTIFIER (student_id) ───
$id_lookup_sql = "SELECT student_id FROM scholar_info WHERE id = ? LIMIT 1";
$id_lookup_stmt = $conn->prepare($id_lookup_sql);
$id_lookup_stmt->bind_param("i", $session_scholar_id);
$id_lookup_stmt->execute();
$id_lookup_res = $id_lookup_stmt->get_result();

if ($id_row = $id_lookup_res->fetch_assoc()) {
    $student_id_num = $id_row['student_id'];
} else {
    $_SESSION['fail'] = "Scholar record not found.";
    header("Location: login.php");
    exit();
}
$id_lookup_stmt->close();

// ─── 2. FETCH ALL AVAILABLE TERM SNAPSHOTS ───
$terms_sql = "SELECT id, school_year, semester, year_level, academic_status 
              FROM scholar_info 
              WHERE student_id = ? AND is_archived = 0 
              ORDER BY REPLACE(school_year, ' ', '') DESC, 
                       CASE 
                           WHEN semester = 'Summer' THEN 3
                           WHEN semester = '2nd Semester' THEN 2
                           WHEN semester = '1st Semester' THEN 1
                           ELSE 0
                       END DESC";
$terms_stmt = $conn->prepare($terms_sql);
$terms_stmt->bind_param("s", $student_id_num);
$terms_stmt->execute();
$terms_res = $terms_stmt->get_result();

$available_terms = [];
while ($t_row = $terms_res->fetch_assoc()) {
    $available_terms[] = $t_row;
}
$terms_stmt->close();

if (empty($available_terms)) {
    $_SESSION['fail'] = "No active term records found for this scholar.";
    header("Location: login.php");
    exit();
}

// ─── 3. DETERMINE SELECTED TERM SNAPSHOT ID ───
$selected_scholar_id = isset($_GET['term_id']) ? (int)$_GET['term_id'] : 0;

$valid_selected = false;
foreach ($available_terms as $term) {
    if ((int)$term['id'] === $selected_scholar_id) {
        $valid_selected = true;
        break;
    }
}

if (!$valid_selected) {
    $selected_scholar_id = (int)$available_terms[0]['id'];
}

// ─── 4. FETCH SCHOLAR NOTES ACROSS ALL TERMS ───
$notes_sql = "SELECT sn.notes 
              FROM scholar_notes sn 
              INNER JOIN scholar_info si ON sn.scholar_id = si.id 
              WHERE si.student_id = ?";
$notes_stmt = $conn->prepare($notes_sql);
$notes_stmt->bind_param("s", $student_id_num);
$notes_stmt->execute();
$notes_result = $notes_stmt->get_result();

$scholar_notes = [];
while ($note_row = $notes_result->fetch_assoc()) {
    $note_content = json_decode($note_row['notes'], true);
    if (!is_array($note_content)) {
        $note_content = ['Note' => $note_row['notes']];
    }
    $scholar_notes[] = $note_content;
}
$notes_stmt->close();

// ─── 5. FETCH SELECTED TERM SNAPSHOT INFORMATION ───
$scholar_sql = "SELECT scholar_info.*, 
                scholarship_program.name AS scholarship_program_name,
                scholarship_program.min_gwa_req AS gwa_req,
                scholarship_program.pay_amount,
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
$scholar_stmt->bind_param('i', $selected_scholar_id);
$scholar_stmt->execute();
$scholar_result = $scholar_stmt->get_result();

if ($row = $scholar_result->fetch_assoc()) {
    $scholarship_program_id = (int) $row['scholarship_program_id'];
    $last_name = $row['last_name'];
    $given_name = $row['given_name'];
    $raw_ext = trim($row['ext_name'] ?? '');
    $ext_name = (in_array(strtoupper($raw_ext), ['N/A', 'NONE', '']) || empty($raw_ext)) ? '' : $raw_ext;
    $middle_name = $row['middle_name'];
    $id_num = $row['student_id'];
    $sex = ($row['sex'] === 0) ? "Male" : "Female";
    $birthdate = $row['birthdate'];
    $scholarship_program = $row['scholarship_program_name'];
    $provider_display = !empty($row['provider_name']) ? $row['provider_name'] . ' (' . $row['provider_code'] . ')' : 'Not Specified';
    $gwa_req = (float) $row['gwa_req'];
    $allowance = (float) ($row['pay_amount'] ?? 0);
    $program = $row['program_name'];
    $year_lvl = !empty($row['year_level']) ? $row['year_level'] : "Not specified";
    $college = $row['college_name'];
    $academic_status = $row['academic_status'];
    $school_year = $row['school_year'];
    $semester = $row['semester'];
    $address = $row['permanent_address'];
    $zipcode = $row['zipcode'];
    $disability = empty($row['disability']) ? "Not Applicable" : $row['disability'];
    $contact_num = $row['contact_num'];
    $email = $row['email'];
    $ip_group = empty($row['ip_group']) ? "Not Applicable" : $row['ip_group'];
    
    // Classifications
    $solo_parent_raw = $row['is_solo_parent'] ?? $row['solo_parent'] ?? 0;
    $solo_parent = ($solo_parent_raw == 1 || strtolower((string)$solo_parent_raw) === 'yes') ? "Yes" : "No";

    $raised_solo_parent_raw = $row['is_raised_by_solo_parent'] ?? $row['raised_by_solo_parent'] ?? $row['is_child_of_solo_parent'] ?? 0;
    $raised_solo_parent = ($raised_solo_parent_raw == 1 || strtolower((string)$raised_solo_parent_raw) === 'yes') ? "Yes" : "No";

    $gida_raw = $row['gida'] ?? $row['is_gida'] ?? $row['gida_details'] ?? '';
    if (!empty($gida_raw) && $gida_raw !== '0') {
        $gida = (is_numeric($gida_raw) && (int)$gida_raw === 1) ? "Yes" : $gida_raw;
    } else {
        $gida = "Not Applicable";
    }

    $farmer_fisherfolk_raw = $row['farmer_fisherfolk'] ?? $row['is_farmer_fisherfolk'] ?? $row['is_farmer_fisherfolks'] ?? 0;
    $farmer_fisherfolk = ($farmer_fisherfolk_raw == 1 || strtolower((string)$farmer_fisherfolk_raw) === 'yes') ? "Yes" : "No";

    $rebel_returnee_raw = $row['rebel_returnee'] ?? $row['is_rebel_returnee'] ?? 0;
    $rebel_returnee = ($rebel_returnee_raw == 1 || strtolower((string)$rebel_returnee_raw) === 'yes') ? "Yes" : "No";

    // Family & Parents Details
    $father_last = $row['father_last_name'] ?? '';
    $father_given = $row['father_given_name'] ?? '';
    $father_middle = $row['father_middle_name'] ?? '';
    $mother_last = $row['mother_last_name'] ?? '';
    $mother_given = $row['mother_given_name'] ?? '';
    $mother_middle = $row['mother_middle_name'] ?? '';
    
    $father_contact = trim($row['father_contact_num'] ?? $row['father_contact'] ?? '');
    $mother_contact = trim($row['mother_contact_num'] ?? $row['mother_contact'] ?? '');

    $philsys_id = trim($row['philsys_id'] ?? '');
    $lrn = trim($row['lrn'] ?? '');
    $four_ps_id = trim($row['four_ps_id'] ?? '');
    $street = $row['street'] ?? '';
    $town_city = $row['town_city'] ?? '';
    $province = $row['province'] ?? '';
    $country = $row['country'] ?? 'Philippines';

    // PROFILE PICTURE RESOLUTION
    $profile_pic = trim($row['profile_picture'] ?? '');

    if (!empty($profile_pic) && preg_match("~^https?://~i", $profile_pic)) {
        $pic_path = $profile_pic;
        $has_picture = true;
    } elseif (!empty($profile_pic) && file_exists(__DIR__ . '/../assets/images/profile_picture/' . $profile_pic)) {
        $pic_path = '../assets/images/profile_picture/' . $profile_pic . '?v=' . time();
        $has_picture = true;
    } else {
        $pic_path = '';
        $has_picture = false;
    }

} else {
    $_SESSION['fail'] = "Scholar information not available.";
    header("Location: login.php");
    exit();
}
$scholar_stmt->close();

// ─── 6. CHECK PASSWORD CHANGED STATUS ACROSS ALL SNAPSHOT ROWS ───
$pass_check_sql = "SELECT MAX(COALESCE(is_password_changed, 0)) AS max_changed 
                   FROM scholar_info 
                   WHERE student_id = ?";
$pass_check_stmt = $conn->prepare($pass_check_sql);
$pass_check_stmt->bind_param("s", $student_id_num);
$pass_check_stmt->execute();
$pass_check_res = $pass_check_stmt->get_result();

$is_password_changed = 0;
if ($p_row = $pass_check_res->fetch_assoc()) {
    $is_password_changed = (int) $p_row['max_changed'];
}
$pass_check_stmt->close();

// ─── 7. FETCH ANNOUNCEMENTS WITH ATTACHMENT & LATEST GWA ───
$announcements_sql = "SELECT 
                        announcements.title, 
                        announcements.content, 
                        announcements.attachment,
                        announcements.created_at, 
                        sp.name AS scholarship_program_name 
                      FROM announcements 
                      LEFT JOIN scholarship_program sp ON announcements.scholarship_program_id = sp.id 
                      WHERE announcements.is_active = 1 
                        AND (announcements.scholarship_program_id IS NULL 
                             OR announcements.scholarship_program_id = 0 
                             OR announcements.scholarship_program_id = ?) 
                      ORDER BY announcements.created_at DESC";
$announcements_stmt = $conn->prepare($announcements_sql);
$announcements_stmt->bind_param("i", $scholarship_program_id);
$announcements_stmt->execute();
$announcements_result = $announcements_stmt->get_result();

$scholar_announcements = [];
while ($ann = $announcements_result->fetch_assoc()) {
    $scholar_announcements[] = $ann;
}
$announcements_stmt->close();

$latest_gwa = null;
$is_flagged = false;
$gwa_sql = "SELECT ap.gwa 
            FROM academic_performance ap
            INNER JOIN scholar_info si ON ap.scholar_id = si.id
            WHERE si.student_id = ? 
            ORDER BY REPLACE(ap.school_year, ' ', '') DESC, ap.semester DESC LIMIT 1";
$gwa_stmt = $conn->prepare($gwa_sql);
$gwa_stmt->bind_param('s', $student_id_num);
$gwa_stmt->execute();
$gwa_res = $gwa_stmt->get_result();
if ($gwa_row = $gwa_res->fetch_assoc()) {
    $latest_gwa = (float)$gwa_row['gwa'];
    if ($gwa_req > 0 && $latest_gwa > $gwa_req) {
        $is_flagged = true;
    }
}
$gwa_stmt->close();

$full_name = trim("$given_name $middle_name $last_name $ext_name");
$initials = strtoupper(substr($given_name, 0, 1) . substr($last_name, 0, 1));
$status_slug = strtolower(str_replace(' ', '-', trim($academic_status)));
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0">
    <title>My Profile - PIT ScholarshipHub</title>
    <link rel="stylesheet" href="../assets/css/profile.css?v=<?= filemtime('../assets/css/profile.css') ?>">
    <link rel="icon" href="../assets/images/PIT%20LOGO.png">
    <link rel="stylesheet" href="../assets/css/modals.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
    <?php include __DIR__ . "/../includes/modal_alerts.php"; ?>
    <div class="container">
        <!-- Header -->
        <header class="header">
            <div class="header-left">
                <img src="../assets/images/PIT%20LOGO.png" alt="PIT Logo">
                <div class="header-title-box">
                    <h1>PIT ScholarshipHub</h1>
                    <p>My Profile</p>
                </div>
            </div>
            <div class="header-right">
                <span class="welcome-text">Welcome, <?= htmlspecialchars($given_name) ?>!</span>
                <form action="../functions/logout.php" method="post" style="display: inline;">
                    <button type="submit" class="logout-btn" onclick="return confirmAction(event, 'Are you sure you want to log out?', 'Logout', true)">Logout</button>
                </form>
            </div>
        </header>

        <!-- Sticky Navigation Bar -->
        <nav class="nav-bar">
            <ul>
                <li><a href="#sec-personal">Personal Info</a></li>
                <li><a href="#sec-announcements">Announcements</a></li>
                <li><a href="#sec-contact">Contact Details</a></li>
                <li><a href="#sec-family-notes">Family Background & Notes</a></li>
                <?php if ($gwa_req > 0) : ?>
                    <li><a href="#sec-academic">Academic Performance</a></li>
                <?php endif; ?>
            </ul>
        </nav>

        <!-- Page Content -->
        <main class="page-content">
            
            <!-- Hero Scholar Cards Grid -->
            <div class="hero-grid">
                <!-- Left Hero Card (Profile Picture, Details & Actions) -->
                <div class="hero-card hero-card-info">
                    <div class="avatar-wrapper" onclick="openPhotoModal()" title="Click to change photo">
                        <?php if ($has_picture): ?>
                            <img src="<?= htmlspecialchars($pic_path) ?>" alt="Profile Picture" class="hero-avatar-img">
                        <?php else: ?>
                            <div class="hero-avatar"><?= $initials ?></div>
                        <?php endif; ?>
                        <div class="avatar-overlay">📷 Edit</div>
                    </div>
                    <div class="hero-details">
                        <h2><?= htmlspecialchars($full_name) ?></h2>
                        <p><strong>Student ID:</strong> <?= htmlspecialchars($id_num) ?></p>
                        <p><strong>College:</strong> <?= htmlspecialchars($college) ?></p>
                        <p><strong>Program:</strong> <?= htmlspecialchars($program) ?></p>

                        <!-- Academic Status Indicator, Flagged Badge & Password Change Button -->
                        <div class="hero-details-action-row">
                            <span class="status-badge status-<?= $status_slug ?>">
                                <?= htmlspecialchars($academic_status) ?>
                            </span>
                            <?php if ($is_flagged): ?>
                                <span class="status-badge status-flagged">
                                    ⚠️ Below GWA Requirement
                                </span>
                            <?php endif; ?>
                            <button type="button" class="btn btn-warning btn-password-change" onclick="openPasswordModal()">🔒 Change Password</button>
                        </div>
                    </div>
                </div>

                <!-- Right Hero Card (Term Snapshot Selector Widget) -->
                <div class="hero-card hero-card-actions">
                    <form action="profile.php" method="GET" class="term-selector-form">
                        <div class="hero-term-selector">
                            <div class="term-selector-header">
                                <div class="term-icon-box">📅</div>
                                <div class="term-header-text">
                                    <span class="term-subtitle">Academic View</span>
                                    <h3>Term Snapshot</h3>
                                </div>
                            </div>

                            <div class="select-wrapper">
                                <select name="term_id" id="term_id" onchange="this.form.submit()">
                                    <?php foreach ($available_terms as $t) : 
                                        $t_yl = str_replace(' Year', ' Yr', $t['year_level'] ?? '');
                                    ?>
                                        <option value="<?= $t['id'] ?>" <?= $t['id'] == $selected_scholar_id ? 'selected' : '' ?>>
                                            SY <?= htmlspecialchars($t['school_year']) ?> — <?= htmlspecialchars($t['semester']) ?> (<?= htmlspecialchars($t_yl) ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <span class="select-arrow">▼</span>
                            </div>

                            <div class="term-selector-footer">
                                <span class="pulse-dot"></span> Viewing records for this snapshot
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Key Metric KPI Bar -->
            <div class="kpi-grid">
                <div class="kpi-card">
                    <div class="kpi-title">Scholarship Program</div>
                    <div class="kpi-value"><?= htmlspecialchars($scholarship_program) ?></div>
                    <div class="kpi-sub">Provider: <?= htmlspecialchars($provider_display) ?></div>
                </div>
                <div class="kpi-card">
                    <div class="kpi-title">Allowance / Payout</div>
                    <div class="kpi-value">₱ <?= number_format($allowance, 2) ?></div>
                    <div class="kpi-sub">Per Semester Payout</div>
                </div>
                <div class="kpi-card">
                    <div class="kpi-title">Academic Standing</div>
                    <div class="kpi-value"><?= htmlspecialchars($year_lvl) ?></div>
                    <div class="kpi-sub">SY <?= htmlspecialchars($school_year) ?> (<?= htmlspecialchars($semester) ?>)</div>
                </div>
                <?php if ($gwa_req > 0) : ?>
                    <div class="kpi-card">
                        <div class="kpi-title">General Weighted Average (GWA)</div>
                        <div class="kpi-value"><?= $latest_gwa !== null ? htmlspecialchars($latest_gwa) : 'No Record' ?></div>
                        <div class="kpi-sub">Min Required: <?= htmlspecialchars($gwa_req) ?></div>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Two-Column Layout 1 -->
            <div class="layout-two-col mb-20">
                <div class="profile-card" id="sec-personal">
                    <h3>Personal Information & Classifications</h3>
                    <div class="info-group">
                        <div class="info-row">
                            <span class="info-label">PhilSys ID</span>
                            <span class="info-value"><?= !empty($philsys_id) ? htmlspecialchars($philsys_id) : 'N/A' ?></span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">LRN</span>
                            <span class="info-value"><?= !empty($lrn) ? htmlspecialchars($lrn) : 'N/A' ?></span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">4P's ID</span>
                            <span class="info-value"><?= !empty($four_ps_id) ? htmlspecialchars($four_ps_id) : 'N/A' ?></span>
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
                            <span class="info-label">Disability (PWD)</span>
                            <span class="info-value"><?= htmlspecialchars($disability) ?></span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">IP Group</span>
                            <span class="info-value"><?= htmlspecialchars($ip_group) ?></span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Solo Parent</span>
                            <span class="info-value"><?= htmlspecialchars($solo_parent) ?></span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Raised by Solo Parent</span>
                            <span class="info-value"><?= htmlspecialchars($raised_solo_parent) ?></span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">GIDA Resident</span>
                            <span class="info-value"><?= htmlspecialchars($gida) ?></span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Farmer / Fisherfolk Family</span>
                            <span class="info-value"><?= htmlspecialchars($farmer_fisherfolk) ?></span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Rebel Returnee Family</span>
                            <span class="info-value"><?= htmlspecialchars($rebel_returnee) ?></span>
                        </div>
                    </div>
                </div>

                <div class="right-column-stack">
                    <div class="profile-card mb-20" id="sec-announcements">
                        <div class="card-header mb-10">
                            <h3>📢 Announcements</h3>
                        </div>
                        <?php if (!empty($scholar_announcements)) : ?>
                            <div class="announcements-list-compact">
                                <?php foreach ($scholar_announcements as $index => $item) : 
                                    $addressed_to = !empty($item['scholarship_program_name']) ? $item['scholarship_program_name'] : "All Scholarship Programs";
                                    $attachments = getAttachmentList($item['attachment']);
                                ?>
                                    <div class="announcement-title-item" onclick="showAnnouncementModal(<?= $index ?>)" title="Click to view full announcement content">
                                        <div class="title-header-row">
                                            <div class="title-text"><?= htmlspecialchars($item['title']) ?></div>
                                            <span class="click-hint-badge">Click to view content ➔</span>
                                        </div>
                                        <div class="title-date">Posted: <?= date('M d, Y', strtotime($item['created_at'])) ?> | Addressed To: <?= htmlspecialchars($addressed_to) ?></div>
                                    </div>

                                    <template id="ann-content-<?= $index ?>">
                                        <div class="ann-data-title"><?= htmlspecialchars($item['title']) ?></div>
                                        <div class="ann-data-meta">Date Posted: <?= date('F d, Y', strtotime($item['created_at'])) ?> | Addressed To: <?= htmlspecialchars($addressed_to) ?></div>
                                        <div class="ann-data-body"><?= makeLinksClickable($item['content']) ?></div>
                                        <?php if (!empty($attachments)): ?>
                                            <div class="ann-data-attachment" style="margin-top: 15px; padding: 10px; background: rgba(0,0,0,0.03); border: 1px dashed #2a6b37; border-radius: 6px;">
                                                <strong>📎 File Attachments (<?= count($attachments) ?>):</strong> 
                                                <div style="display: flex; flex-wrap: wrap; gap: 8px; margin-top: 6px;">
                                                    <?php foreach ($attachments as $att) : ?>
                                                        <a href="<?= htmlspecialchars($att['url']) ?>" target="_blank" rel="noopener noreferrer" style="color: #1a73e8; font-weight: bold; text-decoration: underline; background: #ffffff; padding: 4px 10px; border-radius: 4px; border: 1px solid #ccc; font-size: 13px; display: inline-flex; align-items: center; gap: 4px;">
                                                            📎 <?= htmlspecialchars($att['filename']) ?>
                                                        </a>
                                                    <?php endforeach; ?>
                                                </div>
                                            </div>
                                        <?php endif; ?>
                                    </template>
                                <?php endforeach; ?>
                            </div>
                        <?php else : ?>
                            <div class="no-data">
                                <p>No active announcements available for your scholarship program.</p>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="profile-card" id="sec-contact">
                        <h3>Contact & Address Details</h3>
                        <div class="info-group">
                            <div class="info-row">
                                <span class="info-label">Email</span>
                                <span class="info-value"><?= htmlspecialchars($email) ?></span>
                            </div>
                            <div class="info-row">
                                <span class="info-label">Contact Number</span>
                                <span class="info-value"><?= htmlspecialchars($contact_num) ?></span>
                            </div>
                            <div class="info-row">
                                <span class="info-label">Permanent Address</span>
                                <span class="info-value"><?= htmlspecialchars($address) ?></span>
                            </div>
                            <div class="info-row">
                                <span class="info-label">Zipcode / Country</span>
                                <span class="info-value"><?= htmlspecialchars($zipcode) ?>, <?= htmlspecialchars($country) ?></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Two-Column Layout 2 -->
            <div class="layout-two-col mb-20" id="sec-family-notes">
                <div class="profile-card">
                    <h3>Family Background</h3>
                    <div class="info-group">
                        <div class="info-row">
                            <span class="info-label">Father's Name</span>
                            <span class="info-value">
                                <?= (!empty($father_last) && !empty($father_given)) ? htmlspecialchars("$father_last, $father_given $father_middle") : 'Not Provided' ?>
                            </span>
                        </div>
                        <?php if (!empty($father_contact)) : ?>
                            <div class="info-row">
                                <span class="info-label">Father's Contact</span>
                                <span class="info-value"><?= htmlspecialchars($father_contact) ?></span>
                            </div>
                        <?php endif; ?>

                        <div class="info-row">
                            <span class="info-label">Mother's Maiden Name</span>
                            <span class="info-value">
                                <?= (!empty($mother_last) && !empty($mother_given)) ? htmlspecialchars("$mother_last, $mother_given $mother_middle") : 'Not Provided' ?>
                            </span>
                        </div>
                        <?php if (!empty($mother_contact)) : ?>
                            <div class="info-row">
                                <span class="info-label">Mother's Contact</span>
                                <span class="info-value"><?= htmlspecialchars($mother_contact) ?></span>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="profile-card">
                    <h3>Additional Notes & Information</h3>
                    <div class="info-group">
                        <?php if (!empty($scholar_notes)) : ?>
                            <?php foreach ($scholar_notes as $note_array) : 
                                foreach ($note_array as $key => $value) : ?>
                                <div class="info-row">
                                    <div class="info-value">
                                        <strong><?= htmlspecialchars($key) ?>:</strong> <?= htmlspecialchars($value) ?>
                                    </div>
                                </div>
                            <?php endforeach; endforeach; ?>
                        <?php else : ?>
                            <div class="no-data">
                                <p>No additional notes available.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Full-Width Academic Performance across all terms (Rendered only if Program Has GWA Requirement) -->
            <?php if ($gwa_req > 0) : ?>
                <div class="profile-card mt-20" id="sec-academic">
                    <div class="card-header mb-20">
                        <h3>📈 Academic Performance & GWA Trend</h3>
                    </div>

                    <?php 
                    $academic_sql = "SELECT ap.*, 
                                        CASE 
                                            WHEN ap.semester LIKE '%1st%' THEN 1 
                                            WHEN ap.semester LIKE '%2nd%' THEN 2 
                                            WHEN ap.semester LIKE '%Summer%' THEN 3 
                                            ELSE 4 
                                        END as sem_order 
                                     FROM academic_performance ap
                                     INNER JOIN scholar_info si ON ap.scholar_id = si.id
                                     WHERE si.student_id = ? 
                                     ORDER BY REPLACE(ap.school_year, ' ', '') ASC, sem_order ASC";
                    $academic_stmt = $conn->prepare($academic_sql);
                    $academic_stmt->bind_param('s', $student_id_num);
                    $academic_stmt->execute();
                    $academic_result = $academic_stmt->get_result();

                    $gwa_labels = [];
                    $gwa_values = [];
                    $req_values = [];
                    $academic_history = [];

                    while ($acad_row = $academic_result->fetch_assoc()) {
                        $academic_history[] = $acad_row;
                        $gwa_labels[] = str_replace(' Year', ' Yr', $acad_row['year_level']) . " - " . $acad_row['semester'];
                        $gwa_values[] = (float) $acad_row['gwa'];
                        $req_values[] = $gwa_req;
                    }
                    $academic_stmt->close();
                    ?>

                    <?php if (!empty($academic_history)) : ?>
                        <div class="gwa-chart-container">
                            <canvas id="gwaTrendChart"></canvas>
                        </div>

                        <div class="academic-table-wrapper mt-20">
                            <table class="responsive-table">
                                <thead>
                                    <tr>
                                        <th>Semester</th>
                                        <th>GWA</th>
                                        <th>Units Completed</th>
                                        <th>Year Level</th>
                                        <th>School Year</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach (array_reverse($academic_history) as $acad_row) : ?>
                                        <tr>
                                            <td data-label="Semester"><strong><?= htmlspecialchars($acad_row['semester']) ?></strong></td>
                                            <td data-label="GWA"><strong><?= number_format((float)$acad_row['gwa'], 2) ?></strong></td>
                                            <td data-label="Units"><?= htmlspecialchars($acad_row['units']) ?></td>
                                            <td data-label="Year Level"><?= htmlspecialchars($acad_row['year_level']) ?></td>
                                            <td data-label="School Year"><?= htmlspecialchars($acad_row['school_year']) ?></td>
                                            <td data-label="Status">
                                                <?php if ((float)$acad_row['gwa'] > $gwa_req) : ?>
                                                    <span class="req-unmet">⚠️ Requirement Not Met</span>
                                                <?php else : ?>
                                                    <span class="req-met">✓ Requirement Met</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else : ?>
                        <div class="no-data">
                            <p>No academic performance records found to generate trend chart.</p>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

        </main>

        <footer class="footer">
            © 2026 PIT ScholarshipHub. All rights reserved.
        </footer>
    </div>

    <!-- Announcement Details Modal -->
    <div id="announcementModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 id="annModalTitle">Announcement Details</h3>
                <span class="modal-close" onclick="closeModal('announcementModal')">&times;</span>
            </div>
            <div class="modal-body" style="padding-top: 10px;">
                <span id="annModalMeta" class="detail-date-badge"></span>
                <hr class="detail-divider">
                <div id="annModalContent" class="detail-content"></div>
                <div id="annModalAttachment"></div>
            </div>
        </div>
    </div>

    <!-- Change Password Modal -->
    <div id="passwordModal" class="modal <?= $is_password_changed === 0 ? 'show' : '' ?>">
        <div class="modal-content">
            <div class="modal-header">
                <h3><?= $is_password_changed === 0 ? '🔒 Password Change Required' : 'Change Password' ?></h3>
                <?php if ($is_password_changed === 1): ?>
                    <span class="modal-close" onclick="closeModal('passwordModal')">&times;</span>
                <?php endif; ?>
            </div>

            <?php if ($is_password_changed === 0): ?>
                <div class="notice-box">
                    <strong>First-Time Login Notice:</strong> For security reasons, you must change your initial password before accessing your account dashboard.
                </div>
            <?php endif; ?>

            <form action="../functions/student/change_password_process.php" method="POST" onsubmit="return validatePasswordChange(event)">
                <div class="form-group">
                    <label for="current_password">Current / Temporary Password <span class="required">*</span></label>
                    <input type="password" name="current_password" id="current_password" required>
                </div>
                <div class="form-group">
                    <label for="new_password">New Password <span class="required">*</span></label>
                    <input type="password" name="new_password" id="new_password" minlength="8" placeholder="Minimum 8 characters" required>
                </div>
                <div class="form-group">
                    <label for="confirm_password">Confirm New Password <span class="required">*</span></label>
                    <input type="password" name="confirm_password" id="confirm_password" minlength="8" required>
                </div>
                <div class="form-actions">
                    <?php if ($is_password_changed === 1): ?>
                        <button type="button" class="btn btn-secondary" onclick="closeModal('passwordModal')">Cancel</button>
                    <?php endif; ?>
                    <button type="submit" name="change_password" class="btn btn-success" style="<?= $is_password_changed === 0 ? 'width: 100%;' : '' ?>">Update Password</button>
                </div>
            </form>

            <script>
            function validatePasswordChange(event) {
                const currentPass = document.getElementById('current_password').value;
                const newPass = document.getElementById('new_password').value;
                const confirmPass = document.getElementById('confirm_password').value;

                if (currentPass === newPass) {
                    alert("New password cannot be the same as your current password.");
                    event.preventDefault();
                    return false;
                }

                if (newPass !== confirmPass) {
                    alert("New password and confirmation password do not match.");
                    event.preventDefault();
                    return false;
                }

                return true;
            }
            </script>
        </div>
    </div>

    <!-- Upload Profile Picture Modal -->
    <div id="photoModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Update Profile Picture</h3>
                <span class="modal-close" onclick="closeModal('photoModal')">&times;</span>
            </div>
            <form action="../functions/student/upload_profile_pic_process.php" method="POST" enctype="multipart/form-data">
                <div class="form-group">
                    <label for="profile_picture">Choose Photo <span class="required">*</span></label>
                    <input type="file" name="profile_picture" id="profile_picture" accept="image/jpeg, image/png, image/webp" required>
                    <p class="form-hint">Supported: JPG, PNG, WEBP (Max 2MB)</p>
                </div>
                <div class="form-actions">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('photoModal')">Cancel</button>
                    <button type="submit" name="upload_photo" class="btn btn-success">Upload</button>
                </div>
            </form>
        </div>
    </div>

    <script>
    const isPasswordChanged = <?= $is_password_changed ?>;

    function showAnnouncementModal(index) {
        const tmpl = document.getElementById('ann-content-' + index);
        if (!tmpl) return;

        const title = tmpl.content.querySelector('.ann-data-title').innerHTML;
        const meta = tmpl.content.querySelector('.ann-data-meta').innerHTML;
        const body = tmpl.content.querySelector('.ann-data-body').innerHTML;
        const attachmentElem = tmpl.content.querySelector('.ann-data-attachment');

        document.getElementById('annModalTitle').innerHTML = title;
        document.getElementById('annModalMeta').innerHTML = meta;
        document.getElementById('annModalContent').innerHTML = body;
        document.getElementById('annModalAttachment').innerHTML = attachmentElem ? attachmentElem.outerHTML : '';
        document.getElementById('announcementModal').classList.add('show');
    }

    function openPasswordModal() { 
        document.getElementById('passwordModal').classList.add('show'); 
    }
    
    function openPhotoModal() { 
        document.getElementById('photoModal').classList.add('show'); 
    }
    
    function closeModal(id) { 
        if (id === 'passwordModal' && isPasswordChanged === 0) {
            return;
        }
        document.getElementById(id).classList.remove('show'); 
    }

    window.onclick = function(event) {
        if (event.target.classList.contains('modal')) {
            if (event.target.id === 'passwordModal' && isPasswordChanged === 0) {
                return;
            }
            event.target.classList.remove('show');
        }
    };
    </script>

    <?php if ($gwa_req > 0 && !empty($academic_history)): ?>
    <script>
    document.addEventListener("DOMContentLoaded", function() {
        const gwaLabels = <?= json_encode($gwa_labels) ?>;
        const gwaValues = <?= json_encode($gwa_values) ?>;
        const gwaReqValue = <?= $gwa_req ?>;
        const reqValues = <?= json_encode($req_values) ?>;

        const ctx = document.getElementById('gwaTrendChart').getContext('2d');
        
        const gradient = ctx.createLinearGradient(0, 0, 0, 300);
        gradient.addColorStop(0, 'rgba(42, 107, 55, 0.35)');
        gradient.addColorStop(1, 'rgba(42, 107, 55, 0.0)');

        // Dynamically calculate y-axis range based on scholar's GWA and required GWA threshold
        const allGwa = [...gwaValues, gwaReqValue];
        const lowestGwa = Math.min(...allGwa);
        const highestGwa = Math.max(...allGwa);

        let chartMin = Math.min(1.0, Math.floor(lowestGwa));
        let chartMax = Math.max(3.5, Math.ceil(highestGwa + 0.5));

        const datasets = [
            {
                label: 'Scholar GWA',
                data: gwaValues,
                borderColor: '#2a6b37',
                backgroundColor: gradient,
                borderWidth: 3,
                pointBackgroundColor: '#1e4628',
                pointBorderColor: '#ffffff',
                pointBorderWidth: 2,
                pointRadius: 6,
                pointHoverRadius: 8,
                fill: true,
                tension: 0.3
            },
            {
                label: 'Min Required GWA (' + gwaReqValue.toFixed(2) + ')',
                data: reqValues,
                borderColor: '#e74c3c',
                borderWidth: 2,
                borderDash: [6, 6],
                pointBackgroundColor: '#e74c3c',
                pointBorderColor: '#ffffff',
                pointBorderWidth: 1,
                pointRadius: gwaLabels.length === 1 ? 5 : 3,
                pointHoverRadius: 6,
                fill: false,
                tension: 0
            }
        ];

        new Chart(ctx, {
            type: 'line',
            data: {
                labels: gwaLabels,
                datasets: datasets
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: true,
                        position: 'top',
                        labels: {
                            font: { family: '-apple-system, BlinkMacSystemFont, Segoe UI, Roboto', size: 12, weight: 'bold' }
                        }
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return context.dataset.label + ': ' + parseFloat(context.raw).toFixed(2);
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        reverse: true,
                        min: chartMin,
                        max: chartMax,
                        title: {
                            display: true,
                            text: 'GWA (1.00 Highest - 3.00 Passing)',
                            font: { weight: 'bold', size: 12 }
                        },
                        grid: { color: 'rgba(0, 0, 0, 0.05)' }
                    },
                    x: {
                        title: {
                            display: true,
                            text: 'Academic Term Progress',
                            font: { weight: 'bold', size: 12 }
                        },
                        grid: { display: false }
                    }
                }
            }
        });
    });
    </script>
    <?php endif; ?>
</body>
</html>