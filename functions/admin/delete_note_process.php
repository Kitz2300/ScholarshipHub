<?php
require_once __DIR__ . '/../../includes/conn.php';
session_start();

// Get input[cite: 16]
$scholar_id = isset($_POST['scholar_id']) ? (int)$_POST['scholar_id'] : 0;
$note_title = isset($_POST['note_title']) ? trim($_POST['note_title']) : '';

if ($scholar_id <= 0 || empty($note_title)) {
    $_SESSION['fail'] = "Invalid request.";
    header("Location: ../../admin/scholar_profile.php?scholar_id=" . $scholar_id);
    exit();
}

// Fetch the current notes JSON[cite: 16]
$select_sql = "SELECT id, notes FROM scholar_notes WHERE scholar_id = ?";
$select_stmt = $conn->prepare($select_sql);
$select_stmt->bind_param("i", $scholar_id);
$select_stmt->execute();
$result = $select_stmt->get_result();

if ($result->num_rows === 0) {
    $_SESSION['fail'] = "No notes found for this scholar.";
    header("Location: ../../admin/scholar_profile.php?scholar_id=" . $scholar_id);
    exit();
}

$row = $result->fetch_assoc();
$notes_id = $row['id'];
$notes_json = $row['notes'];

// Decode JSON[cite: 16]
$notes_array = json_decode($notes_json, true);
if (!is_array($notes_array)) {
    $_SESSION['fail'] = "Invalid note format.";
    header("Location: ../../admin/scholar_profile.php?scholar_id=" . $scholar_id);
    exit();
}

// Remove the key if it exists[cite: 16]
if (array_key_exists($note_title, $notes_array)) {
    unset($notes_array[$note_title]);
} else {
    $_SESSION['fail'] = "Note title not found.";
    header("Location: ../../admin/scholar_profile.php?scholar_id=" . $scholar_id);
    exit();
}

// Delete row if empty, otherwise update[cite: 16]
if (empty($notes_array)) {
    $delete_sql = "DELETE FROM scholar_notes WHERE id = ?";
    $delete_stmt = $conn->prepare($delete_sql);
    $delete_stmt->bind_param("i", $notes_id);
    if ($delete_stmt->execute()) {
        // ========== AUDIT TRAIL LOGGING ==========
        $user_id = $_SESSION['user_id'] ?? $_SESSION['id'] ?? 0;
        $action = "Deleted note '$note_title' for Scholar ID $scholar_id (no notes remaining)";
        $audit_sql = "INSERT INTO audit_trail (user_id, action) VALUES (?, ?)";
        $audit_stmt = $conn->prepare($audit_sql);
        $audit_stmt->bind_param("is", $user_id, $action);
        $audit_stmt->execute();
        $audit_stmt->close();

        $_SESSION['success'] = "Note deleted successfully (no notes left).";
    } else {
        $_SESSION['fail'] = "Failed to delete note row.";
    }
    $delete_stmt->close();
} else {
    // Update with new JSON[cite: 16]
    $new_json = json_encode($notes_array, JSON_UNESCAPED_UNICODE);
    $update_sql = "UPDATE scholar_notes SET notes = ? WHERE id = ?";
    $update_stmt = $conn->prepare($update_sql);
    $update_stmt->bind_param("si", $new_json, $notes_id);
    if ($update_stmt->execute()) {
        // ========== AUDIT TRAIL LOGGING ==========
        $user_id = $_SESSION['user_id'] ?? $_SESSION['id'] ?? 0;
        $action = "Deleted note '$note_title' for Scholar ID $scholar_id";
        $audit_sql = "INSERT INTO audit_trail (user_id, action) VALUES (?, ?)";
        $audit_stmt = $conn->prepare($audit_sql);
        $audit_stmt->bind_param("is", $user_id, $action);
        $audit_stmt->execute();
        $audit_stmt->close();

        $_SESSION['success'] = "Note deleted successfully.";
    } else {
        $_SESSION['fail'] = "Failed to update notes.";
    }
    $update_stmt->close();
}

$select_stmt->close();
$conn->close();

header("Location: ../../admin/scholar_profile.php?scholar_id=" . $scholar_id);
exit();
?>