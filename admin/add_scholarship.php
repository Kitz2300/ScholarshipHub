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
    <script src="../assets/js/school_year.js"></script>
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
                    <!-- <li class="dropdown">
                        <a href="#" class="dropdown-toggle">Import / Export <div class="arrow-down">▼</div></a>
                        <ul class="dropdown-menu">
                            <li><a href="upload_excel.php">Import Excel</a></li>
                            <li><a href="export_scholars.php">Export Excel</a></li>
                        </ul>
                    </li> -->

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
                            <div class="form-group">
                                <label for="start_year">School Year <span class="required">*</span></label>
                                <div class="school-year-group">
                                    <select name="start_year" id="start_year" required>
                                        <?php for($y = date('Y') - 5; $y <= date('Y'); $y++) : ?>
                                            <option value="<?= $y ?>"><?= $y ?></option>
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
                                <!-- <label for="scholarship_type">Type <span class="required">*</span></label>
                                <div class="radio-group">
                                    <div class="radio-option">
                                        <input type="radio" name="is_grant" id="scholarship_type" value="0" checked>
                                        <label for="scholarship_type">Scholarship</label>
                                    </div>
                                    <div class="radio-option">
                                        <input type="radio" name="is_grant" id="financial_grant_type" value="1">
                                        <label for="financial_grant_type">Financial Grant</label>
                                    </div>
                                </div> -->
                                
                                <label for="type">Type <span class="required">*</span></label>
                                <select name="sc_type" required>
                                    <option value="Not Specified">Not Specified</option>
                                    <optgroup label="By Qualifications">
                                        <option value="Merit-Based">Merit-Based</option>
                                        <option value="Need-Based">Need-Based</option>
                                        <option value="Career-specific">Career-specific</option>
                                        <option value="Athletic">Athletic</option>
                                        <option value="Identity or Group-Based">Identity or Group-Based</option>
                                    </optgroup>
                                    <optgroup label="By Funding Source">
                                        <option value="Government Scholarship">Government Scholarship</option>
                                        <option value="Private / Corporate Scholarship">Private / Corporate Scholarship</option>
                                        <option value="Institutional / University Grant">Institutional / University Grant</option>
                                        <option value="International Scholarship">International Scholarship</option>
                                    </optgroup>
                                    <optgroup label="Other">
                                        <option value="Academic Excellence Grant">Academic Excellence Grant</option>
                                        <option value="Leadership / Community Services">Leadership / Community Service</option>
                                        <option value="Research Scholarship">Research Scholarship</option>
                                        <option value="External Partner Scholarship">External Partner Scholarship</option>
                                    </optgroup>
                                </select>
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