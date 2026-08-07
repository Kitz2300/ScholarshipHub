<?php 
session_start(); 
?>

<!DOCTYPE html>
<html>
<head>
    <title>Add Scholarship Program - PIT ScholarshipHub</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../assets/css/form.css?v=<?= filemtime('../assets/css/form.css') ?>">
    <link rel="icon" href="../assets/images/PIT%20LOGO.png">
</head>
<body>
    <?php 
    // if add failed alert users
    if(isset($_SESSION['fail'])) {
        echo "<script>alert('" . $_SESSION['fail'] . "')</script>";
        unset($_SESSION['fail']);
    }
    ?>

    <?php if(!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') : ?>
        <script>alert('You are not logged in')</script>
        <script>window.location.href="login.php"</script>
    <?php else : ?>
        <div class="container">
            <!-- Header -->
            <div class="header">
                <div class="header-left">
                    <img src="../assets/images/PIT%20LOGO.png" alt="PIT Logo">
                    <div>
                        <h1>PIT ScholarshipHub</h1>
                        <p>Add Scholarship Program</p>
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
                    <li><a href="scholar_list.php">Scholars</a></li>
                    <li><a href="announcements.php">Announcements</a></li>
                    <li><a href="scholarship_program.php" class="active">Scholarship Programs</a></li>
                    <li><a href="upload_excel.php">Import Excel</a></li>
                    <li><a href="export_scholars.php">Export Excel</a></li>
                </ul>
            </nav>

            <!-- Page Content -->
            <div class="page-content">
                <!-- Page Header -->
                <div class="page-header">
                    <h2>Add Scholarship Program</h2>
                    <div class="page-actions">
                        <a href="scholarship_program.php" class="btn btn-secondary">Back to Scholarship Programs</a>
                    </div>
                </div>

                <!-- add form -->
                <form action="../functions/admin/add_scholarship_process.php" method="POST" class="reusable-form">
                    <div class="form-section">
                        <div class="form-row">
                            <div class="form-group">
                                <label for="name">Scholarship Name <span class="required">*</span></label>
                                <input type="text" name="name" id="name" placeholder="Enter scholarship name" required/>
                            </div>
                            <div class="form-group">
                                <label for="code">Scholarship Code <span class="required">*</span></label>
                                <input type="text" name="code" id="code" placeholder="e.g., DOST" required/>
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group full-width">
                                <label for="desc">Description <span class="required">*</span></label>
                                <textarea name="desc" id="desc" rows="5" placeholder="Enter scholarship description" required></textarea>
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="pay_amount">Allowance <span class="required">*</span></label>
                                <input type="number" name="pay_amount" id="pay_amount" step="0.01" min="0" placeholder="e.g., 5000.00" required/>
                            </div>
                            <div class="form-group">
                                <label for="gwa_req">Minimum GWA Requirement</label>
                                <input type="number" name="gwa_req" id="gwa_req" step="0.01" min="1.0" max="4.0" placeholder="e.g., 2.5 or leave blank"/>
                                <small class="help-text">Leave blank if no GWA requirement.</small>
                            </div>
                        </div>
                        
                        <!-- Scholarship Type Selection -->
                        <div class="form-row">
                            <div class="form-group full-width">
                                <label for="scholarship_type">Type <span class="required">*</span></label>
                                <div class="radio-group">
                                    <div class="radio-option">
                                        <input type="radio" name="is_grant" id="scholarship_type" value="0" checked>
                                        <label for="scholarship_type">Scholarship</label>
                                    </div>
                                    <div class="radio-option">
                                        <input type="radio" name="is_grant" id="financial_grant_type" value="1">
                                        <label for="financial_grant_type">Financial Grant</label>
                                    </div>
                                </div>
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
    <?php endif; ?>
</body>
</html>