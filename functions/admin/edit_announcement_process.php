<?php
include __DIR__ . "/../../includes/conn.php";
require_once __DIR__ . "/../../includes/file_upload_helper.php";
session_start();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $announcement_id = (int)$_POST['announcement_id'];
    $title = trim($_POST['title']);
    $scholarship_program_id = (int)$_POST['scholarship_program_id'];
    $content = trim($_POST['content']);
    $remove_attachment = isset($_POST['remove_attachment']) ? 1 : 0;
    $remove_specific = $_POST['remove_specific'] ?? [];

    // Retrieve existing attachments
    $fetch_stmt = $conn->prepare("SELECT attachment FROM announcements WHERE id = ?");
    $fetch_stmt->bind_param("i", $announcement_id);
    $fetch_stmt->execute();
    $existing = $fetch_stmt->get_result()->fetch_assoc();
    $fetch_stmt->close();

    $raw_attachment = $existing['attachment'] ?? null;
    $existing_paths = [];

    if (!empty($raw_attachment)) {
        $decoded = json_decode($raw_attachment, true);
        $existing_paths = is_array($decoded) ? $decoded : [$raw_attachment];
    }

    // Handle full removal
    if ($remove_attachment) {
        deleteAnnouncementAttachments($raw_attachment);
        $existing_paths = [];
    } elseif (!empty($remove_specific)) {
        // Handle specific attachment removals
        $remaining_paths = [];
        foreach ($existing_paths as $path) {
            if (in_array($path, $remove_specific)) {
                deleteAnnouncementAttachment($path);
            } else {
                $remaining_paths[] = $path;
            }
        }
        $existing_paths = $remaining_paths;
    }

    // Handle new file uploads (preserves original filenames)
    $new_paths = uploadAnnouncementAttachments('attachments');
    if ($new_paths === false) {
        $_SESSION['fail'] = "Invalid file or upload error (Max 10MB per file).";
        header("Location: ../../admin/announcements.php");
        exit();
    }

    // Merge remaining existing attachments with newly uploaded ones
    $final_paths = array_merge($existing_paths, $new_paths);
    $attachment_path = !empty($final_paths) ? json_encode($final_paths) : null;

    $sql = "UPDATE announcements SET title = ?, content = ?, scholarship_program_id = ?, attachment = ? WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssisi", $title, $content, $scholarship_program_id, $attachment_path, $announcement_id);

    if ($stmt->execute()) {
        $_SESSION['success'] = "Announcement updated successfully.";
    } else {
        $_SESSION['fail'] = "Failed to update announcement.";
    }

    header("Location: ../../admin/announcements.php");
    exit();
}