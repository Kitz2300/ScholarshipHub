<?php 
include __DIR__ . '/../includes/conn.php';
session_start();

if(isset($_SESSION['fail'])) {
    echo "<script>alert('" . $_SESSION['fail'] . "')</script>";
    unset($_SESSION['fail']);
}

$year = "";
?>

<!DOCTYPE html>
<html>
<head>
    <title>Add Scholar - PIT ScholarshipHub</title>
    <link rel="stylesheet" href="../assets/css/form.css">
    <link rel="icon" href="../assets/images/PIT%20LOGO.png">
</head>
<body>
    <?php if(!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') : ?>
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
                        <p>Add New Scholar</p>
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
                    <li><a href="scholar_list.php" class="active">Scholars</a></li>
                    <li><a href="announcements.php">Announcements</a></li>
                    <li><a href="scholarship_program.php">Scholarship Programs</a></li>
                    <li><a href="upload_excel.php">Import Excel</a></li>
                    <li><a href="export_scholars.php">Export Excel</a></li>
                </ul>
            </nav>

            <!-- Page Content -->
            <div class="page-content">
                <!-- Page Header -->
                <div class="page-header">
                    <h2>Add New Scholar</h2>
                    <div class="page-actions">
                        <a href="scholar_list.php" class="btn btn-secondary">Back to List</a>
                    </div>
                </div>

                <!-- Add Scholar Form -->
                <form action="../functions/admin/add_scholar_process.php" method="POST" class="scholar-form">
                    <!-- Student Information -->
                    <div class="form-section">
                        <h3>Student Information</h3>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="last_name">Last Name</label>
                                <input type="text" name="last_name" id="last_name" placeholder="Enter last name" required>
                            </div>
                            <div class="form-group">
                                <label for="first_name">First Name</label>
                                <input type="text" name="first_name" id="first_name" placeholder="Enter first name" required>
                            </div>
                            <div class="form-group">
                                <label for="middle_name">Middle Name</label>
                                <input type="text" name="middle_name" id="middle_name" placeholder="Enter middle name" required>
                            </div>
                            <div class="form-group">
                                <label for="ext_name">Extension Name</label>
                                <select name="ext_name" id="ext_name" required>
                                    <option value="" selected disabled>Select</option>
                                    <option value="N/A">Not Applicable</option>
                                    <option value="Jr.">Jr.</option>
                                    <option value="Sr.">Sr.</option>
                                    <option value="II">II</option>
                                    <option value="III">III</option>
                                </select>
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="email">Email Address</label>
                                <input type="email" name="email" id="email" placeholder="john.doe@example.com" required>
                            </div>
                            <div class="form-group">
                                <label for="student_id">Student ID</label>
                                <input type="text" name="student_id" id="student_id" placeholder="23-0223-403" required>
                            </div>
                            <div class="form-group">
                                <label for="student_contact">Contact Number</label>
                                <input type="text" name="student_contact" id="student_contact" placeholder="09123456789" required>
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="sex">Sex</label>
                                <select name="sex" id="sex" required>
                                    <option value="" selected disabled>Select Sex</option>
                                    <option value="Male">Male</option>
                                    <option value="Female">Female</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="birth_date">Birth Date</label>
                                <input type="date" name="birth_date" id="birth_date" required>
                            </div>
                            <div class="form-group">
                                <label for="disability">Disability</label>
                                <input type="text" name="disability" id="disability" placeholder="e.g., Visual impairment, Dyslexia, Mobility impairment" />
                            </div>
                            <div class="form-group">
                                <label for="ip_group">IP Group</label>
                                <input type="text" name="ip_group" id="ip_group" placeholder="e.g., Ifugao, Tagbanwa, Maranao, B'laan, Teduray" />
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group full-width">
                                <label for="address">Permanent Address</label>
                                <input type="text" name="address" id="address" placeholder="Brgy, Town/City, Province" required>
                            </div>
                            <div class="form-group">
                                <label for="zipcode">Zipcode</label>
                                <input type="number" name="zipcode" id="zipcode" placeholder="6538" required>
                            </div>
                        </div>
                    </div>

                    <!-- Scholarship Information -->
                    <div class="form-section">
                        <h3>Scholarship Information</h3>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="scholarship_program">Scholarship Program</label>
                                <select name="scholarship_program" id="scholarship_program" required>
                                    <option value="" selected disabled>Select Scholarship Program</option>
                                    <?php 
                                    $scholarship_program_sql = "SELECT id, name, code FROM scholarship_program";
                                    $stmt = $conn->prepare($scholarship_program_sql);
                                    $stmt->execute();
                                    $result = $stmt->get_result();
                                    while($row = $result->fetch_assoc()) : ?>
                                        <option value="<?= $row['id'] ?>"><?= htmlspecialchars($row['name']) ?> (<?= htmlspecialchars($row['code']) ?>)</option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="program">Program / Course</label>
                                <select name="program" id="program" required>
                                    <option value="" selected disabled>Select Program</option>
                                    <?php 
                                    $program_sql = "SELECT id, name FROM program";
                                    $stmt = $conn->prepare($program_sql);
                                    $stmt->execute();
                                    $result = $stmt->get_result();
                                    while($row = $result->fetch_assoc()) : ?>
                                        <option value="<?= $row['id'] ?>"><?= htmlspecialchars($row['name']) ?></option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="year_lvl">Year Level</label>
                                <select name="year_lvl" id="year_lvl" required>
                                    <option value="" selected disabled>Select Year Level</option>
                                    <?php for($i = 1; $i <= 6; $i++) : 
                                        switch ($i) {
                                            case 1: $year = "1st Year"; break;
                                            case 2: $year = "2nd Year"; break;
                                            case 3: $year = "3rd Year"; break;
                                            default: $year = $i . "th Year"; break;
                                        }
                                    ?>
                                        <option value="<?= $i ?>"><?= $year ?></option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- Family Background -->
                    <div class="form-section">
                        <h3>Family Background</h3>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="father_last">Father's Last Name</label>
                                <input type="text" name="father_last" id="father_last" placeholder="Last Name" required>
                            </div>
                            <div class="form-group">
                                <label for="father_first">Father's First Name</label>
                                <input type="text" name="father_first" id="father_first" placeholder="First Name" required>
                            </div>
                            <div class="form-group">
                                <label for="father_middle">Father's Middle Name</label>
                                <input type="text" name="father_middle" id="father_middle" placeholder="Middle Name" required>
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="mother_last">Mother's Maiden Last Name</label>
                                <input type="text" name="mother_last" id="mother_last" placeholder="Last Name" required>
                            </div>
                            <div class="form-group">
                                <label for="mother_first">Mother's Maiden First Name</label>
                                <input type="text" name="mother_first" id="mother_first" placeholder="First Name" required>
                            </div>
                            <div class="form-group">
                                <label for="mother_middle">Mother's Maiden Middle Name</label>
                                <input type="text" name="mother_middle" id="mother_middle" placeholder="Middle Name" required>
                            </div>
                        </div>
                    </div>

                    <!-- Form Actions -->
                    <div class="form-actions">
                        <button type="submit" class="btn btn-success">Add Scholar</button>
                        <a href="scholar_list.php" class="btn btn-secondary">Cancel</a>
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