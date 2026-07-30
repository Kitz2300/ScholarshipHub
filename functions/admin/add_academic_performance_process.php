    <?php 
    include __DIR__ . "/../../includes/conn.php";
    session_start();

    if(isset($_POST['add']) && isset($_POST['scholar_id'])) {
        // store input values in variabled
        $scholar_id = (int) $_POST['scholar_id'] ?? 0;
        $sem = $_POST['sem'] ?? "";
        $gwa = (float) $_POST['gwa'] ?? 0;
        $units = (float) $_POST['units'] ?? 0;
        $year_lvl = "";
        $school_year =  $_POST['start_year'] . " - " . $_POST['end_year'];

        // validate required fields
        if(empty($sem) || $units == 0 || $gwa == 0 ) {
            $_SESSION['fail'] = "All fields are required!";
            header("Location: ../../admin/academic_performance.php?scholar_id=$scholar_id");
            exit();
        }

        // scholar year level query
        $year_lvl_sql = "SELECT year_level FROM scholar_info WHERE id = ?";
        $year_lvl_stmt = $conn->prepare($year_lvl_sql);
        $year_lvl_stmt->bind_param('i', $scholar_id);
        $year_lvl_stmt->execute();
        $year_lvl_result = $year_lvl_stmt->get_result();
        
        if($row=$year_lvl_result->fetch_assoc()) {
            // validate data before store
            if(!empty($row['year_level'])) {
                $year_lvl = $row['year_level'];
            } else {
                $_SESSION['fail'] = "Scholar has no year level!";
                header("Location: ../../admin/academic_performance.php?scholar_id=$scholar_id");
                exit();
            }
        } else {
            $_SESSION['fail'] = "Scholar not found!";
            header("Location: ../../admin/academic_performance.php?scholar_id=$scholar_id");
            exit();
        }
        
        // academic performance query
        $sql = "INSERT INTO academic_performance(scholar_id, semester, gwa, units, year_level, school_year) VALUES( ?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('isddss', $scholar_id, $sem, $gwa, $units, $year_lvl, $school_year);

        if($stmt->execute()) {
            $_SESSION['success'] = "Academic record successfully added";
            header("Location: ../../admin/academic_performance.php?scholar_id=$scholar_id");
            exit();
        } else {
            $_SESSION['fail'] = "Academic record failed to add";
            header("Location: ../../admin/academic_performance.php?scholar_id=$scholar_id");
            exit();
        }

    }
    ?>
