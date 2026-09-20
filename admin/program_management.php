<?php
include '../includes/conn.php';
include '../includes/check_permission.php';
session_start();

if($_SESSION['role'] !== 'admin') {
    checkPageAccess($conn, $_SESSION['user_id'], 'program_management');
}

// ─── DELETE COLLEGE ───
if(isset($_POST['delete_college'])) {
    $college_id = (int) $_POST['college_id'];
    if($college_id > 0) {
        $sql = "DELETE FROM college WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $college_id);
        if($stmt->execute()) {
            $_SESSION['success'] = "College deleted successfully.";
        } else {
            $_SESSION['fail'] = "Failed to delete college.";
        }
    }
    header("Location: program_management.php");
    exit();
}

// ─── DELETE PROGRAM ───
if(isset($_POST['delete_program'])) {
    $program_id = (int) $_POST['program_id'];
    if($program_id > 0) {
        $sql = "DELETE FROM program WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $program_id);
        if($stmt->execute()) {
            $_SESSION['success'] = "Program deleted successfully.";
        } else {
            $_SESSION['fail'] = "Failed to delete program.";
        }
    }
    header("Location: program_management.php");
    exit();
}

// ─── FETCH DATA ───
$colleges_sql = "SELECT * FROM college ORDER BY college_name ASC";
$colleges_result = $conn->query($colleges_sql);

$programs_sql = "SELECT program.id AS program_id, program.name AS program_name, college.id AS college_id, college.college_name, college.college_code 
                 FROM program 
                 LEFT JOIN college ON program.college_id = college.id 
                 ORDER BY college.college_name ASC, program.name ASC";
$programs_result = $conn->query($programs_sql);
?>

<!DOCTYPE html>
<html>
<head>
    <title>Program & College Management - PIT ScholarshipHub</title>
    <link rel="stylesheet" href="../assets/css/program_management.css?v=<?= filemtime('../assets/css/program_management.css') ?>">
    <link rel="icon" href="../assets/images/PIT%20LOGO.png">
    <link rel="stylesheet" href="../assets/css/modals.css">
</head>
<body>
    <!-- alert messages -->
    <?php include __DIR__ . "/../includes/modal_alerts.php"; ?>
    <?php 
    if(!isset($_SESSION['user_id']) || ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'staff')) : 
        $_SESSION['fail'] = "You are no logged in!";
        header("Location: ../portal.php");
        exit();
    else : ?>
    <div class="container">
        <!-- Header -->
        <div class="header">
            <div class="header-left">
                <img src="../assets/images/PIT%20LOGO.png" alt="PIT Logo">
                <div>
                    <h1>PIT ScholarshipHub</h1>
                    <p>Program & College Management</p>
                </div>
            </div>
            <div class="header-right">
                <span class="welcome-text">Welcome, Admin!</span>
                <form action="../functions/logout.php" method="post" style="display: inline;">
                    <button type="submit" class="logout-btn" onclick="return confirmAction(event, 'Are you sure you want to log out?', 'Logout', true)">Logout</button>
                </form>
            </div>
        </div>

        <?php include __DIR__ . "/../includes/navigation.php"; ?>

        <!-- Page Content -->
        <div class="page-content">
            <div class="page-header">
                <h2>Program & College Management</h2>
            </div>

            <!-- ========== COLLEGE MANAGEMENT ========== -->
            <div class="section-card">
                <div class="section-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; border-bottom: 2px solid #2a6b37; padding-bottom: 10px;">
                    <h3 style="margin: 0; border: none; padding: 0;">Colleges</h3>
                    <?php if(hasPermission($conn, $_SESSION['user_id'], 'program_management', 'add')) : ?>
                        <button type="button" class="btn btn-success btn-sm" onclick="openAddCollege()">Add College</button>
                    <?php endif; ?>
                </div>

                <!-- College Table -->
                <div class="table-wrapper">
                    <table>
                        <thead>
                            <tr>
                                <th>College Name</th>
                                <th>Code</th>
                                <th class="actions-col">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if($colleges_result->num_rows > 0) : ?>
                                <?php while($college = $colleges_result->fetch_assoc()) : ?>
                                    <tr>
                                        <td><?= htmlspecialchars($college['college_name']) ?></td>
                                        <td><?= htmlspecialchars($college['college_code']) ?></td>
                                        <td>
                                            <div class="action-buttons">
                                                <!-- Edit Button (opens modal) -->
                                                <?php if(hasPermission($conn, $_SESSION['user_id'], 'program_management', 'edit')) : ?>
                                                    <button class="btn-sm btn-edit" onclick="openEditCollege(<?= $college['id'] ?>, '<?= htmlspecialchars($college['college_name']) ?>', '<?= htmlspecialchars($college['college_code']) ?>')">Edit</button>
                                                <?php endif; ?>
                                                <!-- Delete Form -->
                                                <form action="<?= $_SERVER['PHP_SELF'] ?>" method="POST" style="display: inline;">
                                                    <input type="hidden" name="college_id" value="<?= $college['id'] ?>">
                                                    <?php if(hasPermission($conn, $_SESSION['user_id'], 'program_management', 'delete')) : ?>
                                                        <button type="submit" name="delete_college" class="btn-sm btn-delete" onclick="return confirmAction(event, 'Delete this college?', 'Delete', true)">Delete</button>
                                                    <?php endif; ?>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else : ?>
                                <tr><td colspan="3" class="no-data">No colleges found.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- ========== PROGRAM MANAGEMENT ========== -->
            <div class="section-card">
                <div class="section-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; border-bottom: 2px solid #2a6b37; padding-bottom: 10px;">
                    <h3 style="margin: 0; border: none; padding: 0;">Programs</h3>
                    <?php if(hasPermission($conn, $_SESSION['user_id'], 'program_management', 'add')) : ?>
                        <button type="button" class="btn btn-success btn-sm" onclick="openAddProgram()">Add Program</button>
                    <?php endif; ?>
                </div>

                <!-- Program Table -->
                <div class="table-wrapper">
                    <table>
                        <thead>
                            <tr>
                                <th>Program Name</th>
                                <th>College</th>
                                <th>College Code</th>
                                <th class="actions-col">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if($programs_result->num_rows > 0) : ?>
                                <?php while($program = $programs_result->fetch_assoc()) : ?>
                                    <tr>
                                        <td><?= htmlspecialchars($program['program_name']) ?></td>
                                        <td><?= htmlspecialchars($program['college_name'] ?? 'N/A') ?></td>
                                        <td><?= htmlspecialchars($program['college_code'] ?? 'N/A') ?></td>
                                        <td>
                                            <div class="action-buttons">
                                                <!-- Edit Button (opens modal) -->
                                                <?php if(hasPermission($conn, $_SESSION['user_id'], 'program_management', 'edit')) : ?>
                                                    <button class="btn-sm btn-edit" onclick="openEditProgram(<?= $program['program_id'] ?>, '<?= htmlspecialchars($program['program_name']) ?>', <?= $program['college_id'] ?? 0 ?>)">Edit</button>
                                                <?php endif; ?>
                                                <!-- Delete Form -->
                                                <form action="<?= $_SERVER['PHP_SELF'] ?>" method="POST" style="display: inline;">
                                                    <input type="hidden" name="program_id" value="<?= $program['program_id'] ?>">
                                                    <?php if(hasPermission($conn, $_SESSION['user_id'], 'program_management', 'delete')) : ?>
                                                        <button type="submit" name="delete_program" class="btn-sm btn-delete" onclick="return confirmAction(event, 'Delete this program?', 'Delete', true)">Delete</button>
                                                    <?php endif; ?>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else : ?>
                                <tr><td colspan="4" class="no-data">No programs found.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Footer -->
        <div class="footer">
            © 2026 PIT ScholarshipHub. All rights reserved.
        </div>
    </div>

    <!-- ========== ADD COLLEGE MODAL ========== -->
    <div id="addCollegeModal">
        <div>
            <h3>Add New College</h3>
            <form action="../functions/admin/add_college_process.php" method="POST">
                <div class="form-group">
                    <label>College Name</label>
                    <input type="text" name="college_name" placeholder="Ex: College of Arts and Sciences" required>
                </div>
                <div class="form-group">
                    <label>College Code</label>
                    <input type="text" name="college_code" placeholder="Ex: CAS" required>
                </div>
                <button type="submit" name="add_college" class="btn btn-success">Add College</button>
                <button type="button" onclick="closeModal()" class="btn btn-secondary">Cancel</button>
            </form>
        </div>
    </div>

    <!-- ========== ADD PROGRAM MODAL ========== -->
    <div id="addProgramModal">
        <div>
            <h3>Add New Program</h3>
            <form action="../functions/admin/add_program_process.php" method="POST">
                <div class="form-group">
                    <label>Program Name</label>
                    <input type="text" name="program_name" placeholder="Ex: Bachelor of Science in Information Technology" required>
                </div>
                <div class="form-group">
                    <label>College</label>
                    <select name="college_id" required>
                        <option value="">Select College</option>
                        <?php 
                        $colleges_result->data_seek(0);
                        while($college = $colleges_result->fetch_assoc()) : ?>
                            <option value="<?= $college['id'] ?>"><?= htmlspecialchars($college['college_name']) ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <button type="submit" name="add_program" class="btn btn-success">Add Program</button>
                <button type="button" onclick="closeModal()" class="btn btn-secondary">Cancel</button>
            </form>
        </div>
    </div>

    <!-- ========== EDIT COLLEGE MODAL ========== -->
    <div id="editCollegeModal">
        <div>
            <h3>Edit College</h3>
            <form action="../functions/admin/edit_college_process.php" method="POST">
                <input type="hidden" name="college_id" id="edit_college_id">
                <div class="form-group">
                    <label>College Name</label>
                    <input type="text" name="college_name" id="edit_college_name" required>
                </div>
                <div class="form-group">
                    <label>College Code</label>
                    <input type="text" name="college_code" id="edit_college_code" required>
                </div>
                <button type="submit" name="edit_college" class="btn btn-success">Update College</button>
                <button type="button" onclick="closeModal()" class="btn btn-secondary">Cancel</button>
            </form>
        </div>
    </div>

    <!-- ========== EDIT PROGRAM MODAL ========== -->
    <div id="editProgramModal">
        <div>
            <h3>Edit Program</h3>
            <form action="../functions/admin/edit_program_process.php" method="POST">
                <input type="hidden" name="program_id" id="edit_program_id">
                <div class="form-group">
                    <label>Program Name</label>
                    <input type="text" name="program_name" id="edit_program_name" required>
                </div>
                <div class="form-group">
                    <label>College</label>
                    <select name="college_id" id="edit_program_college" required>
                        <?php 
                        $colleges_result->data_seek(0);
                        while($college = $colleges_result->fetch_assoc()) : ?>
                            <option value="<?= $college['id'] ?>"><?= htmlspecialchars($college['college_name']) ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <button type="submit" name="edit_program" class="btn btn-success">Update Program</button>
                <button type="button" onclick="closeModal()" class="btn btn-secondary">Cancel</button>
            </form>
        </div>
    </div>

    <script>
        function openAddCollege() {
            document.getElementById('addCollegeModal').style.display = 'flex';
        }
        function openAddProgram() {
            document.getElementById('addProgramModal').style.display = 'flex';
        }
        function openEditCollege(id, name, code) {
            document.getElementById('edit_college_id').value = id;
            document.getElementById('edit_college_name').value = name;
            document.getElementById('edit_college_code').value = code;
            document.getElementById('editCollegeModal').style.display = 'flex';
        }
        function openEditProgram(id, name, collegeId) {
            document.getElementById('edit_program_id').value = id;
            document.getElementById('edit_program_name').value = name;
            document.getElementById('edit_program_college').value = collegeId;
            document.getElementById('editProgramModal').style.display = 'flex';
        }
        function closeModal() {
            document.getElementById('addCollegeModal').style.display = 'none';
            document.getElementById('addProgramModal').style.display = 'none';
            document.getElementById('editCollegeModal').style.display = 'none';
            document.getElementById('editProgramModal').style.display = 'none';
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            if (event.target === document.getElementById('addCollegeModal')) closeModal();
            if (event.target === document.getElementById('addProgramModal')) closeModal();
            if (event.target === document.getElementById('editCollegeModal')) closeModal();
            if (event.target === document.getElementById('editProgramModal')) closeModal();
        }
    </script>
    <?php endif; ?>
</body>
</html>