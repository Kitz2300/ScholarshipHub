<?php 
include __DIR__ . "/../../includes/conn.php";
session_start();

// initiate action if button pressed
if(isset($_POST['add'])) {
    $name = $_POST['name'] ?? "";
    $code = $_POST['code'] ?? "";
    $description = $_POST['desc'] ?? "";
    $gwa_req = $_POST['gwa_req'] ?? 0.00;
    $pay_amount = $_POST['pay_amount'] ?? 0.00;
    $financial_grant = isset($_POST['is_grant']) ? $_POST['is_grant'] : 0;

    // validate empty required fields
    if(!empty($name) && !empty($code) && !empty($description)) {
        $sql = "INSERT INTO scholarship_program(name, code, description, min_gwa_req, pay_amount, financial_grant, is_active) VALUES( ?, ?, ?, ?, ?, ?, 1)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sssddi", $name, $code, $description, $gwa_req, $pay_amount, $financial_grant);

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