<?php
// includes/school_year_helper.php

function getCurrentSchoolYear() {
    $year = date('Y');
    $next_year = $year + 1;
    return $year . '-' . $next_year;
}

function getAvailableSchoolYears($conn) {
    $sql = "SELECT DISTINCT school_year FROM scholar_info WHERE school_year IS NOT NULL AND is_archived = 0
            UNION
            SELECT DISTINCT school_year FROM scholarship_program WHERE school_year IS NOT NULL AND is_archived = 0 AND is_active = 1
            ORDER BY school_year DESC";
    $result = $conn->query($sql);
    $years = [];
    while($row = $result->fetch_assoc()) {
        $years[] = $row['school_year'];
    }
    // If no data at all, return current year
    if(empty($years)) {
        $years[] = getCurrentSchoolYear();
    }
    return $years;
}

function getScholarshipSchoolYears($conn) {
    $sql = "SELECT DISTINCT school_year FROM scholarship_program WHERE school_year IS NOT NULL AND is_archived = 0 ORDER BY school_year DESC";
    $result = $conn->query($sql);
    $years = [];
    while($row = $result->fetch_assoc()) {
        $years[] = $row['school_year'];
    }

    if(empty($years)) {
        $years[] = getCurrentSchoolYear();
    }
    return $years;
}

function getSelectedSchoolYear() {
    return $_SESSION['selected_school_year'] ?? getCurrentSchoolYear();
}

function setSelectedSchoolYear($year) {
    $_SESSION['selected_school_year'] = $year;
}
if (!function_exists('getSelectedSemester')) {
    function getSelectedSemester() {
        return $_SESSION['selected_semester'] ?? '1st Semester';
    }
}

if (!function_exists('setSelectedSemester')) {
    function setSelectedSemester($semester) {
        $_SESSION['selected_semester'] = $semester;
    }
}
?>