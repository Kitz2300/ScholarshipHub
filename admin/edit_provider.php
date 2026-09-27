<?php 
include __DIR__ . "/../includes/conn.php";
include '../includes/check_permission.php';
session_start();

if ($_SESSION['role'] !== 'admin') {
    checkPageAccess($conn, $_SESSION['user_id'], 'scholarship_programs');
}

if (!hasPermission($conn, $_SESSION['user_id'], 'scholarship_programs', 'edit')) {
    $_SESSION['fail'] = "You do not have permission to edit funding providers.";
    header("Location: providers.php");
    exit();
}

$provider_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if ($provider_id <= 0) {
    $_SESSION['fail'] = "Invalid provider selected.";
    header("Location: providers.php");
    exit();
}

// Process Form Submission
if (isset($_POST['update_provider'])) {
    $name = trim($_POST['name']);
    $code = trim($_POST['code']);
    $provider_type = trim($_POST['provider_type']);
    
    // Override provider_type with custom input if 'Other' is selected
    if ($provider_type === 'Other' && !empty($_POST['provider_type_other'])) {
        $provider_type = trim($_POST['provider_type_other']);
    }

    $contact_person = trim($_POST['contact_person']);
    $email = trim($_POST['email']);
    $contact_num = trim($_POST['contact_num']);

    if (!empty($name) && !empty($code) && !empty($provider_type)) {
        $update_sql = "UPDATE providers SET name = ?, code = ?, provider_type = ?, contact_person = ?, email = ?, contact_num = ? WHERE id = ?";
        $stmt = $conn->prepare($update_sql);
        $stmt->bind_param('ssssssi', $name, $code, $provider_type, $contact_person, $email, $contact_num, $provider_id);

        if ($stmt->execute()) {
            $_SESSION['success'] = "Funding provider updated successfully!";
            header("Location: providers.php");
            exit();
        } else {
            $_SESSION['fail'] = "Failed to update provider. Please try again.";
        }
    } else {
        $_SESSION['fail'] = "Please fill in all required fields.";
    }
}

// Fetch Existing Provider Details
$fetch_sql = "SELECT * FROM providers WHERE id = ?";
$fetch_stmt = $conn->prepare($fetch_sql);
$fetch_stmt->bind_param('i', $provider_id);
$fetch_stmt->execute();
$result = $fetch_stmt->get_result();

if ($result->num_rows === 0) {
    $_SESSION['fail'] = "Provider not found.";
    header("Location: providers.php");
    exit();
}

$provider = $result->fetch_assoc();

// Determine if the saved provider_type is a custom value ("Other")
$standard_types = ['Government', 'Private', 'Institutional', 'NGO', 'Alumni'];
$current_type = $provider['provider_type'];
$is_other_type = !in_array($current_type, $standard_types);
?>

<!DOCTYPE html>
<html>
<head>
    <title>Edit Funding Provider - PIT ScholarshipHub</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../assets/css/form.css?v=<?= filemtime('../assets/css/form.css') ?>">
    <link rel="stylesheet" href="../assets/css/modals.css?v=<?= filemtime('../assets/css/modals.css') ?>">
    <link rel="icon" href="../assets/images/PIT%20LOGO.png">
    <script src="../assets/js/disable_button.js"></script>s
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
                <div class="page-header">
                    <h2>Edit Funding Provider / <?= htmlspecialchars($provider['code']) ?></h2>
                    <a href="providers.php" class="btn btn-secondary">Back to Providers</a>
                </div>

                <form action="edit_provider.php?id=<?= $provider_id ?>" method="POST" class="reusable-form">
                    <!-- Provider Details Section -->
                    <div class="form-section">
                        <h3>Provider Information</h3>
                        
                        <div class="form-row">
                            <div class="form-group two-thirds">
                                <label for="name">Provider Name <span class="required">*</span></label>
                                <input type="text" id="name" name="name" value="<?= htmlspecialchars($provider['name']) ?>" required>
                            </div>
                            <div class="form-group half-width">
                                <label for="code">Abbreviation / Code <span class="required">*</span></label>
                                <input type="text" id="code" name="code" value="<?= htmlspecialchars($provider['code']) ?>" required>
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group half-width">
                                <label for="provider_type">Provider Type <span class="required">*</span></label>
                                <select id="provider_type" name="provider_type" required>
                                    <option value="Government" <?= $current_type === 'Government' ? 'selected' : '' ?>>Government</option>
                                    <option value="Private" <?= $current_type === 'Private' ? 'selected' : '' ?>>Private</option>
                                    <option value="Institutional" <?= $current_type === 'Institutional' ? 'selected' : '' ?>>Institutional / University</option>
                                    <option value="NGO" <?= $current_type === 'NGO' ? 'selected' : '' ?>>Non Government Organization (NGO)</option>
                                    <option value="Alumni" <?= $current_type === 'Alumni' ? 'selected' : '' ?>>Alumni Association</option>
                                    <option value="Other" <?= $is_other_type ? 'selected' : '' ?>>Other</option>
                                </select>
                            </div>

                            <!-- Dynamic Input for "Other" Type -->
                            <div class="form-group half-width" id="other_type_container" style="display: <?= $is_other_type ? 'flex' : 'none' ?>;">
                                <label for="provider_type_other">Specify Other Category / Type <span class="required">*</span></label>
                                <input type="text" name="provider_type_other" id="provider_type_other" value="<?= $is_other_type ? htmlspecialchars($current_type) : '' ?>" placeholder="e.g., Community Service Grant">
                            </div>
                        </div>
                    </div>

                    <!-- Contact Details Section -->
                    <div class="form-section">
                        <h3>Contact Information (Optional)</h3>
                        
                        <div class="form-row">
                            <div class="form-group half-width">
                                <label for="contact_person">Contact Person</label>
                                <input type="text" id="contact_person" name="contact_person" placeholder="e.g., Juan Dela Cruz" value="<?= htmlspecialchars($provider['contact_person'] ?? '') ?>">
                            </div>
                            <div class="form-group half-width">
                                <label for="email">Email Address</label>
                                <input type="email" id="email" name="email" placeholder="e.g., provider@domain.gov.ph" value="<?= htmlspecialchars($provider['email'] ?? '') ?>">
                            </div>
                            <div class="form-group half-width">
                                <label for="contact_num">Contact Number</label>
                                <input type="text" id="contact_num" name="contact_num" placeholder="e.g., 09123456789 / (053) 123-4567" value="<?= htmlspecialchars($provider['contact_num'] ?? '') ?>">
                            </div>
                        </div>
                    </div>

                    <!-- Form Actions -->
                    <div class="form-actions">
                        <button type="submit" name="update_provider" class="btn btn-success">Update Provider</button>
                        <a href="providers.php" class="btn btn-secondary">Cancel</a>
                    </div>
                </form>
            </div>

            <!-- Footer -->
            <div class="footer">
                © 2026 PIT ScholarshipHub. All rights reserved.
            </div>
        </div>

        <script>
            document.addEventListener('DOMContentLoaded', function() {
                const ptTypeSelect = document.getElementById('provider_type');
                const otherTypeContainer = document.getElementById('other_type_container');
                const otherTypeInput = document.getElementById('provider_type_other');

                function toggleOtherTypeInput() {
                    if (ptTypeSelect.value === 'Other') {
                        otherTypeContainer.style.display = 'flex';
                        otherTypeInput.required = true;
                    } else {
                        otherTypeContainer.style.display = 'none';
                        otherTypeInput.required = false;
                    }
                }

                ptTypeSelect.addEventListener('change', toggleOtherTypeInput);
                toggleOtherTypeInput(); // Run on page load
            });
        </script>
    <?php endif; ?>
</body>
</html>