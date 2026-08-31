<?php
// includes/address_helper.php

function getProvinceId($conn, $province_name) {
    if (empty($province_name)) return null;
    $sql = "SELECT id, name, parent_id as region_id FROM psgc WHERE is_prov = 1 AND name LIKE ?";
    $stmt = $conn->prepare($sql);
    $like = "%" . trim($province_name) . "%";
    $stmt->bind_param("s", $like);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        return ['id' => $row['id'], 'region_id' => $row['region_id']];
    }
    return null;
}

function getCityId($conn, $city_name, $province_id) {
    if (empty($city_name) || empty($province_id)) return null;
    $sql = "SELECT id FROM psgc WHERE (is_city = 1 OR is_municipality = 1) AND parent_id = ? AND name LIKE ?";
    $stmt = $conn->prepare($sql);
    $like = "%" . trim($city_name) . "%";
    $stmt->bind_param("is", $province_id, $like);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        return $row['id'];
    }
    return null;
}

function getBarangayId($conn, $barangay_name, $city_id) {
    if (empty($barangay_name) || empty($city_id)) return null;
    $sql = "SELECT id FROM psgc WHERE is_bgy = 1 AND parent_id = ? AND name LIKE ?";
    $stmt = $conn->prepare($sql);
    $like = "%" . trim($barangay_name) . "%";
    $stmt->bind_param("is", $city_id, $like);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        return $row['id'];
    }
    return null;
}

function extractBarangayName($street_field) {
    $patterns = [
        '/Brgy\.\s*([^,]+)/i',
        '/Barangay\s*([^,]+)/i',
        '/BRGY\.\s*([^,]+)/i',
        '/Bgy\.\s*([^,]+)/i',
    ];
    foreach ($patterns as $pattern) {
        if (preg_match($pattern, $street_field, $matches)) {
            return trim($matches[1]);
        }
    }
    return '';
}

function buildPermanentAddress($street, $barangay_name, $city_name, $province_name) {
    $parts = array_filter([
        trim($street),
        trim($barangay_name),
        trim($city_name),
        trim($province_name),
        'Philippines'
    ]);
    return implode(', ', $parts);
}