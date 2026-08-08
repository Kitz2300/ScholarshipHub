<?php
include __DIR__ . '/../includes/conn.php';

$type = $_POST['type'] ?? '';
$parent_id = (int) ($_POST['parent_id'] ?? 0);

$response = [];

if($type === 'regions') {
    $sql = "SELECT id, name FROM psgc WHERE is_reg = 1 ORDER BY name";
    $result = $conn->query($sql);
    while($row = $result->fetch_assoc()) {
        $response[] = $row;
    }
} elseif($type === 'provinces') {
    $sql = "SELECT id, name FROM psgc WHERE is_prov = 1 AND parent_id = ? ORDER BY name";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $parent_id);
    $stmt->execute();
    $result = $stmt->get_result();
    while($row = $result->fetch_assoc()) {
        $response[] = $row;
    }
} elseif($type === 'cities') {
    $sql = "SELECT id, name FROM psgc WHERE (is_city = 1 OR is_municipality = 1) AND parent_id = ? ORDER BY name";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $parent_id);
    $stmt->execute();
    $result = $stmt->get_result();
    while($row = $result->fetch_assoc()) {
        $response[] = $row;
    }
} elseif($type === 'barangays') {
    $sql = "SELECT id, name FROM psgc WHERE is_bgy = 1 AND parent_id = ? ORDER BY name";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $parent_id);
    $stmt->execute();
    $result = $stmt->get_result();
    while($row = $result->fetch_assoc()) {
        $response[] = $row;
    }
}

header('Content-Type: application/json');
echo json_encode($response);
?>