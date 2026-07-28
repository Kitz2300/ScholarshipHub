<?php 
include __DIR__ . "/../../includes/conn.php";
session_start();

// initiate action if button pressed
if(isset($_POST['update'])) {
    $scholarship_id = (int) $_POST['scholarship_id'] ?? 0;
    
    // validate scholarship id
    if($scholarship_id === 0) {
        $_SESSION['fail'] = "Scholarship Program does not exist!";
        header("Location: ../../admin/scholarship_program.php");
        exit();
    }

    // store input values
    $name = $_POST['name'];
    $code = $_POST['code'];
    $description = $_POST['desc'];
    $gwa_req = (float) $_POST['gwa_req'];
    $pay = (float) $_POST['pay_amount'];
    $is_grant = isset($_POST['is_grant']) ? 1 : 0;
    $is_active = (int) $_POST['is_active'];

    // validate empty required fields
    if(!empty($name) && !empty($code) && !empty($description)) {
        $update_sql = "UPDATE scholarship_program SET name = ?, code = ?, description = ?, min_gwa_req = ?, pay_amount = ?, financial_grant = ?, is_active = ? WHERE id = ?";
        $update_stmt = $conn->prepare($update_sql);
        $update_stmt->bind_param("sssddiii", $name, $code, $description, $gwa_req, $pay, $is_grant, $is_active, $scholarship_id);
        
        // condition if query is successful or not
        if($update_stmt->execute()) {
            $_SESSION['success'] = "Scholarhip program successfully updated";
            header("Location: ../../admin/scholarship_program.php");
            exit();
        } else {
            $_SESSION['fail'] = "Update Scholarship Program Failed!";
            header("Location: ../../admin/scholarship_program.php");
            exit();
        }
    } else {
        $_SESSION['fail'] = "Required fields empty!";
        header("Location: ../../admin/scholarship_program.php");
        exit();
    }
    
}
?>