<?php
// includes/upload_helper.php

if (!function_exists('uploadAnnouncementAttachments')) {
    function uploadAnnouncementAttachments($fileInputKey) {
        if (!isset($_FILES[$fileInputKey])) {
            return [];
        }

        $files = $_FILES[$fileInputKey];
        $uploadedPaths = [];

        // Normalize single vs multiple file input structures
        $names    = is_array($files['name'])     ? $files['name']     : [$files['name']];
        $tmpNames = is_array($files['tmp_name']) ? $files['tmp_name'] : [$files['tmp_name']];
        $errors   = is_array($files['error'])    ? $files['error']    : [$files['error']];
        $sizes    = is_array($files['size'])     ? $files['size']     : [$files['size']];
        $types    = is_array($files['type'])     ? $files['type']     : [$files['type']];

        $allowedExts = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'png', 'jpg', 'jpeg', 'webp'];
        $maxSize = 10 * 1024 * 1024; // 10MB per file

        // Cloudinary environment check
        $cloudName = getenv('CLOUDINARY_CLOUD_NAME') ?: (defined('CLOUDINARY_CLOUD_NAME') ? CLOUDINARY_CLOUD_NAME : '');
        $apiKey    = getenv('CLOUDINARY_API_KEY')    ?: (defined('CLOUDINARY_API_KEY')    ? CLOUDINARY_API_KEY    : '');
        $apiSecret = getenv('CLOUDINARY_API_SECRET') ?: (defined('CLOUDINARY_API_SECRET') ? CLOUDINARY_API_SECRET : '');

        for ($i = 0; $i < count($names); $i++) {
            if ($errors[$i] === UPLOAD_ERR_NO_FILE || $errors[$i] !== UPLOAD_ERR_OK) {
                continue;
            }

            if ($sizes[$i] > $maxSize) {
                continue; // Skip oversized files
            }

            $ext = strtolower(pathinfo($names[$i], PATHINFO_EXTENSION));
            if (!in_array($ext, $allowedExts)) {
                continue; // Skip disallowed file types
            }

            // ── Option A: Cloudinary Upload ──
            if (!empty($cloudName) && !empty($apiKey) && !empty($apiSecret)) {
                $timestamp = time();
                $folder = 'announcements';
                $resourceType = in_array($ext, ['png', 'jpg', 'jpeg', 'webp']) ? 'image' : 'raw';
                
                $stringToSign = "folder={$folder}&timestamp={$timestamp}" . $apiSecret;
                $signature = sha1($stringToSign);

                $cFile = new CURLFile($tmpNames[$i], $types[$i], $names[$i]);

                $postData = [
                    'file' => $cFile,
                    'api_key' => $apiKey,
                    'timestamp' => $timestamp,
                    'signature' => $signature,
                    'folder' => $folder
                ];

                $ch = curl_init("https://api.cloudinary.com/v1_1/{$cloudName}/{$resourceType}/upload");
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);

                $response = curl_exec($ch);
                curl_close($ch);

                $json = json_decode($response, true);
                if (isset($json['secure_url'])) {
                    $uploadedPaths[] = $json['secure_url'];
                    continue;
                }
            }

            // ── Option B: Local Fallback Upload (Preserves Original Filename) ──
            $uploadDir = __DIR__ . '/../assets/File_Uploads/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }

            // Sanitize original filename (keep alphanumeric, spaces, hyphens, and underscores)
            $originalName = pathinfo($names[$i], PATHINFO_FILENAME);
            $cleanName = preg_replace("/[^a-zA-Z0-9\.\-\_\s]/", "", $originalName);
            $cleanName = trim($cleanName);
            if (empty($cleanName)) {
                $cleanName = 'attachment_' . time();
            }

            $newFileName = $cleanName . '.' . $ext;
            $targetPath = $uploadDir . $newFileName;

            // Handle name collisions without overwriting existing files
            $counter = 1;
            while (file_exists($targetPath)) {
                $newFileName = $cleanName . '_' . $counter . '.' . $ext;
                $targetPath = $uploadDir . $newFileName;
                $counter++;
            }

            if (move_uploaded_file($tmpNames[$i], $targetPath)) {
                $uploadedPaths[] = '../assets/File_Uploads/' . $newFileName;
            }
        }

        return $uploadedPaths;
    }
}

if (!function_exists('deleteAnnouncementAttachment')) {
    function deleteAnnouncementAttachment($filePathOrUrl) {
        if (empty($filePathOrUrl)) return false;

        // Local file deletion
        if (strpos($filePathOrUrl, '../assets/File_Uploads/') !== false || strpos($filePathOrUrl, 'assets/File_Uploads/') !== false) {
            $relativePath = ltrim(str_replace('../', '', $filePathOrUrl), '/');
            $fullPath = __DIR__ . '/../' . $relativePath;
            if (file_exists($fullPath)) {
                return @unlink($fullPath);
            }
        }
        return false;
    }
}

if (!function_exists('deleteAnnouncementAttachments')) {
    function deleteAnnouncementAttachments($rawPathOrJson) {
        if (empty($rawPathOrJson)) return;

        $decoded = json_decode($rawPathOrJson, true);
        $files = is_array($decoded) ? $decoded : [$rawPathOrJson];

        foreach ($files as $file) {
            deleteAnnouncementAttachment($file);
        }
    }
}
?>