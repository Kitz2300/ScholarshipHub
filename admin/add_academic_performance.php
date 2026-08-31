<?php 
include __DIR__ . "/../includes/conn.php";
session_start();

// get scholar id from url param
$scholar_id = (int) ($_GET['scholar_id'] ?? 0);

// validate id
if($scholar_id == 0) {
    $_SESSION['fail'] = "Scholar does not exist.";
    header("Location: scholar_list.php");
    exit();
}

?>

<!DOCTYPE html>
<html>
<head>
    <title>Add Academic Performance - PIT ScholarshipHub</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../assets/css/form.css?v=<?= filemtime('../assets/css/form.css') ?>">
    <link rel="icon" href="../assets/images/PIT%20LOGO.png">
</head>
<body>
    <?php 
    if(!isset($_SESSION['user_id']) || ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'staff')) : ?>
        <!-- if not logged in, redirect user to login page -->
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
                        <p>Add Academic Performance</p>
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
                <!-- Page Header -->
                <div class="page-header">
                    <h2>Add Academic Performance</h2>
                    <div class="page-actions">
                        <a href="academic_performance.php?scholar_id=<?= $scholar_id ?>" class="btn btn-secondary">Back to List</a>
                    </div>
                </div>

                <!-- Add Form -->
                <form action="../functions/admin/add_academic_performance_process.php" method="POST" class="reusable-form">
                    <div class="form-section">
                        <div class="form-row">
                            <div class="form-group">
                                <label for="semester">Semester <span class="required">*</span></label>
                                <select name="sem" id="semester" required>
                                    <option selected disabled>--Select Semester--</option>
                                    <option value="1st Semester">1st Semester</option>
                                    <option value="2nd Semester">2nd Semester</option>
                                    <option value="3rd Semester">3rd Semester</option>
                                </select>
                            </div>

                            <div class="form-group">
                                <label for="gwa">General Weighted Average (GWA) <span class="required">*</span></label>
                                <input type="number" name="gwa" id="gwa" step="0.01" min="1.00" max="4.00" placeholder="e.g., 1.75" required/>
                            </div>

                            <div class="form-group">
                                <label for="units">Units <span class="required">*</span></label>
                                <input type="number" name="units" id="units" min="1" placeholder="e.g., 20.0" required/>
                            </div>
                        </div>

                        <!-- <div class="form-row">
                            <div class="form-group full-width">
                                <label for="start_year">School Year <span class="required">*</span></label>
                                <div class="school-year-group">
                                    <select name="start_year" id="start_year" required>
                                        <?php for($y = date('Y') - 5; $y <= date('Y'); $y++) : ?>
                                            <option value="<?= $y ?>"><?= $y ?></option>
                                        <?php endfor; ?>
                                    </select>
                                    <span class="dash">–</span>
                                    <select name="end_year" id="end_year" required>
                                        <?php for($y = date('Y'); $y <= date('Y') + 5; $y++) : ?>
                                            <option value="<?= $y ?>"><?= $y ?></option>
                                        <?php endfor; ?>
                                    </select>
                                </div>
                                <small style="color: #999; font-size: 12px; margin-top: 4px;">Select the start and end year of the academic year.</small>
                            </div>
                        </div> -->
                    </div>

                    <input type="hidden" name="scholar_id" value="<?= $scholar_id ?>"/>

                    <div class="form-actions">
                        <button type="submit" name="add" class="btn btn-success">Add Record</button>
                        <a href="academic_performance.php?scholar_id=<?= $scholar_id ?>" class="btn btn-secondary">Cancel</a>
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