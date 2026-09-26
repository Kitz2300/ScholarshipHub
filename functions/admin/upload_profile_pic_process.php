<?php
require_once __DIR__ . '/../../includes/conn.php';
session_start();

// Ensure only authenticated Admin or Staff can access
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'] ?? '', ['admin', 'staff'])) {
    $_SESSION['fail'] = "Unauthorized access.";
    header("Location: " . ($_SERVER['HTTP_REFERER'] ?? '../../index.php'));
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

if ($_SERVER['REQUEST_METHOD'] === 'POST' && (isset($_POST['update_profile']) || isset($_POST['upload_photo']))) {
    $user_id = (int) $_SESSION['user_id'];
    $role = strtolower($_SESSION['role'] ?? 'staff');

    // Separate Cloudinary folders for Admin vs Staff
    $folder = ($role === 'admin') ? 'profile_pictures/admin' : 'profile_pictures/staff';

    // Update First Name, Middle Name, Last Name if submitted in the form
    if (isset($_POST['given_name']) && isset($_POST['last_name'])) {
        $given_name = trim($_POST['given_name']);
        $middle_name = trim($_POST['middle_name'] ?? '');
        $last_name = trim($_POST['last_name']);

        if (!empty($given_name) && !empty($last_name)) {
            $name_sql = "UPDATE scholarship_users SET given_name = ?, middle_name = ?, last_name = ? WHERE id = ?";
            $name_stmt = $conn->prepare($name_sql);
            $name_stmt->bind_param("sssi", $given_name, $middle_name, $last_name, $user_id);
            $name_stmt->execute();
            $name_stmt->close();
        }
    }

    // Process profile picture upload if provided
    if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['profile_picture'];
        $max_size = 2 * 1024 * 1024; // 2MB limit
        $allowed_exts = ['jpg', 'jpeg', 'png', 'webp'];

        // Validate size
        if ($file['size'] > $max_size) {
            $_SESSION['fail'] = "File size exceeds the 2MB limit.";
            header("Location: " . ($_SERVER['HTTP_REFERER'] ?? '../../index.php'));
            exit();
        }

        // Validate extension
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, $allowed_exts)) {
            $_SESSION['fail'] = "Invalid image format. Allowed: JPG, JPEG, PNG, WEBP.";
            header("Location: " . ($_SERVER['HTTP_REFERER'] ?? '../../index.php'));
            exit();
        }

        // Attempt Primary Upload to Cloudinary (for Railway/Cloud)
        $image_destination = uploadToCloudinary($file['tmp_name'], $folder);

        // Fallback to Local Directory Upload (for XAMPP/Local environment)
        if (!$image_destination) {
            $target_dir = __DIR__ . '/../../assets/images/profile_picture/';
            if (!file_exists($target_dir)) {
                mkdir($target_dir, 0777, true);
            }

            $prefix = ($role === 'admin') ? 'admin_' : 'staff_';
            $new_filename = $prefix . $user_id . "_" . time() . "." . $ext;
            $local_path = $target_dir . $new_filename;

            if (move_uploaded_file($file['tmp_name'], $local_path)) {
                $image_destination = $new_filename;
            }
        }

        if ($image_destination) {
            $target_dir = __DIR__ . '/../../assets/images/profile_picture/';

            // Delete old local picture file if it exists locally
            $old_sql = "SELECT profile_picture FROM scholarship_users WHERE id = ?";
            $old_stmt = $conn->prepare($old_sql);
            $old_stmt->bind_param("i", $user_id);
            $old_stmt->execute();
            $old_res = $old_stmt->get_result();
            if ($old_row = $old_res->fetch_assoc()) {
                $old_pic = trim($old_row['profile_picture'] ?? '');
                if (!empty($old_pic) && !preg_match("~^https?://~i", $old_pic) && file_exists($target_dir . $old_pic)) {
                    @unlink($target_dir . $old_pic);
                }
            }
            $old_stmt->close();

            // Update database record with Cloudinary URL or local filename
            $update_sql = "UPDATE scholarship_users SET profile_picture = ? WHERE id = ?";
            $update_stmt = $conn->prepare($update_sql);
            $update_stmt->bind_param("si", $image_destination, $user_id);

            if ($update_stmt->execute()) {
                $_SESSION['success'] = "Profile updated successfully!";
            } else {
                $_SESSION['fail'] = "Failed to update record in database.";
            }
            $update_stmt->close();
        } else {
            $_SESSION['fail'] = "Failed to save uploaded image.";
        }
    } else {
        $_SESSION['success'] = "Profile details updated successfully!";
    }

    // Redirect back to the page that submitted the form
    header("Location: " . ($_SERVER['HTTP_REFERER'] ?? '../../index.php'));
    exit();
}