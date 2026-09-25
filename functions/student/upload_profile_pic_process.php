<?php
require_once __DIR__ . '/../../includes/conn.php';
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    $_SESSION['fail'] = "Unauthorized access.";
    header("Location: ../../student/login.php");
    exit();
}

/**
 * Uploads image to Cloudinary via REST API.
 * Pulls credentials from Railway env vars with fallback support.
 */
function uploadToCloudinary($file_tmp_path, $folder = 'profile_pictures') {
    $cloud_name = getenv('CLOUDINARY_CLOUD_NAME') ?: (getenv('cloudinary_cloud_name') ?: ($_ENV['CLOUDINARY_CLOUD_NAME'] ?? ($_ENV['cloudinary_cloud_name'] ?? '')));
    $api_key    = getenv('CLOUDINARY_API_KEY')    ?: (getenv('cloudinary_api_key')    ?: ($_ENV['CLOUDINARY_API_KEY']    ?? ($_ENV['cloudinary_api_key']    ?? '')));
    $api_secret = getenv('CLOUDINARY_API_SECRET') ?: (getenv('cloudinary_api_secret') ?: ($_ENV['CLOUDINARY_API_SECRET'] ?? ($_ENV['cloudinary_api_secret'] ?? '')));

    if (empty($cloud_name) || empty($api_key) || empty($api_secret)) {
        return false;
    }

    $timestamp = time();
    $params_to_sign = "folder={$folder}&timestamp={$timestamp}";
    $signature = sha1($params_to_sign . $api_secret);

    $endpoint = "https://api.cloudinary.com/v1_1/{$cloud_name}/image/upload";

    $post_fields = [
        'file'      => new CURLFile($file_tmp_path),
        'api_key'   => $api_key,
        'timestamp' => $timestamp,
        'signature' => $signature,
        'folder'    => $folder
    ];

    $ch = curl_init($endpoint);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $post_fields);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($http_code === 200 && $response) {
        $result = json_decode($response, true);
        if (isset($result['secure_url'])) {
            return $result['secure_url'];
        }
    }

    error_log("Cloudinary Upload Failed (HTTP $http_code): " . $response);
    return false;
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

    // Attempt Primary Upload to Cloudinary (for Railway/Cloud)
    $image_destination = uploadToCloudinary($file['tmp_name']);

    // Fallback to Local Directory Upload (for XAMPP/Local environment)
    if (!$image_destination) {
        $target_dir = __DIR__ . '/../../assets/images/profile_picture/';
        if (!file_exists($target_dir)) {
            mkdir($target_dir, 0777, true);
        }

        $new_filename = "scholar_" . $scholar_id . "_" . time() . "." . $ext;
        $local_path = $target_dir . $new_filename;

        if (move_uploaded_file($file['tmp_name'], $local_path)) {
            $image_destination = $new_filename;
        }
    }

    if ($image_destination) {
        $target_dir = __DIR__ . '/../../assets/images/profile_picture/';

        // Fetch old photo to delete local file if previously stored locally
        $old_sql = "SELECT profile_picture FROM scholar_info WHERE id = ?";
        $old_stmt = $conn->prepare($old_sql);
        $old_stmt->bind_param("i", $scholar_id);
        $old_stmt->execute();
        $old_res = $old_stmt->get_result();
        if ($old_row = $old_res->fetch_assoc()) {
            $old_pic = trim($old_row['profile_picture'] ?? '');
            if (!empty($old_pic) && !preg_match("~^https?://~i", $old_pic) && file_exists($target_dir . $old_pic)) {
                @unlink($target_dir . $old_pic);
            }
        }
        $old_stmt->close();

        // Update database with Cloudinary URL or local filename
        $update_sql = "UPDATE scholar_info SET profile_picture = ? WHERE id = ?";
        $update_stmt = $conn->prepare($update_sql);
        $update_stmt->bind_param("si", $image_destination, $scholar_id);

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