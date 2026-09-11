<?php
include __DIR__ . '/../../includes/conn.php';
session_start();

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'student') {
    header("Location: ../../login.php");
    exit();
}

$scholar_id = (int) ($_SESSION['user_id'] ?? 0);

if (isset($_POST['change_password'])) {
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    // 1. Check if new password is the same as the current password
    if ($current_password === $new_password) {
        $_SESSION['fail'] = "New password cannot be the same as your current password.";
        header("Location: ../../pages/profile.php");
        exit();
    }

    // 2. Check if new password and confirmation match
    if ($new_password !== $confirm_password) {
        $_SESSION['fail'] = "New password and confirmation do not match.";
        header("Location: ../../pages/profile.php");
        exit();
    }

    // 3. Length check
    if (strlen($new_password) < 8) {
        $_SESSION['fail'] = "New password must be at least 8 characters long.";
        header("Location: ../../pages/profile.php");
        exit();
    }

    // 4. Verify existing password against DB
    $sql = "SELECT password FROM scholar_info WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $scholar_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($row = $result->fetch_assoc()) {
        if (!password_verify($current_password, $row['password'])) {
            $_SESSION['fail'] = "Incorrect current password.";
            header("Location: ../../pages/profile.php");
            exit();
        }

        // 5. Update password and mark as changed
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
        $update_sql = "UPDATE scholar_info SET password = ?, is_password_changed = 1 WHERE id = ?";
        $update_stmt = $conn->prepare($update_sql);
        $update_stmt->bind_param("si", $hashed_password, $scholar_id);

        if ($update_stmt->execute()) {
            $_SESSION['success'] = "Password updated successfully!";
        } else {
            $_SESSION['fail'] = "Failed to update password. Please try again.";
        }
        $update_stmt->close();
    } else {
        $_SESSION['fail'] = "User record not found.";
    }

    $stmt->close();
    header("Location: ../../pages/profile.php");
    exit();
}