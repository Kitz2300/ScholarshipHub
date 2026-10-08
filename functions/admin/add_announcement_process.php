<?php
include __DIR__ . "/../../includes/conn.php";
require_once __DIR__ . "/../../includes/file_upload_helper.php";
session_start();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add'])) {
    $title = trim($_POST['title']);
    $scholarship_program_id = (int)$_POST['scholarship_program_id'];
    $content = trim($_POST['content']);
    $user_id = $_SESSION['user_id'];

    // Process multiple attachments via upload helper (preserves original filenames)
    $uploaded_paths = uploadAnnouncementAttachments('attachments');

    if ($uploaded_paths === false) {
        $_SESSION['fail'] = "Invalid file or file size exceeds limit (Max 10MB per file).";
        header("Location: ../../admin/announcements.php");
        exit();
    }

    // Save array of paths as JSON string in database
    $attachment_path = !empty($uploaded_paths) ? json_encode($uploaded_paths) : null;

    $sql = "INSERT INTO announcements (title, content, scholarship_program_id, user_id, attachment, is_active, created_at) 
            VALUES (?, ?, ?, ?, ?, 1, NOW())";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssiis", $title, $content, $scholarship_program_id, $user_id, $attachment_path);

    if ($stmt->execute()) {
        $_SESSION['success'] = "Announcement created successfully.";
    } else {
        $_SESSION['fail'] = "Failed to create announcement.";
    }

    header("Location: ../../admin/announcements.php");
    exit();
}