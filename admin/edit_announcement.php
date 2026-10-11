<?php 
include __DIR__ . "/../includes/conn.php";
include __DIR__ . "/../includes/check_permission.php";
session_start();

if (!isset($_SESSION['user_id'])) {$_SESSION['fail'] = "You are not logged in!";
    header("Location: ../portal.php");
    exit();
}

if ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'staff') {$_SESSION['fail'] = "You are not logged in!";
    header("Location: ../portal.php");
    exit();
}

if (!hasPermission($conn,$_SESSION['user_id'], 'announcements', 'edit')) {
    header("Location: announcements.php");
    exit();
}

$announcement_id = (int) ($_GET['id'] ?? 0);

$announcement_sql = "SELECT * FROM announcements WHERE id=?";
$announcement_stmt =$conn->prepare($announcement_sql);$announcement_stmt->bind_param("i", $announcement_id);$announcement_stmt->execute();
$result =$announcement_stmt->get_result();

$title = "";
$content = "";
$status = 0;
$scholarship_program_id = 0;
$attachment = "";

if ($row =$result->fetch_assoc()) {
    $title =$row['title'];
    $content =$row['content'];
    $status = (int)$row['is_active'];
    $scholarship_program_id = (int)($row['scholarship_program_id'] ?? 0);
    $attachment =$row['attachment'] ?? '';
}

// Get the program name of the currently assigned program ID
$current_sp_name = "";
if ($scholarship_program_id > 0) {$curr_sp_sql = "SELECT name FROM scholarship_program WHERE id = ?";
    $curr_sp_stmt =$conn->prepare($curr_sp_sql);$curr_sp_stmt->bind_param("i", $scholarship_program_id);$curr_sp_stmt->execute();
    $curr_sp_res =$curr_sp_stmt->get_result();
    if ($c_row =$curr_sp_res->fetch_assoc()) {
        $current_sp_name =$c_row['name'];
    }
    $curr_sp_stmt->close();
}

// Fetch unique scholarship programs grouped by name and code
$sp_sql = "SELECT MIN(id) AS id, name, code 
           FROM scholarship_program 
           WHERE is_archived = 0 OR name = ?
           GROUP BY name, code 
           ORDER BY name ASC";
$sp_stmt =$conn->prepare($sp_sql);$sp_stmt->bind_param("s", $current_sp_name);$sp_stmt->execute();
$sp_result =$sp_stmt->get_result();
?>
<!DOCTYPE html>
<html>
<head>
    <title>Edit Announcement - PIT ScholarshipHub</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../assets/css/form.css?v=<?= filemtime('../assets/css/form.css') ?>">
    <link rel="stylesheet" href="../assets/css/modals.css?v=<?= filemtime('../assets/css/modals.css') ?>">
    <link rel="icon" href="../assets/images/PIT%20LOGO.png">
    <script src="../assets/js/disable_button.js"></script>
    <style>
        .drop-zone {
            border: 2px dashed #2a6b37;
            border-radius: 8px;
            padding: 24px;
            text-align: center;
            background-color: rgba(42, 107, 55, 0.03);
            cursor: pointer;
            transition: background-color 0.2s, border-color 0.2s;
            margin-bottom: 8px;
        }
        .drop-zone--over {
            border-color: #3fb524;
            background-color: rgba(63, 181, 36, 0.12);
        }
        .drop-zone__prompt {
            color: #2c3e50;
            font-size: 14px;
            font-weight: 500;
        }
        .drop-zone__browse {
            color: #1a73e8;
            font-weight: 600;
            text-decoration: underline;
        }
        .file-list-container {
            display: flex;
            flex-direction: column;
            gap: 6px;
            margin-bottom: 8px;
        }
        .file-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 6px 12px;
            background: #ffffff;
            border: 1px solid #e0e0e0;
            border-radius: 6px;
            font-size: 13px;
        }
        .file-item-name {
            font-weight: 500;
            color: #333;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        .file-item-remove {
            color: #d9534f;
            cursor: pointer;
            font-weight: bold;
            font-size: 16px;
            margin-left: 10px;
            padding: 0 6px;
            border-radius: 4px;
        }
        .file-item-remove:hover {
            background-color: rgba(217, 83, 79, 0.12);
        }
        .btn-remove-link {
            color: #d9534f;
            cursor: pointer;
            font-weight: bold;
            font-size: 13px;
            text-decoration: underline;
            margin-left: 15px;
            border: none;
            background: none;
            padding: 0;
        }
        .btn-remove-link:hover {
            color: #c9302c;
        }
    </style>
</head>
<body>
    <?php include __DIR__ . "/../includes/modal_alerts.php"; ?>

    <div class="container">
        <?php include __DIR__ . "/../includes/navigation.php"; ?>

        <!-- Page Content -->
        <div class="page-content">
            <!-- Page Header -->
            <div class="page-header">
                <h2>Edit Announcement</h2>
                <div class="page-actions">
                    <a href="announcements.php" class="btn btn-secondary">Back to Announcements</a>
                </div>
            </div>

            <!-- Edit Announcement Form -->
            <form action="../functions/admin/edit_announcement_process.php" method="POST" enctype="multipart/form-data" class="reusable-form">
                <div class="form-section">
                    <div class="form-row">
                        <div class="form-group full-width">
                            <label for="title">Title <span class="required">*</span></label>
                            <input type="text" name="title" id="title" value="<?= htmlspecialchars($title) ?>" required/>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group full-width">
                            <label for="scholarship_program_id">Addressed To <span class="required">*</span></label>
                            <select name="scholarship_program_id" id="scholarship_program_id" required>
                                <option value="0" <?= $scholarship_program_id == 0 ? "selected" : "" ?>>All Scholarship Programs</option>
                                <?php if ($sp_result &&$sp_result->num_rows > 0) : ?>
                                    <?php while($sp =$sp_result->fetch_assoc()) : ?>
                                        <option value="<?= $sp['id'] ?>" <?= ($current_sp_name !== '' && $current_sp_name ===$sp['name']) || $scholarship_program_id ==$sp['id'] ? "selected" : "" ?>>
                                            <?= htmlspecialchars($sp['name']) ?><?= !empty($sp['code']) ? ' (' . htmlspecialchars($sp['code']) . ')' : '' ?>
                                        </option>
                                    <?php endwhile; ?>
                                <?php endif; ?>
                            </select>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group full-width">
                            <label for="content">Content <span class="required">*</span></label>
                            <textarea name="content" id="content" rows="8" required><?= htmlspecialchars($content) ?></textarea>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group full-width">
                            <label>File Attachments</label>
                            <?php if (!empty($attachment)) : 
                                $decoded_attachments = json_decode($attachment, true);
                                $attachments_list = is_array($decoded_attachments) ? $decoded_attachments : [$attachment];
                            ?>
                                <div id="existing-attachments-container" style="margin-bottom: 12px; padding: 10px; background: rgba(0,0,0,0.03); border: 1px solid #ddd; border-radius: 6px;">
                                    <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 8px;">
                                        <span style="font-weight: bold; color: #2a6b37;">Current Attachments:</span>
                                        <button type="button" id="btn-remove-all-existing" class="btn-remove-link">
                                            Remove all attachments
                                        </button>
                                    </div>
                                    <div id="existing-files-list">
                                        <?php foreach ($attachments_list as$att_file) : 
                                            $file_url = preg_match('~^https?://~i',$att_file) ? $att_file :$att_file;
                                            $filename = basename($att_file);
                                        ?>
                                            <div class="existing-file-row" style="display: flex; align-items: center; justify-content: space-between; font-size: 14px; margin-bottom: 6px; padding: 6px 10px; background: #fff; border: 1px solid #eee; border-radius: 4px;">
                                                <a href="<?= htmlspecialchars($file_url) ?>" target="_blank" rel="noopener noreferrer" style="color: #1a73e8; font-weight: 500; text-decoration: underline; display: inline-flex; align-items: center; gap: 4px;">
                                                    📎 <?= htmlspecialchars($filename) ?>
                                                </a>
                                                <button type="button" class="btn-remove-link" onclick="removeExistingFile(this, '<?= htmlspecialchars($att_file, ENT_QUOTES) ?>')">
                                                    Remove
                                                </button>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <!-- Hidden Inputs Container for Removed Items -->
                            <div id="removed-inputs-container"></div>

                            <!-- Drag & Drop Dropzone -->
                            <div class="drop-zone" id="drop-zone">
                                <div class="drop-zone__prompt">
                                    📁 <strong>Drag & drop new files here</strong> or <span class="drop-zone__browse">browse files</span>
                                </div>
                                <input type="file" name="attachments[]" id="attachments" accept=".pdf,.doc,.docx,.xls,.xlsx,.png,.jpg,.jpeg,.webp" multiple style="display: none;"/>
                            </div>
                            <div id="file-list" class="file-list-container"></div>

                            <span class="help-text">Upload new files to add to existing attachments (PDF, Word, Excel, Images - Max 10MB per file).</span>
                        </div>
                    </div>
                </div>

                <input type="hidden" name="announcement_id" value="<?= htmlspecialchars($announcement_id) ?>"/>

                <div class="form-actions">
                    <button type="submit" class="btn btn-success">Update Announcement</button>
                    <a href="announcements.php" class="btn btn-secondary">Cancel</a>
                </div>
            </form>
        </div>

        <!-- Footer -->
        <div class="footer">
            © 2026 PIT ScholarshipHub. All rights reserved.
        </div>
    </div>

    <!-- Drag & Drop and File Removal Handler Script -->
    <script>
    function removeExistingFile(btnElement, filePath) {
        // 1. Remove element from DOM
        const row = btnElement.closest('.existing-file-row');
        if (row) {
            row.remove();
        }

        // 2. Append hidden input for PHP process
        const container = document.getElementById('removed-inputs-container');
        const hiddenInput = document.createElement('input');
        hiddenInput.type = 'hidden';
        hiddenInput.name = 'remove_specific[]';
        hiddenInput.value = filePath;
        container.appendChild(hiddenInput);

        // 3. Hide container if empty
        const remainingRows = document.querySelectorAll('.existing-file-row');
        if (remainingRows.length === 0) {
            const mainBox = document.getElementById('existing-attachments-container');
            if (mainBox) mainBox.style.display = 'none';
        }
    }

    document.addEventListener('DOMContentLoaded', function() {
        // Remove All Attachments Handler
        const removeAllBtn = document.getElementById('btn-remove-all-existing');
        if (removeAllBtn) {
            removeAllBtn.addEventListener('click', function(e) {
                e.preventDefault();

                const container = document.getElementById('removed-inputs-container');
                const hiddenInput = document.createElement('input');
                hiddenInput.type = 'hidden';
                hiddenInput.name = 'remove_attachment';
                hiddenInput.value = '1';
                container.appendChild(hiddenInput);

                const mainBox = document.getElementById('existing-attachments-container');
                if (mainBox) mainBox.style.display = 'none';
            });
        }

        // Dropzone & New Uploads Handler
        const dropZone = document.getElementById('drop-zone');
        const fileInput = document.getElementById('attachments');
        const fileList = document.getElementById('file-list');
        const dt = new DataTransfer();

        if (!dropZone || !fileInput) return;

        dropZone.addEventListener('click', (e) => {
            if (e.target.classList.contains('file-item-remove')) return;
            fileInput.click();
        });

        ['dragover', 'dragenter'].forEach(eventName => {
            dropZone.addEventListener(eventName, (e) => {
                e.preventDefault();
                e.stopPropagation();
                dropZone.classList.add('drop-zone--over');
            });
        });

        ['dragleave', 'dragend'].forEach(eventName => {
            dropZone.addEventListener(eventName, (e) => {
                e.preventDefault();
                e.stopPropagation();
                dropZone.classList.remove('drop-zone--over');
            });
        });

        dropZone.addEventListener('drop', (e) => {
            e.preventDefault();
            e.stopPropagation();
            dropZone.classList.remove('drop-zone--over');

            if (e.dataTransfer.files && e.dataTransfer.files.length > 0) {
                addFiles(e.dataTransfer.files);
            }
        });

        fileInput.addEventListener('change', () => {
            addFiles(fileInput.files);
        });

        function addFiles(files) {
            for (let i = 0; i < files.length; i++) {
                dt.items.add(files[i]);
            }
            fileInput.files = dt.files;
            renderFileList();
        }

        function renderFileList() {
            fileList.innerHTML = '';
            for (let i = 0; i < dt.files.length; i++) {
                const file = dt.files[i];
                const item = document.createElement('div');
                item.className = 'file-item';
                
                const nameSpan = document.createElement('span');
                nameSpan.className = 'file-item-name';
                nameSpan.textContent = `📎 ${file.name} (${(file.size / (1024 * 1024)).toFixed(2)} MB)`;

                const removeBtn = document.createElement('span');
                removeBtn.className = 'file-item-remove';
                removeBtn.innerHTML = '&times;';
                removeBtn.title = 'Remove file';
                removeBtn.addEventListener('click', (e) => {
                    e.stopPropagation();
                    removeFile(i);
                });

                item.appendChild(nameSpan);
                item.appendChild(removeBtn);
                fileList.appendChild(item);
            }
        }

        function removeFile(index) {
            dt.items.remove(index);
            fileInput.files = dt.files;
            renderFileList();
        }
    });
    </script>
</body>
</html>