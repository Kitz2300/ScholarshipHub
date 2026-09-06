<?php
require_once __DIR__ . '/../../includes/conn.php';
session_start();

// ─── CHECK STUDENT AUTHORIZATION ───
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    $_SESSION['fail'] = "Unauthorized access.";
    header("Location: ../../student/login.php");
    exit();
}

// ─── PROCESS PASSWORD CHANGE ───
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $scholar_id = (int) $_SESSION['user_id'];
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    // 1. Validation Checks
    if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
        $_SESSION['fail'] = "All password fields are required.";
        header("Location: ../../student/profile.php");
        exit();
    }

    if ($new_password !== $confirm_password) {
        $_SESSION['fail'] = "New password and confirmation do not match.";
        header("Location: ../../student/profile.php");
        exit();
    }

    if (strlen($new_password) < 8) {
        $_SESSION['fail'] = "New password must be at least 8 characters long.";
        header("Location: ../../student/profile.php");
        exit();
    }

    // 2. Fetch current hashed password
    $sql = "SELECT password FROM scholar_info WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $scholar_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($row = $result->fetch_assoc()) {
        $existing_hash = $row['password'];

        // 3. Verify current password
        if (!password_verify($current_password, $existing_hash)) {
            $_SESSION['fail'] = "Incorrect current password.";
            $stmt->close();
            header("Location: ../../student/profile.php");
            exit();
        }

        // 4. Hash new password & update database
        $new_hash = password_hash($new_password, PASSWORD_BCRYPT);
        $update_sql = "UPDATE scholar_info SET password = ? WHERE id = ?";
        $update_stmt = $conn->prepare($update_sql);
        $update_stmt->bind_param("si", $new_hash, $scholar_id);

        if ($update_stmt->execute()) {
            $_SESSION['success'] = "Password updated successfully!";
        } else {
            $_SESSION['fail'] = "Failed to update password. Please try again.";
        }
        
        $update_stmt->close();
    } else {
        $_SESSION['fail'] = "Account record not found.";
    }

    $stmt->close();
    header("Location: ../../student/profile.php");
    exit();
} else {
    header("Location: ../../student/profile.php");
    exit();
}
?>