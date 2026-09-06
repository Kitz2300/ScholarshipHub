<?php
require_once __DIR__ . '/../../includes/conn.php';
session_start();

// Get and validate inputs
$scholar_id = isset($_POST['scholar_id']) ? (int)$_POST['scholar_id'] : 0;
$title = isset($_POST['title']) ? trim($_POST['title']) : '';
$content = isset($_POST['content']) ? trim($_POST['content']) : '';

if ($scholar_id <= 0 || empty($title) || empty($content)) {
    $_SESSION['fail'] = "All fields are required.";
    header("Location: ../../admin/scholar_profile.php?scholar_id=" . $scholar_id);
    exit();
}

// Sanitize (basic)
$title = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');
$content = htmlspecialchars($content, ENT_QUOTES, 'UTF-8');

// Check if a note row already exists for this scholar[cite: 15]
$check_sql = "SELECT id, notes FROM scholar_notes WHERE scholar_id = ?";
$check_stmt = $conn->prepare($check_sql);
$check_stmt->bind_param("i", $scholar_id);
$check_stmt->execute();
$check_result = $check_stmt->get_result();

if ($check_result->num_rows > 0) {
    // Existing row – update[cite: 15]
    $row = $check_result->fetch_assoc();
    $notes_id = $row['id'];
    $existing_json = $row['notes'];

    // Decode existing JSON, if invalid, start fresh[cite: 15]
    $notes_array = json_decode($existing_json, true);
    if (!is_array($notes_array)) {
        $notes_array = [];
    }

    // Add/update the new note (title => content)[cite: 15]
    $notes_array[$title] = $content;

    // Encode back to JSON[cite: 15]
    $new_json = json_encode($notes_array, JSON_UNESCAPED_UNICODE);

    // Update the row[cite: 15]
    $update_sql = "UPDATE scholar_notes SET notes = ? WHERE id = ?";
    $update_stmt = $conn->prepare($update_sql);
    $update_stmt->bind_param("si", $new_json, $notes_id);

    if ($update_stmt->execute()) {
        // ========== AUDIT TRAIL LOGGING ==========
        $user_id = $_SESSION['user_id'] ?? $_SESSION['id'] ?? 0;
        $action = "Updated notes for Scholar ID $scholar_id: Added/updated note titled '$title'";

        $audit_sql = "INSERT INTO audit_trail (user_id, action) VALUES (?, ?)";
        $audit_stmt = $conn->prepare($audit_sql);
        $audit_stmt->bind_param("is", $user_id, $action);
        $audit_stmt->execute();
        $audit_stmt->close();

        $_SESSION['success'] = "Note added successfully.";
    } else {
        $_SESSION['fail'] = "Failed to update note: " . $conn->error;
    }
    $update_stmt->close();
} else {
    // No row – insert new[cite: 15]
    $notes_array = [$title => $content];
    $new_json = json_encode($notes_array, JSON_UNESCAPED_UNICODE);

    $insert_sql = "INSERT INTO scholar_notes (scholar_id, notes) VALUES (?, ?)";
    $insert_stmt = $conn->prepare($insert_sql);
    $insert_stmt->bind_param("is", $scholar_id, $new_json);

    if ($insert_stmt->execute()) {
        // ========== AUDIT TRAIL LOGGING ==========
        $user_id = $_SESSION['user_id'] ?? $_SESSION['id'] ?? 0;
        $action = "Added new note record for Scholar ID $scholar_id: '$title'";

        $audit_sql = "INSERT INTO audit_trail (user_id, action) VALUES (?, ?)";
        $audit_stmt = $conn->prepare($audit_sql);
        $audit_stmt->bind_param("is", $user_id, $action);
        $audit_stmt->execute();
        $audit_stmt->close();

        $_SESSION['success'] = "Note added successfully.";
    } else {
        $_SESSION['fail'] = "Failed to add note: " . $conn->error;
    }
    $insert_stmt->close();
}

$check_stmt->close();
$conn->close();

// Redirect back to the scholar profile[cite: 15]
header("Location: ../../admin/scholar_profile.php?scholar_id=" . $scholar_id);
exit();
?>