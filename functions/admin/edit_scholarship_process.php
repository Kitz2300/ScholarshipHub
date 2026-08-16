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
    $school_year = $_POST['start_year'] . " - " . $_POST['end_year'];
    $description = $_POST['desc'];
    $gwa_req = (float) $_POST['gwa_req'];
    $pay = (float) $_POST['pay_amount'];
    $sc_type = $_POST['sc_type'];
    // $is_grant = isset($_POST['is_grant']) ? 1 : 0;

    // validate empty required fields
    if(!empty($name) && !empty($code) && !empty($description) && !empty($school_year) && !empty($pay) && !empty($sc_type)) {
        $update_sql = "UPDATE scholarship_program SET name = ?, code = ?, school_year = ?, description = ?, min_gwa_req = ?, pay_amount = ?, scholarship_type = ? WHERE id = ?";
        $update_stmt = $conn->prepare($update_sql);
        $update_stmt->bind_param("ssssddsi", $name, $code, $school_year, $description, $gwa_req, $pay, $sc_type, $scholarship_id);
        
        $update_scholar_sql = "UPDATE scholar_info SET school_year = ? WHERE scholarship_program_id = ?";
        $update_scholar_stmt = $conn->prepare($update_scholar_sql);
        $update_scholar_stmt->bind_param('si', $school_year, $scholarship_id);

        // condition if query is successful or not
        if($update_scholar_stmt->execute() && $update_stmt->execute()) {
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