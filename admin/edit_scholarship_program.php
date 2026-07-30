<?php 
include __DIR__ . "/../includes/conn.php";
session_start();

// get id from url
$scholarship_id =  (int) ($_GET['id'] ?? 0);;

// initialize variables
$name = "";
$code = "";
$description = "";
$gwa_req = 0.00;
$pay = 0.00;
$is_grant = 0;
$is_active = 0;

$scholarship_sql = "SELECT * FROM scholarship_program WHERE id = ?";
$scholarship_stmt = $conn->prepare($scholarship_sql);
$scholarship_stmt->bind_param('i', $scholarship_id);
$scholarship_stmt->execute();
$scholarship_result = $scholarship_stmt->get_result();


if($scholarship_result->num_rows > 0) {
    if($row=$scholarship_result->fetch_assoc()) {
        $name = $row['name'];
        $code = $row['code'];
        $description = $row['description'];
        $gwa_req = $row['min_gwa_req'];
        $pay = $row['pay_amount'];
        $is_grant = $row['financial_grant'];
        $is_active = $row['is_active'];
    }
} else {
    // alert user if query returned 0 results
    $_SESSION['fail'] = "Scholarship program does not exist!";
    header("Location: scholarship_program.php");
    exit();
}
?>

<html>
    <head>
        <title>Edit Scholarship Program</title>
    </head>
    <body>
        <!-- check if logged in or not -->
        <?php if(!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') :?>
            <script>alert('You are not logged in')</script>
            <script>window.location.href='login.php'</script>
        <?php else : ?>
            <h1>Edit Scholarship Program</h1>

            <!-- edit form -->
            <form action="../functions/admin/edit_scholarship_process.php" method="POST">
                <label for="name" >Scholarship Name:</label><br>
                <input type="text" name="name" id="name" value="<?= htmlspecialchars($name) ?>" required/><br><br>
                
                <label for="code">Scholarship Code:</label><br>
                <input type="text" name="code" id="code" value="<?= htmlspecialchars($code) ?>" required/><br><br>

                <label for="desc">Description:</label><br>
                <textarea name="desc" id="desc" style="width: 500px; height: 300px;" required><?= htmlspecialchars($description) ?></textarea><br><br>

                <label for="gwa_req">Minimum GWA Requirement (Optional):</label><br>
                <input type="number" name="gwa_req" id="gwa_req" step="0.01" min="1.0" max="4.0" placeholder="e.g., 2.5 or leave blank" value="<?= htmlspecialchars($gwa_req) ?>"/>
                <small>Leave blank if no GWA requirement.</small><br><br>

                <label for="pay_amount">Allowance:</label><br>
                ₱ <input type="number" name="pay_amount" id="pay_amount" value="<?= htmlspecialchars($pay) ?>" required/><br><br>

                <label for="financial_grant">Financial grant?</label>
                <input type="checkbox" name="is_grant" id="financial_grant" <?= $is_grant == 1 ? "checked" : "" ?> value="1"/><br><br>

                <label for="is_active">Is the program still active?</label><br>
                <select name="is_active" required>
                    <option value="1" <?= $is_active == 1 ? "selected" : "" ?>>Active</option>
                    <option value="0" <?= $is_active == 0 ? "selected" : "" ?>>Inactive</option>
                </select><br><br>

                <input type="hidden" value="<?= $scholarship_id ?>" name="scholarship_id" />

                <button name="update">Update</button>
            </form>
            <button onclick="window.location.href='scholarship_program.php'">Cancel</button>
        <?php endif; ?>
    </body>
</html>