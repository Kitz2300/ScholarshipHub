<?php 
include __DIR__ . "/../includes/conn.php";
include '../includes/check_permission.php';
session_start();

if ($_SESSION['role'] !== 'admin') {
    checkPageAccess($conn, $_SESSION['user_id'], 'scholarship_programs');
}

// Handle archive filter submission
if (isset($_POST['apply_filters'])) {
    if (isset($_POST['archive_status'])) {
        $_SESSION['provider_archive_filter'] = $_POST['archive_status'];
    }
    header("Location: providers.php");
    exit();
}

$archive_filter = $_SESSION['provider_archive_filter'] ?? '0';

// Archive / Unarchive Provider Process
if (isset($_POST['toggle_archive']) && isset($_POST['provider_id'])) {
    $provider_id = (int) $_POST['provider_id'];
    
    $archive_sql = "UPDATE providers SET is_archived = NOT is_archived WHERE id = ?";
    $archive_stmt = $conn->prepare($archive_sql);
    $archive_stmt->bind_param('i', $provider_id);

    if ($archive_stmt->execute()) {
        $_SESSION['success'] = "Provider archive status updated successfully!";
    } else {
        $_SESSION['fail'] = "Failed to update provider status.";
    }
    
    header("Location: providers.php");
    exit();
}

// Build SQL query based on filter
if ($archive_filter === '1') {
    $providers_sql = "SELECT * FROM providers WHERE is_archived = 1 ORDER BY name ASC";
} elseif ($archive_filter === 'all') {
    $providers_sql = "SELECT * FROM providers ORDER BY name ASC";
} else { // Default: Active only
    $providers_sql = "SELECT * FROM providers WHERE is_archived = 0 ORDER BY name ASC";
}

$providers_stmt = $conn->prepare($providers_sql);
$providers_stmt->execute();
$providers_result = $providers_stmt->get_result();
?>

<!DOCTYPE html>
<html>
<head>
    <title>Scholarship Providers - PIT ScholarshipHub</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../assets/css/scholarship_program.css?v=<?= filemtime('../assets/css/scholarship_program.css') ?>">
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
                        <p>Scholarship Providers</p>
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
                <!-- Page Header & Mini Navigation -->
                <div class="page-header">
                    <h2>Scholarship Management</h2>
                    
                    <!-- Filters Form -->
                    <form action="providers.php" method="POST" class="school-year-selector">
                        <label for="archive_status"><strong>Status:</strong></label>
                        <select name="archive_status" id="archive_status">
                            <option value="0" <?= $archive_filter === '0' ? 'selected' : '' ?>>Active Providers</option>
                            <option value="1" <?= $archive_filter === '1' ? 'selected' : '' ?>>Archived Providers</option>
                            <option value="all" <?= $archive_filter === 'all' ? 'selected' : '' ?>>All Providers</option>
                        </select>
                        <button type="submit" name="apply_filters" class="btn btn-sm btn-primary">Apply</button>
                    </form>
                </div>

                <!-- Mini Tab Navigation between Programs and Providers -->
                <div class="sub-nav">
                    <a href="scholarship_program.php" class="sub-nav-item">Scholarship Programs</a>
                    <a href="providers.php" class="sub-nav-item active">Funding Providers</a>
                </div>

                <div class="action-row">
                    <div class="action-row-right">
                        <?php if (hasPermission($conn, $_SESSION['user_id'], 'scholarship_programs', 'add')) : ?>
                            <a href="add_provider.php" class="btn btn-success">+ Add Provider</a>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Providers Grid -->
                <?php if ($providers_result->num_rows > 0) : ?>
                    <div class="programs-grid">
                        <?php while ($row = $providers_result->fetch_assoc()) : 
                            $is_archived = (int) $row['is_archived'] === 1;
                            $card_status_class = $is_archived ? "status-inactive" : "status-active";
                            $badge_class = $is_archived ? 'inactive' : 'active';
                            $badge_text = $is_archived ? 'Archived' : 'Active';
                        ?>
                            <div class="program-card <?= $card_status_class ?>">
                                <div class="program-header">
                                    <h3><?= htmlspecialchars($row['name']) ?></h3>
                                    <span class="program-status <?= $badge_class ?>">
                                        <?= $badge_text ?>
                                    </span>
                                </div>
                                
                                <div class="program-code">
                                    <strong>Code / Abbreviation:</strong> <?= htmlspecialchars($row['code']) ?>
                                </div>
                                <div class="program-code">
                                    <strong>Provider Type:</strong> <?= htmlspecialchars($row['provider_type']) ?>
                                </div>
                                <div class="program-code">
                                    <strong>Contact Person:</strong> <?= !empty($row['contact_person']) ? htmlspecialchars($row['contact_person']) : 'N/A' ?>
                                </div>
                                <div class="program-code">
                                    <strong>Email:</strong> <?= !empty($row['email']) ? htmlspecialchars($row['email']) : 'N/A' ?>
                                </div>
                                <div class="program-code">
                                    <strong>Contact No:</strong> <?= !empty($row['contact_num']) ? htmlspecialchars($row['contact_num']) : 'N/A' ?>
                                </div>

                                <div class="program-actions">
                                    <?php if (hasPermission($conn, $_SESSION['user_id'], 'scholarship_programs', 'edit')) : ?>
                                        <a href="edit_provider.php?id=<?= $row['id'] ?>" class="btn-sm btn-edit">Edit</a>
                                    <?php endif; ?>
                                    
                                    <?php if (hasPermission($conn, $_SESSION['user_id'], 'scholarship_programs', 'archive')) : ?>
                                        <form action="providers.php" method="POST" style="display: inline;">
                                            <input type="hidden" name="provider_id" value="<?= $row['id'] ?>"/>
                                            <?php if (!$is_archived) : ?>
                                                <button type="submit" name="toggle_archive" class="btn-sm btn-archive" onclick="return confirm('Archive this funding provider?')">Archive</button>
                                            <?php else : ?>
                                                <button type="submit" name="toggle_archive" class="btn-sm btn-archive" onclick="return confirm('Unarchive this funding provider?')">Unarchive</button>
                                            <?php endif; ?>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    </div>
                <?php else : ?>
                    <div class="no-data">
                        <p>No scholarship providers found for the selected filter.</p>
                        <a href="add_provider.php" class="btn btn-success" style="margin-top: 10px;">Add your first funding provider</a>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Footer -->
            <div class="footer">
                © 2026 PIT ScholarshipHub. All rights reserved.
            </div>
        </div>
    <?php endif; ?>
</body>
</html>