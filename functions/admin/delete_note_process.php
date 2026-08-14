<?php
require_once __DIR__ . '/../../includes/conn.php';
session_start();

// Authorisation check
// if (!isset($_SESSION['user_id']) || ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'staff')) {
//     $_SESSION['fail'] = "You are not authorized to delete notes.";
//     header("Location: scholar_list.php");
//     exit();
// }

// Get input
$scholar_id = isset($_POST['scholar_id']) ? (int)$_POST['scholar_id'] : 0;
$note_title = isset($_POST['note_title']) ? trim($_POST['note_title']) : '';

if ($scholar_id <= 0 || empty($note_title)) {
    $_SESSION['fail'] = "Invalid request.";
    header("Location: ../../admin/scholar_profile.php?scholar_id=" . $scholar_id);
    exit();
}

// Fetch the current notes JSON
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

// Decode JSON
$notes_array = json_decode($notes_json, true);
if (!is_array($notes_array)) {
    $_SESSION['fail'] = "Invalid note format.";
    header("Location: ../../admin/scholar_profile.php?scholar_id=" . $scholar_id);
    exit();
}

// Remove the key if it exists
if (array_key_exists($note_title, $notes_array)) {
    unset($notes_array[$note_title]);
} else {
    $_SESSION['fail'] = "Note title not found.";
    header("Location: ../../admin/scholar_profile.php?scholar_id=" . $scholar_id);
    exit();
}

// delete rows if empty
if (empty($notes_array)) {
    $delete_sql = "DELETE FROM scholar_notes WHERE id = ?";
    $delete_stmt = $conn->prepare($delete_sql);
    $delete_stmt->bind_param("i", $notes_id);
    if ($delete_stmt->execute()) {
        $_SESSION['success'] = "Note deleted successfully (no notes left).";
    } else {
        $_SESSION['fail'] = "Failed to delete note row.";
    }
    $delete_stmt->close();
} else {
    // Update with new JSON
    $new_json = json_encode($notes_array, JSON_UNESCAPED_UNICODE);
    $update_sql = "UPDATE scholar_notes SET notes = ? WHERE id = ?";
    $update_stmt = $conn->prepare($update_sql);
    $update_stmt->bind_param("si", $new_json, $notes_id);
    if ($update_stmt->execute()) {
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