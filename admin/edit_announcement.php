<?php 
include __DIR__ . "/../includes/conn.php";
include __DIR__ . "/../includes/check_permission.php";
session_start();

if(!hasPermission($conn, $_SESSION['user_id'], 'announcements', 'edit')) {
    header("Location: announcements.php");
    exit();
}

$announcement_id = (int) ($_GET['id'] ?? 0);

$announcement_sql = "SELECT * FROM announcements WHERE id=?";
$announcement_stmt = $conn->prepare($announcement_sql);
$announcement_stmt->bind_param("i", $announcement_id);
$announcement_stmt->execute();
$result = $announcement_stmt->get_result();

// announcement data variables
$title = "";
$content = "";
$status = 0;
$scholarship_program_id = 0;

if($row=$result->fetch_assoc()) {
    $title = $row['title'];
    $content = $row['content'];
    $status = (int)$row['is_active'];
    $scholarship_program_id = (int)($row['scholarship_program_id'] ?? 0);
}

// Fetch active scholarship programs
$sp_sql = "SELECT id, name FROM scholarship_program WHERE is_archived = 0 ORDER BY name ASC";
$sp_result = $conn->query($sp_sql);
?>

<!DOCTYPE html>
<html>
<head>
    <title>Edit Announcement - PIT ScholarshipHub</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../assets/css/form.css?v=<?= filemtime('../assets/css/form.css') ?>">
    <link rel="stylesheet" href="../assets/css/modals.css?v=<?= filemtime('../assets/css/modals.css') ?>">
    <link rel="icon" href="../assets/images/PIT%20LOGO.png">
    <script src="../assets/js/disable_button.js"></script>
</head>
<body>
    <?php include __DIR__ . "/../includes/modal_alerts.php"; ?>
    <?php
    if(!isset($_SESSION['user_id']) || ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'staff')) : 
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
                    <h2>Edit Announcement</h2>
                    <div class="page-actions">
                        <a href="announcements.php" class="btn btn-secondary">Back to Announcements</a>
                    </div>
                </div>

                <!-- Edit Announcement Form -->
                <form action="../functions/admin/edit_announcement_process.php" method="POST" class="reusable-form">
                    <div class="form-section">
                        <div class="form-row">
                            <div class="form-group full-width">
                                <label for="title">Title <span class="required">*</span></label>
                                <input type="text" name="title" id="title" value="<?= htmlspecialchars($title) ?>" required/>
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group full-width">
                                <label for="scholarship_program_id">Addressed To <span class="required">*</span></label>
                                <select name="scholarship_program_id" id="scholarship_program_id" required>
                                    <option value="0" <?= $scholarship_program_id == 0 ? "selected" : "" ?>>All Scholarship Programs</option>
                                    <?php if ($sp_result && $sp_result->num_rows > 0) : ?>
                                        <?php while($sp = $sp_result->fetch_assoc()) : ?>
                                            <option value="<?= $sp['id'] ?>" <?= $scholarship_program_id == $sp['id'] ? "selected" : "" ?>>
                                                <?= htmlspecialchars($sp['name']) ?>
                                            </option>
                                        <?php endwhile; ?>
                                    <?php endif; ?>
                                </select>
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group full-width">
                                <label for="content">Content <span class="required">*</span></label>
                                <textarea name="content" id="content" rows="8" required><?= htmlspecialchars($content) ?></textarea>
                            </div>
                        </div>
                    </div>

                    <input type="hidden" name="announcement_id" value="<?= htmlspecialchars($announcement_id) ?>"/>

                    <div class="form-actions">
                        <button type="submit" class="btn btn-success">Update Announcement</button>
                        <a href="announcements.php" class="btn btn-secondary">Cancel</a>
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