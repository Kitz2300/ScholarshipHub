<?php
// includes/upload_profile_picture.php

/**
 * Handles profile picture uploads to Cloudinary with local storage fallback.
 *
 * @param string $file_input Name of the $_FILES input key.
 * @param int    $user_id    ID of the user.
 * @param string $role       User role ('admin' or 'staff') to determine Cloudinary folder.
 * @param string|null $old_picture Existing picture filename or Cloudinary URL to clean up.
 * @return array ['status' => bool, 'filename' => string, 'message' => string]
 */
function handleProfilePictureUpload($file_input, $user_id, $role = 'staff', $old_picture = null) {
    // ─── 1. FILE VALIDATION ───
    if (!isset($_FILES[$file_input]) || $_FILES[$file_input]['error'] !== UPLOAD_ERR_OK) {
        return ['status' => false, 'message' => 'No file uploaded or upload error occurred.'];
    }

    $file = $_FILES[$file_input];
    $allowed_extensions = ['jpg', 'jpeg', 'png', 'webp'];
    $allowed_mimes = ['image/jpeg', 'image/png', 'image/webp'];
    $max_size = 2 * 1024 * 1024; // 2MB Limit

    if ($file['size'] > $max_size) {
        return ['status' => false, 'message' => 'File size exceeds the 2MB limit.'];
    }

    $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime_type = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    if (!in_array($file_ext, $allowed_extensions) || !in_array($mime_type, $allowed_mimes)) {
        return ['status' => false, 'message' => 'Invalid file format. Only JPG, PNG, and WEBP are allowed.'];
    }

    // Determine target role / folder
    $normalized_role = strtolower(trim($role));
    $folder_name = ($normalized_role === 'admin') ? 'profile_pictures/admin' : 'profile_pictures/staff';
    $prefix = ($normalized_role === 'admin') ? 'admin' : 'staff';

    // ─── 2. CLOUDINARY CONFIGURATION ───
    // Fill in your Cloudinary Cloud Name & Unsigned Upload Preset
    $cloud_name    = 'YOUR_CLOUD_NAME';
    $upload_preset = 'YOUR_UNSIGNED_UPLOAD_PRESET'; 

    $cloudinary_success = false;
    $cloudinary_url = '';

    // Attempt Cloudinary upload if Cloud Name & Preset are set
    if (!empty($cloud_name) && $cloud_name !== 'YOUR_CLOUD_NAME' && !empty($upload_preset) && $upload_preset !== 'YOUR_UNSIGNED_UPLOAD_PRESET') {
        $endpoint = "https://api.cloudinary.com/v1_1/{$cloud_name}/image/upload";

        $cfile = new CURLFile($file['tmp_name'], $mime_type, $file['name']);
        $post_data = [
            'file'          => $cfile,
            'upload_preset' => $upload_preset,
            'folder'        => $folder_name,
            'public_id'     => $prefix . '_' . $user_id . '_' . time()
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $endpoint);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);

        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($http_code === 200 && $response) {
            $result = json_decode($response, true);
            if (isset($result['secure_url'])) {
                $cloudinary_url = $result['secure_url'];
                $cloudinary_success = true;
            }
        }
    }

    // ─── 3. CLOUDINARY SUCCESS ───
    if ($cloudinary_success) {
        // Clean up old local file if it exists and was stored locally
        removeOldLocalPicture($old_picture);
        return [
            'status' => true, 
            'filename' => $cloudinary_url,
            'storage' => 'cloudinary'
        ];
    }

    // ─── 4. LOCAL STORAGE FALLBACK ───
    $new_filename = $prefix . '_' . $user_id . '_' . time() . '.' . $file_ext;
    
    // Primary local upload directory
    $upload_dir = __DIR__ . '/../assets/images/profile_picture/';
    if (!is_dir($upload_dir)) {
        @mkdir($upload_dir, 0755, true);
    }

    $target_path = $upload_dir . $new_filename;

    if (move_uploaded_file($file['tmp_name'], $target_path)) {
        // Remove old local file
        removeOldLocalPicture($old_picture);
        return [
            'status' => true, 
            'filename' => $new_filename,
            'storage' => 'local'
        ];
    }

    return ['status' => false, 'message' => 'Failed to save file locally or to Cloudinary.'];
}

/**
 * Helper function to remove old local images from disk.
 */
function removeOldLocalPicture($old_picture) {
    if (empty($old_picture) || preg_match("~^https?://~i", $old_picture)) {
        return; // Ignore empty values or remote Cloudinary URLs
    }

    $local_paths = [
        __DIR__ . '/../assets/images/profile_picture/' . $old_picture,
        __DIR__ . '/../uploads/profile_pics/' . $old_picture,
        __DIR__ . '/../../uploads/profile_pics/' . $old_picture
    ];

    foreach ($local_paths as $path) {
        if (file_exists($path)) {
            @unlink($path);
        }
    }
}
?>