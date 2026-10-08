<?php 
include __DIR__ . "/../includes/conn.php";
include __DIR__ . "/../includes/check_permission.php";
session_start();

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'staff')) {$_SESSION['fail'] = "You are not logged in!";
    header("Location: ../portal.php");
    exit();
}

if (!hasPermission($conn,$_SESSION['user_id'], 'announcements', 'add')) {
    header("Location: announcements.php");
    exit();
}

$sp_sql = "SELECT id, name FROM scholarship_program WHERE is_archived = 0 ORDER BY name ASC";
$sp_result = $conn->query($sp_sql);
?>
<!DOCTYPE html>
<html>
<head>
    <title>Add Announcement - PIT ScholarshipHub</title>
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
                <h2>Add Announcement</h2>
                <div class="page-actions">
                    <a href="announcements.php" class="btn btn-secondary">Back to Announcements</a>
                </div>
            </div>

            <!-- Add Announcement Form -->
            <form action="../functions/admin/add_announcement_process.php" method="POST" enctype="multipart/form-data" class="reusable-form">
                <div class="form-section">
                    <div class="form-row">
                        <div class="form-group full-width">
                            <label for="title">Title <span class="required">*</span></label>
                            <input type="text" name="title" id="title" placeholder="Enter announcement title" required/>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group full-width">
                            <label for="scholarship_program_id">Addressed To <span class="required">*</span></label>
                            <select name="scholarship_program_id" id="scholarship_program_id" required>
                                <option value="0">All Scholarship Programs</option>
                                <?php if ($sp_result &&$sp_result->num_rows > 0) : ?>
                                    <?php while($sp =$sp_result->fetch_assoc()) : ?>
                                        <option value="<?= $sp['id'] ?>"><?= htmlspecialchars($sp['name']) ?></option>
                                    <?php endwhile; ?>
                                <?php endif; ?>
                            </select>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group full-width">
                            <label for="content">Content <span class="required">*</span></label>
                            <textarea name="content" id="content" rows="8" placeholder="Enter announcement details" required></textarea>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group full-width">
                            <label>File Attachments (Optional)</label>
                            
                            <!-- Drag & Drop Dropzone -->
                            <div class="drop-zone" id="drop-zone">
                                <div class="drop-zone__prompt">
                                    📁 <strong>Drag & drop files here</strong> or <span class="drop-zone__browse">browse files</span>
                                </div>
                                <input type="file" name="attachments[]" id="attachments" accept=".pdf,.doc,.docx,.xls,.xlsx,.png,.jpg,.jpeg,.webp" multiple style="display: none;"/>
                            </div>
                            <div id="file-list" class="file-list-container"></div>

                            <span class="help-text">Supported: PDF, Word, Excel, Images (PNG, JPG, WEBP) - Max 10MB per file.</span>
                        </div>
                    </div>
                </div>

                <div class="form-actions">
                    <button name="add" type="submit" class="btn btn-success">Create Announcement</button>
                    <a href="announcements.php" class="btn btn-secondary">Cancel</a>
                </div>
            </form>
        </div>

        <!-- Footer -->
        <div class="footer">
            © 2026 PIT ScholarshipHub. All rights reserved.
        </div>
    </div>

    <!-- Drag and Drop Handler Script -->
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const dropZone = document.getElementById('drop-zone');
        const fileInput = document.getElementById('attachments');
        const fileList = document.getElementById('file-list');
        const dt = new DataTransfer();

        if (!dropZone || !fileInput) return;

        // Trigger file select on click
        dropZone.addEventListener('click', (e) => {
            if (e.target.classList.contains('file-item-remove')) return;
            fileInput.click();
        });

        // Drag Hover FX
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

        // Drop Event
        dropZone.addEventListener('drop', (e) => {
            e.preventDefault();
            e.stopPropagation();
            dropZone.classList.remove('drop-zone--over');

            if (e.dataTransfer.files && e.dataTransfer.files.length > 0) {
                addFiles(e.dataTransfer.files);
            }
        });

        // File Input Change Event
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