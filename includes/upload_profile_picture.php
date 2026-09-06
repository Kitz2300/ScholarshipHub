<?php
function handleProfilePictureUpload($file_input, $user_id, $prefix = 'user', $old_picture = null) {
    if (!isset($_FILES[$file_input]) || $_FILES[$file_input]['error'] !== UPLOAD_ERR_OK) {
        return ['status' => false, 'message' => 'No file uploaded or upload error occurred.'];
    }

    $file = $_FILES[$file_input];
    $allowed_extensions = ['jpg', 'jpeg', 'png', 'webp'];
    $allowed_mimes = ['image/jpeg', 'image/png', 'image/webp'];
    $max_size = 2 * 1024 * 1024; // 2MB Limit

    // Validate File Size
    if ($file['size'] > $max_size) {
        return ['status' => false, 'message' => 'File size exceeds the 2MB limit.'];
    }

    // Validate Extension and MIME Type
    $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime_type = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    if (!in_array($file_ext, $allowed_extensions) || !in_array($mime_type, $allowed_mimes)) {
        return ['status' => false, 'message' => 'Invalid file format. Only JPG, PNG, and WEBP are allowed.'];
    }

    // Generate Unique Filename
    $new_filename = $prefix . '_' . $user_id . '_' . time() . '.' . $file_ext;
    $upload_dir = __DIR__ . '/../../uploads/profile_pics/';

    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }

    $target_path = $upload_dir . $new_filename;

    if (move_uploaded_file($file['tmp_name'], $target_path)) {
        // Delete old picture if it exists
        if (!empty($old_picture) && file_exists($upload_dir . $old_picture)) {
            unlink($upload_dir . $old_picture);
        }
        return ['status' => true, 'filename' => $new_filename];
    }

    return ['status' => false, 'message' => 'Failed to move uploaded file.'];
}
?>