<?php 
include __DIR__ . "/../../includes/conn.php";
session_start();

// initiate action if button pressed
if(isset($_POST['add'])) {
    $name = $_POST['name'] ?? "";
    $code = $_POST['code'] ?? "";
    $school_year = $_POST['start_year'] . " - " . $_POST['end_year'];
    $description = $_POST['desc'] ?? "";
    $gwa_req = $_POST['gwa_req'] ?? 0.00;
    $pay_amount = $_POST['pay_amount'] ?? 0.00;
    $sc_type = $_POST['sc_type'];
    // $financial_grant = isset($_POST['is_grant']) ? $_POST['is_grant'] : 0;
    
    // validate empty required fields
    if(!empty($name) && !empty($code) && !empty($description)) {
        $sql = "INSERT INTO scholarship_program(name, code, school_year, description, min_gwa_req, pay_amount, scholarship_type, is_active) VALUES( ?, ?, ?, ?, ?, ?, ?, 1)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssssdds", $name, $code, $school_year, $description, $gwa_req, $pay_amount, $sc_type);

        // condition if query is successfull or not
        if($stmt->execute()) {
            $_SESSION['success'] = "Scholarship Program Added.";
            header("Location: ../../admin/scholarship_program.php");
            exit();
        } else {
            $_SESSION['fail'] = "Scholarship Program Failed to Add";
            header("Location: ../../admin/add_scholarship.php");
            exit();
        }
    } else {
        $_SESSION['fail'] = "Required fields empty!";
        header("Location: ../../admin/add_scholarship.php");
        exit();
    }
}
?>