<?php 
include __DIR__ . "/../includes/conn.php";
include __DIR__ . "/../includes/check_permission.php";
session_start();

if (!hasPermission($conn, $_SESSION['user_id'], 'scholarship_programs', 'edit')) {
    header("Location: scholarship_program.php");
    exit();
}

// Get ID from URL
$scholarship_id = (int) ($_GET['id'] ?? 0);

// Initialize variables with current year defaults
$current_year_default = (int) date('Y');
$name = "";
$code = "";
$start_year = $current_year_default;
$end_year = $current_year_default + 1;
$semester = "1st Semester";
$description = "";
$gwa_req = 0.00;
$pay = 0.00;
$sc_type = "";
$provider_id = 0;

$scholarship_sql = "SELECT * FROM scholarship_program WHERE id = ?";
$scholarship_stmt = $conn->prepare($scholarship_sql);
$scholarship_stmt->bind_param('i', $scholarship_id);
$scholarship_stmt->execute();
$scholarship_result = $scholarship_stmt->get_result();

if ($scholarship_result->num_rows > 0) {
    if ($row = $scholarship_result->fetch_assoc()) {
        $name = $row['name'];
        $code = $row['code'];
        $school_year = explode(" - ", str_replace(" ", "", $row['school_year']));
        if (count($school_year) < 2) {
            $school_year = explode("-", $row['school_year']);
        }
        $start_year = !empty($school_year[0]) ? (int)$school_year[0] : $current_year_default;
        $end_year = !empty($school_year[1]) ? (int)$school_year[1] : ($start_year + 1);
        $semester = $row['semester'] ?? '1st Semester';
        $description = $row['description'];
        $gwa_req = (float) $row['min_gwa_req'];
        $pay = $row['pay_amount'];
        $sc_type = $row['scholarship_type'];
        $provider_id = (int) ($row['provider_id'] ?? 0);
    }
} else {
    $_SESSION['fail'] = "Scholarship program does not exist!";
    header("Location: scholarship_program.php");
    exit();
}

// Fetch active providers + assigned provider (even if archived)
$providers_sql = "SELECT id, name, code, is_archived FROM providers WHERE is_archived = 0 OR id = ? ORDER BY name ASC";
$providers_stmt = $conn->prepare($providers_sql);
$providers_stmt->bind_param('i', $provider_id);
$providers_stmt->execute();
$providers_result = $providers_stmt->get_result();

// Define standard options to detect custom "Other" values
$standard_types = [
    'Merit-Based',
    'Need-Based',
    'Academic Excellence',
    'Athletic',
    'Leadership & Community Service',
    'Career/Degree-Specific',
    'Identity/Group-Based'
];

$is_other_type = (!empty($sc_type) && !in_array($sc_type, $standard_types));
$sc_type_select_val = $is_other_type ? 'Other' : $sc_type;
$sc_type_other_val = $is_other_type ? $sc_type : '';
$has_gwa = ($gwa_req > 0);
?>

<!DOCTYPE html>
<html>
<head>
    <title>Edit Scholarship Program - PIT ScholarshipHub</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../assets/css/form.css?v=<?= filemtime('../assets/css/form.css') ?>">
    <link rel="stylesheet" href="../assets/css/modals.css?v=<?= filemtime('../assets/css/modals.css') ?>">
    <link rel="icon" href="../assets/images/PIT%20LOGO.png">
    <script src="../assets/js/school_year.js"></script>
    <script src="../assets/js/disable_button.js"></script>
</head>
<body>
    <?php include __DIR__ . "/../includes/modal_alerts.php"; ?>

    <?php if (!isset($_SESSION['user_id']) || ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'staff')) : 
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
                    <h2>Edit Scholarship Program</h2>
                    <div class="page-actions">
                        <a href="scholarship_program.php" class="btn btn-secondary">Back to Programs</a>
                    </div>
                </div>

                <!-- Edit Form -->
                <form action="../functions/admin/edit_scholarship_process.php" method="POST" class="reusable-form">
                    <input type="hidden" name="scholarship_id" value="<?= $scholarship_id ?>"/>

                    <div class="form-section">
                        <h3>Program Details</h3>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="name">Scholarship Name <span class="required">*</span></label>
                                <input type="text" name="name" id="name" value="<?= htmlspecialchars($name) ?>" required/>
                            </div>
                            <div class="form-group">
                                <label for="code">Scholarship Code</label>
                                <input type="text" name="code" id="code" value="<?= htmlspecialchars($code) ?>"/>
                            </div>
                            <div class="form-group">
                                <label for="start_year">School Year <span class="required">*</span></label>
                                <div class="school-year-group">
                                    <select name="start_year" id="start_year" required>
                                        <?php 
                                        $current_y = (int) date('Y');
                                        $min_y = min($current_y - 5, (int)$start_year);
                                        $max_y = max($current_y + 5, (int)$start_year);
                                        for ($y = $min_y; $y <= $max_y; $y++) : 
                                        ?>
                                            <option value="<?= $y ?>" <?= (int)$start_year === $y ? "selected" : "" ?>><?= $y ?></option>
                                        <?php endfor; ?>
                                    </select>
                                    <span class="dash">–</span>
                                    <input type="text" id="end_year_display" value="" readonly class="end-year-display">
                                    <input type="hidden" name="end_year" id="end_year_hidden" value="">
                                </div>
                            </div>
                            <div class="form-group">
                                <label for="semester">Semester <span class="required">*</span></label>
                                <select name="semester" id="semester" required>
                                    <option value="1st Semester" <?= $semester === "1st Semester" ? "selected" : "" ?>>1st Semester</option>
                                    <option value="2nd Semester" <?= $semester === "2nd Semester" ? "selected" : "" ?>>2nd Semester</option>
                                    <option value="Summer" <?= $semester === "Summer" ? "selected" : "" ?>>Summer</option>
                                </select>
                            </div>
                        </div>

                        <!-- Provider & Category Selection -->
                        <div class="form-row">
                            <div class="form-group half-width">
                                <label for="provider_id">Funding Provider <span class="required">*</span></label>
                                <select name="provider_id" id="provider_id" required>
                                    <option value="" disabled>-- Select Provider --</option>
                                    <?php if ($providers_result && $providers_result->num_rows > 0) : ?>
                                        <?php while ($p = $providers_result->fetch_assoc()) : ?>
                                            <option value="<?= $p['id'] ?>" <?= $provider_id == $p['id'] ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($p['name']) ?> (<?= htmlspecialchars($p['code']) ?>)
                                                <?= $p['is_archived'] ? ' - [Archived]' : '' ?>
                                            </option>
                                        <?php endwhile; ?>
                                    <?php else : ?>
                                        <option value="" disabled>No providers available</option>
                                    <?php endif; ?>
                                </select>
                            </div>

                            <div class="form-group half-width">
                                <label for="sc_type">Scholarship Category / Type <span class="required">*</span></label>
                                <select name="sc_type" id="sc_type" required>
                                    <option value="" disabled>-- Select Type --</option>
                                    <option value="Merit-Based" <?= $sc_type_select_val === "Merit-Based" ? "selected" : "" ?>>Merit-Based</option>
                                    <option value="Need-Based" <?= $sc_type_select_val === "Need-Based" ? "selected" : "" ?>>Need-Based</option>
                                    <option value="Academic Excellence" <?= $sc_type_select_val === "Academic Excellence" ? "selected" : "" ?>>Academic Excellence</option>
                                    <option value="Athletic" <?= $sc_type_select_val === "Athletic" ? "selected" : "" ?>>Athletic</option>
                                    <option value="Leadership & Community Service" <?= $sc_type_select_val === "Leadership & Community Service" ? "selected" : "" ?>>Leadership & Community Service</option>
                                    <option value="Career/Degree-Specific" <?= $sc_type_select_val === "Career/Degree-Specific" ? "selected" : "" ?>>Career/Degree-Specific</option>
                                    <option value="Identity/Group-Based" <?= $sc_type_select_val === "Identity/Group-Based" ? "selected" : "" ?>>Identity/Group-Based</option>
                                    <option value="Other" <?= $sc_type_select_val === "Other" ? "selected" : "" ?>>Other</option>
                                </select>
                            </div>

                            <!-- Dynamic Input for "Other" Type -->
                            <div class="form-group half-width" id="other_type_container" style="display: <?= $is_other_type ? 'flex' : 'none' ?>;">
                                <label for="sc_type_other">Specify Other Category / Type <span class="required">*</span></label>
                                <input type="text" name="sc_type_other" id="sc_type_other" value="<?= htmlspecialchars($sc_type_other_val) ?>" placeholder="e.g., Community Service Grant">
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group full-width">
                                <label for="desc">Description</label>
                                <textarea name="desc" id="desc" rows="4" placeholder="Enter scholarship description (optional)"><?= htmlspecialchars($description) ?></textarea>
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group half-width">
                                <label for="pay_amount">Allowance <span class="required">*</span></label>
                                <input type="number" name="pay_amount" id="pay_amount" step="0.01" min="0" placeholder="e.g., 5000.00" value="<?= htmlspecialchars($pay) ?>" required/>
                            </div>

                            <div class="form-group half-width gwa-row-wrapper">
                                <div class="checkbox-align-bottom">
                                    <label class="checkbox-inline">
                                        <input type="checkbox" id="has_gwa_req" <?= $has_gwa ? 'checked' : '' ?> onchange="toggleGwaInput(this)">
                                        <span>Require GWA</span>
                                    </label>
                                </div>
                                <div id="gwa_input_container" class="form-group gwa-input-group" style="display: <?= $has_gwa ? 'flex' : 'none' ?>;">
                                    <label for="gwa_req">Minimum GWA Requirement</label>
                                    <input type="number" name="gwa_req" id="gwa_req" step="0.01" min="1.0" max="4.0" placeholder="e.g., 2.50" value="<?= $has_gwa ? htmlspecialchars($gwa_req) : '' ?>"/>
                                </div>
                            </div>
                        </div>
                    </div>

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

        <script>
            function toggleGwaInput(checkbox) {
                const container = document.getElementById('gwa_input_container');
                const input = document.getElementById('gwa_req');
                if (container) {
                    container.style.display = checkbox.checked ? 'flex' : 'none';
                    if (!checkbox.checked && input) {
                        input.value = '';
                    }
                }
            }

            document.addEventListener('DOMContentLoaded', function () {
                const scTypeSelect = document.getElementById('sc_type');
                const otherTypeContainer = document.getElementById('other_type_container');
                const otherTypeInput = document.getElementById('sc_type_other');

                function toggleOtherTypeInput() {
                    if (scTypeSelect.value === 'Other') {
                        otherTypeContainer.style.display = 'flex';
                        otherTypeInput.required = true;
                    } else {
                        otherTypeContainer.style.display = 'none';
                        otherTypeInput.required = false;
                    }
                }

                scTypeSelect.addEventListener('change', toggleOtherTypeInput);
                toggleOtherTypeInput();
            });
        </script>
    <?php endif; ?>
</body>
</html>