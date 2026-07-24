<?php 
include __DIR__ . "/../includes/conn.php";
session_start();

// show error message if any
if(isset($_SESSION['fail'])) {
    echo "<script>alert('" . $_SESSION['fail'] . "')</script>";
    unset($_SESSION['fail']);
}

?>

<html>
    <head>
        <title>Export Scholar Records</title>
    </head>
    <body>
        <!-- check if logged in or not -->
        <?php if(!isset($_SESSION['user_id']) || $_SESSION['role'] !== "admin") : ?>
            <script>alert('You are not logged in')</script>
            <?php $_SESSION = []; ?>
            <script>window.location.href='login.php'</script>
            <!-- if logged in, load page -->
        <?php else: ?>
            <h1>Export Scholar Record</h1>
            <a href="scholar_list.php">Back</a>

            <hr>

            <h3>Export Options</h3>

            <hr>
            
            
            <form action="../functions/admin/export_scholars_process.php" method="POST">
                <h4>Select Filters:</h4>
                <!-- filters and column selection options for exporting the file -->

                <!-- scholarship program filter selection -->
                <label for="scholar_program">Scholarship Program</label>
                <select name="scholar_program" id="scholar_program" required>
                    <option value="all">All Scholarship Program</option>
                <?php 
                $scholarship_program_sql = "SELECT name, code FROM scholarship_program WHERE is_active = 1";
                $scholarship_program_stmt = $conn->prepare($scholarship_program_sql);
                $scholarship_program_stmt->execute();
                $scholarship_program_result = $scholarship_program_stmt->get_result();
                
                while($row=$scholarship_program_result->fetch_assoc()) : ?>
                    <option value="<?= $row['name'] ?>"><?= $row['name'] ?> (<?= $row['code'] ?>)</option>
                <?php endwhile; ?>
                </select><br><br>

                <!-- program filter selection -->
                <label for="program">Program:</label>
                <select name="program" id="program" required>
                    <option value="all">All Program</option>
                    <?php 
                    $program_sql = "SELECT name FROM program";
                    $program_stmt = $conn->prepare($program_sql);
                    $program_stmt->execute();
                    $program_result = $program_stmt->get_result();

                    while($row=$program_result->fetch_assoc()) : ?>
                        <option value="<?= $row['name'] ?>"><?= $row['name'] ?></option>
                    <?php endwhile; ?>
                </select>

                <hr>

                <h4>Select columns to include:</h4>

                <!-- checkbox required fields which shows checked and user cannot uncheck -->
                <h4>Required Fields</h4>
                <input type="checkbox" id="student_id" checked disabled/>
                <label for="student_id">Student ID (Required)</label><br>

                <input type="checkbox" id="last_name" checked disabled/>
                <label for="last_name">Last Name (Required)</label><br>
                
                <input type="checkbox" id="given_name" checked disabled/>
                <label for="given_name">Given Name (Required)</label><br>
                
                <input type="checkbox" id="ext_name" checked disabled/>
                <label for="ext_name">Extended Name (Required)</label><br>
                
                <input type="checkbox" id="middle_name" checked disabled/>
                <label for="middle_name">Middle Name (Required)</label><br>
                
                <input type="checkbox" id="program_name" checked disabled/>
                <label for="program_name">Program Name (Required)</label><br>

                <input type="checkbox" id="scholarship" checked disabled/>
                <label for="scholarship">Scholarship Program (Required)</label><br>
                
                <input type="checkbox" id="year_level" checked disabled/>
                <label for="year_level">Year Level (Required)</label><br>
                
                <h4>Optional Fields</h4>

                <!-- checkbox for optional fields -->
                <input type="checkbox" id="father_name" name="columns[]" value="father_name" checked/>
                <label for="father_name">Father's Name</label><br>
                
                <input type="checkbox" id="mother_name" name="columns[]" value="mother_name" checked/>
                <label for="mother_name">Mother's Name</label><br>
                
                <input type="checkbox" id="permanent_address" name="columns[]" value="permanent_address" checked/>
                <label for="permanent_address">Permanent Address</label><br>
                
                <input type="checkbox" id="disability" name="columns[]" value="disability" checked/>
                <label for="disability">Disability</label><br>
                
                <input type="checkbox" id="contact" name="columns[]" value="contact" checked/>
                <label for="contact">Contact Number</label><br>

                <input type="checkbox" id="birthdate" name="columns[]" value="birthdate" checked/>
                <label for="birthdate">Birthdate</label><br>

                <input type="checkbox" id="sex" name="columns[]" value="sex" checked/>
                <label for="sex">Sex</label><br>

                <input type="checkbox" id="email" name="columns[]" value="email" checked/>
                <label for="email">Email Address:</label><br>
                
                <input type="checkbox" id="ip_group" name="columns[]" value="ip_group" checked/>
                <label for="ip_group">IP Group</label><br>
                
                <!-- dynamic checkbox for extra fields (if any) to include or not -->
                <?php 
                $extra_keys = [];
                $extra_sql = "SELECT notes FROM scholar_notes WHERE notes IS NOT NULL AND notes <> ''";
                $extra_stmt = $conn->prepare($extra_sql);
                $extra_stmt->execute();
                $extra_result = $extra_stmt->get_result();

                while($row = $extra_result->fetch_assoc()) {
                    // data from db is a json so we decode it
                    $data = json_decode($row['notes'], true);
                    if(is_array($data)) {
                        foreach(array_keys($data) as $key) {
                            if(!in_array($key, $extra_keys)) {
                                $extra_keys[] = $key;
                            }
                        }
                    }
                }

                if(!empty($extra_keys)) : ?>
                    <p><strong>Extra Fields (from scholar notes):</strong></p>
                    <?php foreach($extra_keys as $key) : ?>
                        <input type="checkbox" id="extra_<?= $key ?>" name="columns[]" value="extra_<?= $key ?>"/>
                        <label for="extra_<?= $key ?>" class="optional"><?= htmlspecialchars($key) ?></label><br>
                    <?php endforeach;
                endif;
                ?>
                
                <hr>

                <!-- filename input to name the file -->
                <label for="filename">Filename:</label>
                <input type="text" name="filename" id="filename" required/><br><br>

                <button name="export" onclick="return confirm('Export scholar list?')" >Export</button>
            </form>
            
        <?php endif; ?>
    </body>
</html>