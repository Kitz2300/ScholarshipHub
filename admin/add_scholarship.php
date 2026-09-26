<?php 
include __DIR__ . "/../includes/conn.php";
include __DIR__ . "/../includes/check_permission.php";
session_start();

if ($_SESSION['role'] !== 'admin') {
    checkPageAccess($conn, $_SESSION['user_id'], 'scholarship_programs');
}

// Fetch active funding providers from database
$providers_sql = "SELECT id, name, code FROM providers WHERE is_archived = 0 ORDER BY name ASC";
$providers_result = $conn->query($providers_sql);
?>

<!DOCTYPE html>
<html>
<head>
    <title>Add Scholarship Program - PIT ScholarshipHub</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../assets/css/form.css?v=<?= filemtime('../assets/css/form.css') ?>">
    <link rel="stylesheet" href="../assets/css/modals.css?v=<?= filemtime('../assets/css/modals.css') ?>">
    <link rel="icon" href="../assets/images/PIT%20LOGO.png">
    <script src="../assets/js/school_year.js"></script>
</head>
<body>
    <?php include __DIR__ . "/../includes/modal_alerts.php"; ?>

    <?php if (!isset($_SESSION['user_id']) || ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'staff')) : 
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
                    <h2>Add Scholarship Program</h2>
                    <div class="page-actions">
                        <a href="scholarship_program.php" class="btn btn-secondary">Back to Scholarship Programs</a>
                    </div>
                </div>

                <!-- Add Form -->
                <form action="../functions/admin/add_scholarship_process.php" method="POST" class="reusable-form">
                    <div class="form-section">
                        <h3>Program Details</h3>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="name">Scholarship Name <span class="required">*</span></label>
                                <input type="text" name="name" id="name" placeholder="Enter scholarship name" required/>
                            </div>
                            <div class="form-group">
                                <label for="code">Scholarship Code <span class="required">*</span></label>
                                <input type="text" name="code" id="code" placeholder="e.g., DOST-SEI" required/>
                            </div>
                            <div class="form-group">
                                <label for="start_year">School Year <span class="required">*</span></label>
                                <div class="school-year-group">
                                    <select name="start_year" id="start_year" required>
                                        <?php for ($y = date('Y') - 5; $y <= date('Y'); $y++) : ?>
                                            <option value="<?= $y ?>"><?= $y ?></option>
                                        <?php endfor; ?>
                                    </select>
                                    <span class="dash">–</span>
                                    <input type="text" id="end_year_display" value="" readonly class="end-year-display">
                                    <input type="hidden" name="end_year" id="end_year_hidden" value="">
                                </div>
                            </div>
                        </div>

                        <!-- Provider and Qualification Type Row -->
                        <div class="form-row">
                            <div class="form-group half-width">
                                <label for="provider_id">Funding Provider <span class="required">*</span></label>
                                <select name="provider_id" id="provider_id" required>
                                    <option value="" disabled selected>-- Select Provider --</option>
                                    <?php if ($providers_result && $providers_result->num_rows > 0) : ?>
                                        <?php while ($p = $providers_result->fetch_assoc()) : ?>
                                            <option value="<?= $p['id'] ?>">
                                                <?= htmlspecialchars($p['name']) ?> (<?= htmlspecialchars($p['code']) ?>)
                                            </option>
                                        <?php endwhile; ?>
                                    <?php else : ?>
                                        <option value="" disabled>No active providers found</option>
                                    <?php endif; ?>
                                </select>
                                <?php if (!$providers_result || $providers_result->num_rows === 0) : ?>
                                    <small class="help-text" style="color: #e74c3c;">No active provider available. Please <a href="add_provider.php" style="color: #1e4628; text-decoration: underline;">add a provider</a> first.</small>
                                <?php endif; ?>
                            </div>

                            <div class="form-group half-width">
                                <label for="sc_type">Scholarship Category / Type <span class="required">*</span></label>
                                <select name="sc_type" id="sc_type" required>
                                    <option value="" disabled selected>-- Select Type --</option>
                                    <option value="Merit-Based">Merit-Based</option>
                                    <option value="Need-Based">Need-Based</option>
                                    <option value="Academic Excellence">Academic Excellence</option>
                                    <option value="Athletic">Athletic</option>
                                    <option value="Leadership & Community Service">Leadership & Community Service</option>
                                    <option value="Career/Degree-Specific">Career/Degree-Specific</option>
                                    <option value="Identity/Group-Based">Identity/Group-Based</option>
                                    <option value="Other">Other</option>
                                </select>
                            </div>

                            <!-- Dynamic Input for "Other" Type -->
                            <div class="form-group half-width" id="other_type_container" style="display: none;">
                                <label for="sc_type_other">Specify Other Category / Type <span class="required">*</span></label>
                                <input type="text" name="sc_type_other" id="sc_type_other" placeholder="e.g., Community Service Grant">
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group full-width">
                                <label for="desc">Description <span class="required">*</span></label>
                                <textarea name="desc" id="desc" rows="4" placeholder="Enter scholarship description" required></textarea>
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group half-width">
                                <label for="pay_amount">Allowance <span class="required">*</span></label>
                                <input type="number" name="pay_amount" id="pay_amount" step="0.01" min="0" placeholder="e.g., 5000.00" required/>
                            </div>
                            <div class="form-group half-width">
                                <label for="gwa_req">Minimum GWA Requirement</label>
                                <input type="number" name="gwa_req" id="gwa_req" step="0.01" min="1.0" max="4.0" placeholder="e.g., 2.50"/>
                                <small class="help-text">Leave blank if no GWA requirement.</small>
                            </div>
                        </div>
                    </div>

                    <div class="form-actions">
                        <button type="submit" name="add" class="btn btn-success">Add Scholarship Program</button>
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
                        otherTypeInput.value = '';
                    }
                }

                scTypeSelect.addEventListener('change', toggleOtherTypeInput);
            });
        </script>
    <?php endif; ?>
</body>
</html>