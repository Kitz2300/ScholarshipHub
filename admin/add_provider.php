<?php 
include __DIR__ . "/../includes/conn.php";
include '../includes/check_permission.php';
session_start();

if ($_SESSION['role'] !== 'admin') {
    checkPageAccess($conn, $_SESSION['user_id'], 'scholarship_programs');
}

if (!hasPermission($conn, $_SESSION['user_id'], 'scholarship_programs', 'add')) {
    $_SESSION['fail'] = "You do not have permission to add funding providers.";
    header("Location: providers.php");
    exit();
}

// Process Form Submission
if (isset($_POST['add_provider'])) {
    $name = trim($_POST['name']);
    $code = trim($_POST['code']);
    $provider_type = trim($_POST['provider_type']);
    $contact_person = trim($_POST['contact_person']);
    $email = trim($_POST['email']);
    $contact_num = trim($_POST['contact_num']);

    if (!empty($name) && !empty($code) && !empty($provider_type)) {
        $insert_sql = "INSERT INTO providers (name, code, provider_type, contact_person, email, contact_num) VALUES (?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($insert_sql);
        $stmt->bind_param('ssssss', $name, $code, $provider_type, $contact_person, $email, $contact_num);

        if ($stmt->execute()) {
            $_SESSION['provider_archive_filter'] = 0;
            $_SESSION['success'] = "Funding provider added successfully!";
            header("Location: providers.php");
            exit();
        } else {
            $_SESSION['fail'] = "Failed to add provider. Please try again.";
        }
    } else {
        $_SESSION['fail'] = "Please fill in all required fields.";
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Add Funding Provider - PIT ScholarshipHub</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../assets/css/form.css?v=<?= filemtime('../assets/css/form.css') ?>">
    <link rel="icon" href="../assets/images/PIT%20LOGO.png">
</head>
<body>
    <?php 
    if (isset($_SESSION['success'])) {
        echo "<script>alert('" . htmlspecialchars($_SESSION['success'], ENT_QUOTES) . "')</script>";
        unset($_SESSION['success']);
    } else if (isset($_SESSION['fail'])) {
        echo "<script>alert('" . htmlspecialchars($_SESSION['fail'], ENT_QUOTES) . "')</script>";
        unset($_SESSION['fail']);
    }
    ?>

    <?php if (!isset($_SESSION['user_id']) || ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'staff')) : ?>
        <script>alert('You are not logged in')</script>
        <script>window.location.href='../portal.php'</script>
    <?php else : ?>
        <div class="container">
            <!-- Header -->
            <div class="header">
                <div class="header-left">
                    <img src="../assets/images/PIT%20LOGO.png" alt="PIT Logo">
                    <div>
                        <h1>PIT ScholarshipHub</h1>
                        <p>Add Funding Provider</p>
                    </div>
                </div>
                <div class="header-right">
                    <span class="welcome-text">Welcome, Admin!</span>
                    <form action="../functions/logout.php" method="post" style="display: inline;">
                        <button type="submit" class="logout-btn" onclick="return confirm('Logout?')">Logout</button>
                    </form>
                </div>
            </div>

            <?php include __DIR__ . "/../includes/navigation.php"; ?>

            <!-- Page Content -->
            <div class="page-content">
                <div class="page-header">
                    <h2>Add New Funding Provider</h2>
                    <a href="providers.php" class="btn btn-secondary">Back to Providers</a>
                </div>

                <form action="add_provider.php" method="POST" class="reusable-form">
                    <!-- Provider Details Section -->
                    <div class="form-section">
                        <h3>Provider Information</h3>
                        
                        <div class="form-row">
                            <div class="form-group two-thirds">
                                <label for="name">Provider Name <span class="required">*</span></label>
                                <input type="text" id="name" name="name" placeholder="e.g., Commission on Higher Education" required>
                            </div>
                            <div class="form-group half-width">
                                <label for="code">Abbreviation / Code <span class="required">*</span></label>
                                <input type="text" id="code" name="code" placeholder="e.g., CHED" required>
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group half-width">
                                <label for="provider_type">Provider Type <span class="required">*</span></label>
                                <select id="provider_type" name="provider_type" required>
                                    <option value="" disabled selected>-- Select Type --</option>
                                    <option value="Government">Government</option>
                                    <option value="Private/Corporate">Private / Corporate</option>
                                    <option value="Institutional">Institutional / University</option>
                                    <option value="NGO/Non-Profit">NGO / Non-Profit</option>
                                    <option value="Alumni">Alumni Association</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- Contact Details Section -->
                    <div class="form-section">
                        <h3>Contact Information (Optional)</h3>
                        
                        <div class="form-row">
                            <div class="form-group half-width">
                                <label for="contact_person">Contact Person</label>
                                <input type="text" id="contact_person" name="contact_person" placeholder="e.g., Juan Dela Cruz">
                            </div>
                            <div class="form-group half-width">
                                <label for="email">Email Address</label>
                                <input type="email" id="email" name="email" placeholder="e.g., provider@domain.gov.ph">
                            </div>
                            <div class="form-group half-width">
                                <label for="contact_num">Contact Number</label>
                                <input type="text" id="contact_num" name="contact_num" placeholder="e.g., 09123456789 / (053) 123-4567">
                            </div>
                        </div>
                    </div>

                    <!-- Form Actions -->
                    <div class="form-actions">
                        <button type="submit" name="add_provider" class="btn btn-success">Save Provider</button>
                        <a href="providers.php" class="btn btn-secondary">Cancel</a>
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