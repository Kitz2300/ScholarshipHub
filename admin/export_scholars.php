<?php 
include __DIR__ . "/../includes/conn.php";
session_start();


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
            
            <!-- TODO: filters for excel file. what scholarship program, college, departmen/program, etc. -->

            <hr>

            <form action="../functions/admin/export_scholars_process.php" method="POST">
                <h4>Select columns to include:</h4>

                <h4>Required Fields</h4>
                <input type="checkbox" id="student_id" name="student_id" checked disabled/>
                <label for="student_id">Student ID (Required)</label><br>

                <input type="checkbox" id="last_name" name="last_name" checked disabled/>
                <label for="last_name">Last Name (Required)</label><br>
                
                <input type="checkbox" id="given_name" name="given_name" checked disabled/>
                <label for="given_name">Given Name (Required)</label><br>
                
                <input type="checkbox" id="ext_name" name="ext_name" checked disabled/>
                <label for="ext_name">Extended Name (Required)</label><br>
                
                <input type="checkbox" id="middle_name" name="middle_name" checked disabled/>
                <label for="middle_name">Middle Name (Required)</label><br>
                
                <input type="checkbox" id="sex" name="sex" checked disabled/>
                <label for="sex">Sex (Required)</label><br>
                
                <input type="checkbox" id="birthdate" name="birthdate" checked disabled/>
                <label for="birthdate">Birthdate (Required)</label><br>
                
                <input type="checkbox" id="program_name" name="program_name" checked disabled/>
                <label for="program_name">Program Name (Required)</label><br>
                
                <input type="checkbox" id="year_level" name="year_level" checked disabled/>
                <label for="year_level">Year Level (Required)</label><br>
                
                <h4>Optional Fields</h4>

                <input type="checkbox" id="father_name" name="father_name" value="father_name"/>
                <label for="father_name">Father's Name</label><br>
                
                <input type="checkbox" id="mother_name" name="mother_name" value="mother_name"/>
                <label for="mother_name">Mother's Name</label><br>
                
                <input type="checkbox" id="permanent_address" name="permanent_address" value="permanent_address"/>
                <label for="permanent_address">Permanent Address</label><br>
                
                <input type="checkbox" id="disability" name="disability" value="disability"/>
                <label for="disability">Disability</label><br>
                
                <input type="checkbox" id="contact" name="contact" value="contact"/>
                <label for="contact">Contact Number</label><br>

                <input type="checkbox" id="email" name="email" value="email"/>
                <label for="email">Email Address:</label><br>
                
                <input type="checkbox" id="ip_group" name="ip_group" value="ip_group"/>
                <label for="ip_group">IP Group</label><br>
                
                <?php 
                $extra_sql = "SELECT * FROM scholar_notes WHERE notes IS NOT NULL AND notes <> ''";
                $extra_stmt = $conn->prepare($extra_sql);
                $extra_stmt->execute();
                $extra_result = $extra_stmt->get_result();

                if($extra_result->num_rows > 0) {
                    while($row=$extra_result->fetch_assoc()) {
                        // TODO: checkbox for each extra column
                    }
                }

                ?>
                
                <hr>

                <lable for="filename">Filename:</lable>
                <input type="text" name="filename" id="filename" required/><br>

                <button>Export</button>
            </form>
            
        <?php endif; ?>
    </body>
</html>