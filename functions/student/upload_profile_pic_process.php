<?php
require_once __DIR__ . '/../../includes/conn.php';
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    $_SESSION['fail'] = "Unauthorized access.";
    header("Location: ../../student/login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_photo'])) {
    $scholar_id = (int) $_SESSION['user_id'];

    if (!isset($_FILES['profile_picture']) || $_FILES['profile_picture']['error'] !== UPLOAD_ERR_OK) {
        $_SESSION['fail'] = "Please select a valid image file.";
        header("Location: ../../student/profile.php");
        exit();
    }

    $file = $_FILES['profile_picture'];
    $max_size = 2 * 1024 * 1024; // 2MB limit
    $allowed_exts = ['jpg', 'jpeg', 'png', 'webp'];

    // Validate size
    if ($file['size'] > $max_size) {
        $_SESSION['fail'] = "File size exceeds the 2MB limit.";
        header("Location: ../../student/profile.php");
        exit();
    }

    // Validate extension
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $allowed_exts)) {
        $_SESSION['fail'] = "Invalid image format. Allowed: JPG, JPEG, PNG, WEBP.";
        header("Location: ../../student/profile.php");
        exit();
    }

    $target_dir = __DIR__ . '/../../assets/images/profile_picture/';
    if (!file_exists($target_dir)) {
        mkdir($target_dir, 0777, true);
    }

    // Fetch old photo to delete
    $old_sql = "SELECT profile_picture FROM scholar_info WHERE id = ?";
    $old_stmt = $conn->prepare($old_sql);
    $old_stmt->bind_param("i", $scholar_id);
    $old_stmt->execute();
    $old_res = $old_stmt->get_result();
    if ($old_row = $old_res->fetch_assoc()) {
        $old_pic = trim($old_row['profile_picture'] ?? '');
        if (!empty($old_pic) && file_exists($target_dir . $old_pic)) {
            unlink($target_dir . $old_pic);
        }
    }
    $old_stmt->close();

    // Generate unique filename
    $new_filename = "scholar_" . $scholar_id . "_" . time() . "." . $ext;
    $destination = $target_dir . $new_filename;

    if (move_uploaded_file($file['tmp_name'], $destination)) {
        $update_sql = "UPDATE scholar_info SET profile_picture = ? WHERE id = ?";
        $update_stmt = $conn->prepare($update_sql);
        $update_stmt->bind_param("si", $new_filename, $scholar_id);

        if ($update_stmt->execute()) {
            $_SESSION['success'] = "Profile picture updated successfully!";
        } else {
            $_SESSION['fail'] = "Failed to update record in database.";
        }
        $update_stmt->close();
    } else {
        $_SESSION['fail'] = "Failed to save uploaded image.";
    }

    header("Location: ../../student/profile.php");
    exit();
}