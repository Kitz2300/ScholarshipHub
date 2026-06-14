<?php 
session_start();

// check if user is a scholar or admin/staff before clearing session variables and destroying session
$is_scholar = $_SESSION['role'] === 'admin' || $_SESSION['role'] === 'staff' ? false : true;
$is_staff = $_SESSION['role'] === 'staff' ? true : false;

//clear all session variables
$_SESSION = array();

// destroy sesssion
session_destroy();

// if scholar redirect to scholar login page, else redirect to admin login page
if($is_scholar) {
    header("Location: ../student/login.php");
    exit();
} else if($is_staff) {
    header("Location: ../staff/login.php");
    exit();
} else {
    header("Location: ../admin/login.php");
    exit();
}

?>