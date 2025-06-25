<?php
// Deployment Note: For load balanced deployment, serve this app behind a load balancer (e.g., Nginx, Apache, or a cloud provider's load balancer). Ensure the /uploads directory is shared (e.g., via NFS, cloud storage, or synced) across all servers for consistent access to uploaded files.
// Set the upload directory
$uploadDir = __DIR__ . '/uploads/';
$baseUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://" . $_SERVER['HTTP_HOST'];
$scriptDir = rtrim(str_replace('\\', '/', dirname($_SERVER['PHP_SELF'])), '/');
$uploadUrl = $baseUrl . ($scriptDir ? '/' . ltrim($scriptDir, '/') : '') . '/uploads/';

// Allowed file types and max size (10MB)
$allowedTypes = [
    'image/jpeg' => 'jpg',
    'image/png'  => 'png',
    'image/gif'  => 'gif',
];
$maxFileSize = 10 * 1024 * 1024; // 10MB

// Ensure the uploads directory exists
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

$message = '';
$shareLinks = [];

// Handle file deletion
if (isset($_POST['delete_file'])) {
    $fileToDelete = basename($_POST['delete_file']);
    $filePath = $uploadDir . $fileToDelete;
    if (is_file($filePath) && strpos(realpath($filePath), realpath($uploadDir)) === 0) {
        if (unlink($filePath)) {
            $message = 'File deleted successfully!';
        } else {
            $message = 'Failed to delete file.';
        }
    } else {
        $message = 'Invalid file.';
    }
}

// Handle bulk file deletion
if (isset($_POST['bulk_delete']) && isset($_POST['selected_files']) && is_array($_POST['selected_files'])) {
    $deletedCount = 0;
    foreach ($_POST['selected_files'] as $fileToDelete) {
        $fileToDelete = basename($fileToDelete);
        $filePath = $uploadDir . $fileToDelete;
        if (is_file($filePath) && strpos(realpath($filePath), realpath($uploadDir)) === 0) {
            if (unlink($filePath)) {
                $deletedCount++;
            }
        }
    }
    if ($deletedCount > 0) {
        $message = $deletedCount . ' file' . ($deletedCount > 1 ? 's' : '') . ' deleted successfully!';
    } else {
        $message = 'No files were deleted.';
    }
}

// Handle multiple image uploads
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['images'])) {
    $uploadResults = [];
    $totalFiles = count($_FILES['images']['name']);
    
    for ($i = 0; $i < $totalFiles; $i++) {
        if ($_FILES['images']['error'][$i] === UPLOAD_ERR_OK) {
            $file = [
                'name' => $_FILES['images']['name'][$i],
                'type' => $_FILES['images']['type'][$i],
                'tmp_name' => $_FILES['images']['tmp_name'][$i],
                'error' => $_FILES['images']['error'][$i],
                'size' => $_FILES['images']['size'][$i]
            ];
            
            $result = ['file' => $file['name'], 'status' => 'error', 'message' => ''];
            
            if ($file['size'] > $maxFileSize) {
                $result['message'] = 'File is too large. Max size is 10MB.';
            } elseif (!array_key_exists(mime_content_type($file['tmp_name']), $allowedTypes)) {
                $result['message'] = 'Invalid file type. Only JPG, PNG, and GIF are allowed.';
            } else {
                $mime = mime_content_type($file['tmp_name']);
                $ext = $allowedTypes[$mime];
                $uniqueName = uniqid('img_', true) . '.' . $ext;
                $destination = $uploadDir . $uniqueName;
                
                $imageInfo = getimagesize($file['tmp_name']);
                if ($imageInfo === false) {
                    $result['message'] = 'Uploaded file is not a valid image.';
                } else {
                    if (move_uploaded_file($file['tmp_name'], $destination)) {
                        $imageUrl = $uploadUrl . $uniqueName;
                        $result['status'] = 'success';
                        $result['url'] = $imageUrl;
                        $result['message'] = 'Uploaded successfully!';
                    } else {
                        $result['message'] = 'Failed to move uploaded file.';
                    }
                }
            }
            $uploadResults[] = $result;
        }
    }
    
    if (!empty($uploadResults)) {
        $successCount = count(array_filter($uploadResults, function($r) { return $r['status'] === 'success'; }));
        $message = $successCount . ' of ' . count($uploadResults) . ' file(s) uploaded successfully!';
        $shareLinks = $uploadResults;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Image Host - Upload & Manage</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600&display=swap" rel="stylesheet">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        :root {
            --primary: #4f8cff;
            --primary-dark: #2563eb;
            --bg: #f8fafc;
            --bg-alt: #e0e7ef;
            --container: #fff;
            --text: #222;
            --input-bg: #f8fafc;
            --input-border: #e0e7ef;
            --error: #d9534f;
            --success: #28a745;
        }
        @media (prefers-color-scheme: dark) {
            :root {
                --primary: #7ab8ff;
                --primary-dark: #2563eb;
                --bg: #181c20;
                --bg-alt: #23272e;
                --container: #23272e;
                --text: #f1f5fa;
                --input-bg: #23272e;
                --input-border: #3a3f47;
            }
        }
        html[data-theme="dark"] {
            --primary: #7ab8ff;
            --primary-dark: #2563eb;
            --bg: #181c20;
            --bg-alt: #23272e;
            --container: #23272e;
            --text: #f1f5fa;
            --input-bg: #23272e;
            --input-border: #3a3f47;
        }
        html[data-theme="light"] {
            --primary: #4f8cff;
            --primary-dark: #2563eb;
            --bg: #f8fafc;
            --bg-alt: #e0e7ef;
            --container: #fff;
            --text: #222;
            --input-bg: #f8fafc;
            --input-border: #e0e7ef;
        }
        body {
            font-family: 'Inter', Arial, sans-serif;
            background: linear-gradient(135deg, var(--bg) 0%, var(--bg-alt) 100%);
            margin: 0;
            min-height: 100vh;
            color: var(--text);
            transition: background 0.3s, color 0.3s;
        }
        .nav {
            display: flex;
            justify-content: center;
            gap: 0;
            margin: 0 auto 24px auto;
            background: var(--container);
            border-radius: 0 0 12px 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.04);
            max-width: 700px;
        }
        .nav-btn {
            flex: 1 1 0;
            padding: 16px 0 12px 0;
            font-size: 1.08em;
            font-weight: 600;
            color: var(--primary-dark);
            background: none;
            border: none;
            border-bottom: 3px solid transparent;
            cursor: pointer;
            transition: color 0.2s, border 0.2s;
        }
        .nav-btn.active {
            color: var(--primary);
            border-bottom: 3px solid var(--primary);
            background: var(--bg-alt);
        }
        .container {
            background: var(--container);
            padding: 32px 24px 24px 24px;
            border-radius: 16px;
            max-width: 420px;
            margin: 24px auto 0 auto;
            box-shadow: 0 4px 24px rgba(0,0,0,0.08);
            display: flex;
            flex-direction: column;
            align-items: center;
        }
        .container.manage {
            max-width: 700px;
        }
        .upload-icon {
            width: 64px;
            height: 64px;
            margin-bottom: 16px;
            color: var(--primary);
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .theme-toggle {
            position: absolute;
            top: 18px;
            right: 18px;
            background: var(--container);
            border: 1.5px solid var(--input-border);
            border-radius: 50%;
            width: 38px;
            height: 38px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: background 0.2s, border 0.2s;
        }
        .theme-toggle svg {
            width: 22px;
            height: 22px;
        }
        h2 {
            margin-top: 0;
            font-weight: 600;
            color: var(--text);
            font-size: 1.6rem;
            margin-bottom: 18px;
        }
        .message {
            margin-bottom: 16px;
            color: var(--error);
            font-weight: 500;
            text-align: center;
        }
        .success {
            color: var(--success);
        }
        form {
            width: 100%;
            display: flex;
            flex-direction: column;
            align-items: center;
            margin-bottom: 18px;
        }
        input[type="file"] {
            display: none;
        }
        .drop-area {
            width: 100%;
            min-height: 120px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            border: 2px dashed var(--primary);
            border-radius: 10px;
            background: var(--input-bg);
            color: var(--primary);
            margin-bottom: 12px;
            transition: background 0.2s, border 0.2s;
            cursor: pointer;
        }
        .drop-area.dragover {
            background: var(--bg-alt);
            border-color: var(--primary-dark);
        }
        .preview {
            margin: 10px 0 0 0;
            width: 100%;
            display: flex;
            flex-direction: column;
            align-items: center;
            max-height: 300px;
            overflow-y: auto;
            padding: 8px;
            background: var(--input-bg);
            border-radius: 8px;
            border: 1px solid var(--input-border);
        }
        .preview-item {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 12px;
            padding: 12px;
            background: var(--container);
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
            width: 100%;
            max-width: 400px;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }
        .preview-item:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        .preview-item img {
            max-width: 60px;
            max-height: 60px;
            border-radius: 6px;
            object-fit: cover;
            border: 2px solid var(--input-border);
        }
        .preview-item-info {
            flex: 1;
            min-width: 0;
        }
        .preview-item-name {
            font-size: 0.95em;
            font-weight: 600;
            color: var(--text);
            margin-bottom: 4px;
            word-break: break-word;
            line-height: 1.3;
        }
        .preview-item-size {
            font-size: 0.85em;
            color: var(--text);
            opacity: 0.7;
            font-weight: 500;
        }
        .preview-item-remove {
            background: #dc3545;
            color: white;
            border: none;
            border-radius: 50%;
            width: 24px;
            height: 24px;
            font-size: 14px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: background 0.2s ease;
        }
        .preview-item-remove:hover {
            background: #c82333;
        }
        .upload-results {
            margin-top: 20px;
            width: 100%;
        }
        .upload-result-item {
            margin-bottom: 16px;
            padding: 16px;
            border-radius: 12px;
            background: var(--container);
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
            border: 1px solid var(--input-border);
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }
        .upload-result-item:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(0,0,0,0.12);
        }
        .upload-result-success {
            border-left: 4px solid var(--success);
        }
        .upload-result-error {
            border-left: 4px solid var(--error);
        }
        .upload-result-header {
            display: flex;
            align-items: flex-start;
            gap: 16px;
            margin-bottom: 12px;
        }
        .upload-result-thumbnail {
            max-width: 80px;
            max-height: 80px;
            border-radius: 8px;
            border: 2px solid var(--input-border);
            object-fit: cover;
            flex-shrink: 0;
        }
        .upload-result-info {
            flex: 1;
            min-width: 0;
        }
        .upload-result-filename {
            font-size: 1.1em;
            font-weight: 700;
            color: var(--text);
            margin-bottom: 4px;
            word-break: break-word;
            line-height: 1.3;
        }
        .upload-result-status {
            font-size: 0.95em;
            font-weight: 600;
            color: var(--success);
        }
        .upload-result-status.error {
            color: var(--error);
        }
        .upload-result-codes {
            margin-top: 12px;
            padding-top: 12px;
            border-top: 1px solid var(--input-border);
        }
        .upload-result-code-group {
            margin-bottom: 8px;
        }
        .upload-result-code-label {
            font-size: 0.9em;
            font-weight: 600;
            color: var(--text);
            margin-bottom: 4px;
            display: block;
        }
        .upload-result-code-input {
            width: 100%;
            padding: 8px 12px;
            border: 1px solid var(--input-border);
            border-radius: 6px;
            background: var(--input-bg);
            color: var(--text);
            font-size: 0.9em;
            font-family: 'Monaco', 'Menlo', 'Ubuntu Mono', monospace;
            transition: border-color 0.2s ease, box-shadow 0.2s ease;
        }
        .upload-result-code-input:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(79,140,255,0.1);
        }
        .upload-result-code-input:hover {
            border-color: var(--primary);
        }
        /* Management section */
        .images {
            width: 100%;
            display: flex;
            flex-wrap: wrap;
            gap: 18px;
            justify-content: center;
        }
        .img-card {
            background: var(--container);
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.06);
            padding: 12px 12px 8px 12px;
            display: flex;
            flex-direction: column;
            align-items: center;
            width: 140px;
            position: relative;
        }
        .img-card input[type="checkbox"] {
            position: absolute;
            top: 8px;
            left: 8px;
            z-index: 10;
            width: 18px;
            height: 18px;
            cursor: pointer;
        }
        .img-card img {
            max-width: 110px;
            max-height: 90px;
            border-radius: 6px;
            margin-bottom: 8px;
        }
        .img-card .filename {
            font-size: 0.92em;
            word-break: break-all;
            margin-bottom: 6px;
            text-align: center;
            max-width: 120px;
        }
        .img-card form {
            margin: 0;
        }
        .img-card button {
            background: #d9534f;
            color: #fff;
            border: none;
            border-radius: 6px;
            padding: 5px 14px;
            font-size: 0.95em;
            cursor: pointer;
        }
        @media (max-width: 600px) {
            .container, .container.manage {
                max-width: 98vw;
                padding: 18px 4vw 18px 4vw;
                margin: 16px auto 0 auto;
            }
            .img-card {
                width: 98vw;
                max-width: 180px;
            }
            .img-card img {
                max-width: 98vw;
                max-height: 120px;
            }
        }
        button[type="submit"] {
            margin-top: 16px;
            background: var(--primary);
            color: #fff;
            border: none;
            border-radius: 8px;
            padding: 12px 32px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            box-shadow: 0 2px 8px rgba(79,140,255,0.08);
            transition: background 0.2s ease;
        }
        button[type="submit"]:hover {
            background: var(--primary-dark);
        }
        .links {
            width: 100%;
            margin-top: 18px;
        }
        .links label {
            font-weight: 600;
            display: block;
            margin-top: 14px;
            margin-bottom: 4px;
            color: var(--text);
        }
        .links input {
            width: 100%;
            margin-bottom: 4px;
            font-size: 0.98em;
            padding: 8px 10px;
            border: 1.5px solid var(--input-border);
            border-radius: 6px;
            background: var(--input-bg);
            color: var(--text);
            transition: border 0.2s;
        }
        .links input:focus {
            border-color: var(--primary);
            outline: none;
        }
    </style>
</head>
<body>
<button class="theme-toggle" id="themeToggle" title="Toggle dark/light mode" aria-label="Toggle dark/light mode">
    <svg id="themeIcon" viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="5" stroke="#4f8cff" stroke-width="2"/><path d="M12 1v2M12 21v2M4.22 4.22l1.42 1.42M18.36 18.36l1.42 1.42M1 12h2M21 12h2M4.22 19.78l1.42-1.42M18.36 5.64l1.42-1.42" stroke="#4f8cff" stroke-width="2"/></svg>
</button>
<div class="nav">
    <button class="nav-btn active" id="tab-upload" onclick="showTab('upload')">Upload Image</button>
    <button class="nav-btn" id="tab-manage" onclick="showTab('manage')">Manage Images</button>
</div>
<div id="section-upload" class="container">
    <div class="upload-icon">
        <!-- SVG upload icon -->
        <svg width="100%" height="100%" viewBox="0 0 64 64" fill="none" xmlns="http://www.w3.org/2000/svg">
            <rect x="8" y="40" width="48" height="12" rx="4" fill="#e6f0ff"/>
            <path d="M32 44V16" stroke="#4f8cff" stroke-width="4" stroke-linecap="round"/>
            <path d="M24 24L32 16L40 24" stroke="#4f8cff" stroke-width="4" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
    </div>
    <h2>Upload an Image</h2>
    <?php if ($message && !$shareLinks): ?>
        <div class="message<?php echo ($message === 'Image uploaded successfully!') ? ' success' : ''; ?>"><?php echo htmlspecialchars($message); ?></div>
    <?php endif; ?>
    <form method="post" enctype="multipart/form-data" id="uploadForm" autocomplete="off">
        <div class="drop-area" id="dropArea">
            <svg width="32" height="32" fill="none" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg"><path d="M10 14V6M10 6L6 10M10 6l4 4" stroke="#4f8cff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/><rect x="3" y="14" width="14" height="3" rx="1.5" fill="#e6f0ff"/></svg>
            <span id="dropText">Drag & drop multiple images or click to select…</span>
            <input type="file" name="images[]" id="fileInput" accept="image/jpeg,image/png,image/gif" multiple required style="display:none;">
        </div>
        <div class="preview" id="preview"></div>
        <button type="submit" id="uploadBtn">Upload All</button>
        <div class="message" id="clientError" style="display:none;"></div>
    </form>
    <?php if (isset($shareLinks) && is_array($shareLinks)): ?>
        <div class="upload-results">
            <h3 style="margin-bottom: 12px; color: var(--text);">Upload Results:</h3>
            <?php foreach ($shareLinks as $result): ?>
                <div class="upload-result-item<?php echo $result['status'] === 'success' ? ' upload-result-success' : ' upload-result-error'; ?>">
                    <div class="upload-result-header">
                        <?php if ($result['status'] === 'success'): ?>
                            <img src="<?php echo htmlspecialchars($result['url']); ?>" alt="" class="upload-result-thumbnail">
                        <?php endif; ?>
                        <div class="upload-result-info">
                            <div class="upload-result-filename"><?php echo htmlspecialchars($result['file']); ?></div>
                            <div class="upload-result-status<?php echo $result['status'] === 'success' ? ' success' : ' error'; ?>"><?php echo htmlspecialchars($result['message']); ?></div>
                        </div>
                    </div>
                    <?php if ($result['status'] === 'success'): ?>
                        <div class="upload-result-codes">
                            <div class="upload-result-code-group">
                                <label class="upload-result-code-label">Direct Link:</label>
                                <input type="text" readonly value="<?php echo htmlspecialchars($result['url']); ?>" class="upload-result-code-input" onclick="this.select();">
                            </div>
                            <div class="upload-result-code-group">
                                <label class="upload-result-code-label">HTML Code:</label>
                                <input type="text" readonly value="&lt;img src=&quot;<?php echo htmlspecialchars($result['url']); ?>&quot; alt=&quot;Image&quot; /&gt;" class="upload-result-code-input" onclick="this.select();">
                            </div>
                            <div class="upload-result-code-group">
                                <label class="upload-result-code-label">BBCode:</label>
                                <input type="text" readonly value="[img]<?php echo htmlspecialchars($result['url']); ?>[/img]" class="upload-result-code-input" onclick="this.select();">
                            </div>
                            <div class="upload-result-code-group">
                                <label class="upload-result-code-label">Markdown:</label>
                                <input type="text" readonly value="![alt text](<?php echo htmlspecialchars($result['url']); ?>)" class="upload-result-code-input" onclick="this.select();">
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
<div id="section-manage" class="container manage" style="display:none;">
    <h2>Manage Uploaded Images</h2>
    <?php if ($message && !$shareLinks): ?>
        <div class="message<?php echo ($message === 'File deleted successfully!' || strpos($message, 'deleted successfully!') !== false) ? ' success' : ''; ?>"><?php echo htmlspecialchars($message); ?></div>
    <?php endif; ?>
    <div style="margin-bottom: 16px; display: flex; gap: 8px; align-items: center; justify-content: center;">
        <label style="display: flex; align-items: center; gap: 6px; font-weight: 600; color: var(--text); cursor: pointer;">
            <input type="checkbox" id="selectAllCheckbox" style="width: 18px; height: 18px; cursor: pointer;">
            Select All
        </label>
        <button id="deleteSelectedBtn" onclick="deleteSelected()" style="background: #d9534f; color: #fff; border: none; border-radius: 6px; padding: 8px 16px; font-size: 0.95em; cursor: pointer; display: none;">Delete Selected</button>
    </div>
    <form id="bulkDeleteForm" method="post" style="display: none;">
        <input type="hidden" name="bulk_delete" value="1">
        <div id="selectedFilesInputs"></div>
    </form>
    <div class="images">
    <?php
    $files = array_diff(scandir($uploadDir), array('.', '..'));
    $hasImages = false;
    foreach ($files as $file):
        $filePath = $uploadDir . $file;
        $fileUrl = $uploadUrl . $file;
        if (is_file($filePath) && ($imgInfo = @getimagesize($filePath))):
            $hasImages = true;
            $fileSize = filesize($filePath);
    ?>
        <div class="img-card" tabindex="0">
            <input type="checkbox" class="file-checkbox" value="<?php echo htmlspecialchars($file); ?>" style="position: absolute; top: 8px; left: 8px; z-index: 10;">
            <img src="<?php echo htmlspecialchars($fileUrl); ?>" alt="" onclick="showImageDetails('<?php echo htmlspecialchars(addslashes($file)); ?>', '<?php echo htmlspecialchars(addslashes($fileUrl)); ?>', <?php echo $imgInfo[0]; ?>, <?php echo $imgInfo[1]; ?>, <?php echo $fileSize; ?>)" style="cursor: pointer;">
            <div class="filename"><?php echo htmlspecialchars($file); ?></div>
            <form method="post">
                <input type="hidden" name="delete_file" value="<?php echo htmlspecialchars($file); ?>">
                <button type="submit">Delete</button>
            </form>
        </div>
    <?php endif; endforeach; ?>
    <?php if (!$hasImages): ?>
        <div style="color:var(--text);opacity:0.7;font-size:1em;">No images uploaded yet.</div>
    <?php endif; ?>
    </div>
    <!-- Modal for image details -->
    <div id="imgDetailsModal" style="display:none;position:fixed;z-index:1000;top:0;left:0;width:100vw;height:100vh;background:rgba(0,0,0,0.45);align-items:center;justify-content:center;">
        <div style="background:var(--container);padding:28px 18px 18px 18px;border-radius:14px;max-width:98vw;max-height:92vh;overflow:auto;box-shadow:0 8px 32px rgba(0,0,0,0.18);position:relative;display:flex;flex-direction:column;align-items:center;">
            <button onclick="closeImgDetails()" style="position:absolute;top:10px;right:14px;background:none;border:none;font-size:1.6em;color:var(--text);cursor:pointer;">&times;</button>
            <img id="modalImg" src="" alt="" style="max-width:320px;max-height:320px;border-radius:10px;box-shadow:0 2px 8px rgba(0,0,0,0.08);margin-bottom:12px;">
            <div style="width:100%;max-width:320px;">
                <div style="margin-bottom:6px;"><b>Filename:</b> <span id="modalFilename"></span></div>
                <div style="margin-bottom:6px;"><b>Size:</b> <span id="modalSize"></span></div>
                <div style="margin-bottom:6px;"><b>Dimensions:</b> <span id="modalDims"></span></div>
                <div style="margin-bottom:6px;"><b>Direct Link:</b> <input id="modalDirect" type="text" readonly style="width:100%;" onclick="this.select();"></div>
                <div style="margin-bottom:6px;"><b>HTML:</b> <input id="modalHtml" type="text" readonly style="width:100%;" onclick="this.select();"></div>
                <div style="margin-bottom:6px;"><b>BBCode:</b> <input id="modalBB" type="text" readonly style="width:100%;" onclick="this.select();"></div>
                <div style="margin-bottom:6px;"><b>Markdown:</b> <input id="modalMd" type="text" readonly style="width:100%;" onclick="this.select();"></div>
            </div>
        </div>
    </div>
</div>
<script>
// Theme management
class ThemeManager {
    constructor() {
        this.themeToggle = document.getElementById('themeToggle');
        this.themeIcon = document.getElementById('themeIcon');
        this.init();
    }

    init() {
        this.themeToggle?.addEventListener('click', () => this.toggleTheme());
        this.setTheme(this.getPreferredTheme());
    }

    setTheme(mode) {
        document.documentElement.setAttribute('data-theme', mode);
        this.updateIcon(mode);
        localStorage.setItem('theme', mode);
    }

    getPreferredTheme() {
        return localStorage.getItem('theme') || 
               (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
    }

    toggleTheme() {
        const currentTheme = document.documentElement.getAttribute('data-theme');
        this.setTheme(currentTheme === 'dark' ? 'light' : 'dark');
    }

    updateIcon(mode) {
        if (!this.themeIcon) return;
        
        this.themeIcon.innerHTML = mode === 'dark' 
            ? '<path d="M21.64 13A9 9 0 1111 2.36 7 7 0 0021.64 13z" stroke="#7ab8ff" stroke-width="2" fill="none"/>'
            : '<circle cx="12" cy="12" r="5" stroke="#4f8cff" stroke-width="2"/><path d="M12 1v2M12 21v2M4.22 4.22l1.42 1.42M18.36 18.36l1.42 1.42M1 12h2M21 12h2M4.22 19.78l1.42-1.42M18.36 5.64l1.42-1.42" stroke="#4f8cff" stroke-width="2"/>';
    }
}

// Tab navigation
class TabManager {
    constructor() {
        this.init();
    }

    init() {
        // Auto-show correct tab based on action
        const shouldShowManage = <?php echo isset($_POST['delete_file']) || isset($_POST['bulk_delete']) ? 'true' : 'false'; ?>;
        this.showTab(shouldShowManage ? 'manage' : 'upload');
    }

    showTab(tab) {
        const sections = {
            upload: document.getElementById('section-upload'),
            manage: document.getElementById('section-manage')
        };
        const buttons = {
            upload: document.getElementById('tab-upload'),
            manage: document.getElementById('tab-manage')
        };

        Object.keys(sections).forEach(key => {
            if (sections[key]) sections[key].style.display = key === tab ? '' : 'none';
            if (buttons[key]) buttons[key].classList.toggle('active', key === tab);
        });
    }
}

// File upload management
class FileUploadManager {
    constructor() {
        this.dropArea = document.getElementById('dropArea');
        this.fileInput = document.getElementById('fileInput');
        this.preview = document.getElementById('preview');
        this.clientError = document.getElementById('clientError');
        this.uploadBtn = document.getElementById('uploadBtn');
        this.uploadForm = document.getElementById('uploadForm');
        
        this.allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
        this.maxFileSize = 10 * 1024 * 1024; // 10MB
        
        this.init();
    }

    init() {
        if (!this.dropArea || !this.fileInput) return;

        // Drag and drop events
        ['dragenter', 'dragover'].forEach(eventName => {
            this.dropArea.addEventListener(eventName, e => this.handleDragEvent(e, true), false);
        });
        
        ['dragleave', 'drop'].forEach(eventName => {
            this.dropArea.addEventListener(eventName, e => this.handleDragEvent(e, false), false);
        });

        // Click and file selection
        this.dropArea.addEventListener('click', () => this.fileInput.click());
        this.dropArea.addEventListener('drop', e => this.handleDrop(e));
        this.fileInput.addEventListener('change', e => this.handleFileSelect(e));
        
        // Form submission
        this.uploadForm?.addEventListener('submit', e => this.handleSubmit(e));
        
        // Error handling
        window.addEventListener('error', () => this.showError('A client-side error occurred. Please try again.'));
        window.addEventListener('offline', () => this.showError('You are offline. Please check your connection.'));
    }

    handleDragEvent(e, isDragover) {
        e.preventDefault();
        e.stopPropagation();
        this.dropArea.classList.toggle('dragover', isDragover);
    }

    handleDrop(e) {
        const files = Array.from(e.dataTransfer.files);
        if (files.length > 0) {
            this.fileInput.files = e.dataTransfer.files;
            this.handleFiles(files);
        }
    }

    handleFileSelect(e) {
        const files = Array.from(e.target.files);
        if (files.length > 0) {
            this.handleFiles(files);
        }
    }

    handleFiles(files) {
        this.clearPreview();
        this.hideError();
        
        files.forEach(file => {
            if (!this.validateFile(file)) return;
            this.createPreviewItem(file);
        });
    }

    validateFile(file) {
        if (!this.allowedTypes.includes(file.type)) {
            this.showError(`Invalid file type: ${file.name}. Only JPG, PNG, and GIF are allowed.`);
            return false;
        }
        
        if (file.size > this.maxFileSize) {
            this.showError(`File too large: ${file.name}. Max size is 10MB.`);
            return false;
        }
        
        return true;
    }

    createPreviewItem(file) {
        const reader = new FileReader();
        reader.onload = (e) => {
            const previewItem = document.createElement('div');
            previewItem.className = 'preview-item';
            
            const img = document.createElement('img');
            img.src = e.target.result;
            img.alt = 'Preview';
            img.className = 'preview-item-thumbnail';
            
            const info = document.createElement('div');
            info.className = 'preview-item-info';
            info.innerHTML = `
                <div class="preview-item-name">${this.escapeHtml(file.name)}</div>
                <div class="preview-item-size">${this.formatBytes(file.size)}</div>
            `;
            
            const removeBtn = document.createElement('button');
            removeBtn.className = 'preview-item-remove';
            removeBtn.textContent = '×';
            removeBtn.onclick = () => previewItem.remove();
            
            previewItem.append(img, info, removeBtn);
            this.preview.appendChild(previewItem);
        };
        reader.readAsDataURL(file);
    }

    handleSubmit(e) {
        if (!this.fileInput.files || this.fileInput.files.length === 0) {
            this.showError('Please select at least one image to upload.');
            e.preventDefault();
            return;
        }
        
        this.uploadBtn.disabled = true;
        this.uploadBtn.textContent = 'Uploading…';
    }

    clearPreview() {
        if (this.preview) this.preview.innerHTML = '';
    }

    showError(msg) {
        if (this.clientError) {
            this.clientError.textContent = msg;
            this.clientError.style.display = 'block';
        }
    }

    hideError() {
        if (this.clientError) this.clientError.style.display = 'none';
    }

    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    formatBytes(bytes) {
        if (bytes < 1024) return bytes + ' B';
        const k = 1024;
        const sizes = ['B', 'KB', 'MB', 'GB', 'TB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
    }
}

// File management
class FileManager {
    constructor() {
        this.selectAllCheckbox = document.getElementById('selectAllCheckbox');
        this.deleteSelectedBtn = document.getElementById('deleteSelectedBtn');
        this.bulkDeleteForm = document.getElementById('bulkDeleteForm');
        this.selectedFilesInputs = document.getElementById('selectedFilesInputs');
        
        this.init();
    }

    init() {
        // Individual checkbox events
        document.querySelectorAll('.file-checkbox').forEach(cb => {
            cb.addEventListener('change', () => this.updateUI());
        });
        
        // Select all functionality
        this.selectAllCheckbox?.addEventListener('change', () => this.toggleSelectAll());
    }

    toggleSelectAll() {
        const checkboxes = document.querySelectorAll('.file-checkbox');
        const isChecked = this.selectAllCheckbox.checked;
        
        checkboxes.forEach(cb => cb.checked = isChecked);
        this.updateUI();
    }

    updateUI() {
        const checkboxes = document.querySelectorAll('.file-checkbox:checked');
        this.deleteSelectedBtn.style.display = checkboxes.length > 0 ? 'inline-block' : 'none';
        this.updateSelectAllState();
    }

    updateSelectAllState() {
        const checkboxes = document.querySelectorAll('.file-checkbox');
        if (checkboxes.length === 0) return;

        const allChecked = Array.from(checkboxes).every(cb => cb.checked);
        const someChecked = Array.from(checkboxes).some(cb => cb.checked);
        
        this.selectAllCheckbox.checked = allChecked;
        this.selectAllCheckbox.indeterminate = someChecked && !allChecked;
    }

    deleteSelected() {
        const checkboxes = document.querySelectorAll('.file-checkbox:checked');
        if (checkboxes.length === 0) return;
        
        const count = checkboxes.length;
        const message = count === 1 ? 'Are you sure you want to delete this file?' 
                                  : `Are you sure you want to delete ${count} selected files?`;
        
        if (confirm(message)) {
            this.prepareBulkDeleteForm(checkboxes);
            this.bulkDeleteForm.submit();
        }
    }

    prepareBulkDeleteForm(checkboxes) {
        this.selectedFilesInputs.innerHTML = '';
        
        checkboxes.forEach(cb => {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'selected_files[]';
            input.value = cb.value;
            this.selectedFilesInputs.appendChild(input);
        });
    }
}

// Image details modal
class ImageDetailsModal {
    constructor() {
        this.modal = document.getElementById('imgDetailsModal');
        this.init();
    }

    init() {
        // Close modal when clicking outside
        this.modal?.addEventListener('click', (e) => {
            if (e.target === this.modal) this.close();
        });
        
        // Close on escape key
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') this.close();
        });
    }

    show(filename, url, width, height, size) {
        if (!this.modal) return;
        
        document.getElementById('modalImg').src = url;
        document.getElementById('modalFilename').textContent = filename;
        document.getElementById('modalSize').textContent = this.formatBytes(size);
        document.getElementById('modalDims').textContent = `${width} × ${height}`;
        document.getElementById('modalDirect').value = url;
        document.getElementById('modalHtml').value = `<img src="${url}" alt="Image" />`;
        document.getElementById('modalBB').value = `[img]${url}[/img]`;
        document.getElementById('modalMd').value = `![alt text](${url})`;
        
        this.modal.style.display = 'flex';
    }

    close() {
        if (this.modal) this.modal.style.display = 'none';
    }

    formatBytes(bytes) {
        if (bytes < 1024) return bytes + ' B';
        const k = 1024;
        const sizes = ['B', 'KB', 'MB', 'GB', 'TB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
    }
}

// Global functions for backward compatibility
function showTab(tab) {
    window.tabManager?.showTab(tab);
}

function showImageDetails(filename, url, width, height, size) {
    window.imageModal?.show(filename, url, width, height, size);
}

function closeImgDetails() {
    window.imageModal?.close();
}

function deleteSelected() {
    window.fileManager?.deleteSelected();
}

// Initialize all managers when DOM is ready
document.addEventListener('DOMContentLoaded', function() {
    window.themeManager = new ThemeManager();
    window.tabManager = new TabManager();
    window.uploadManager = new FileUploadManager();
    window.fileManager = new FileManager();
    window.imageModal = new ImageDetailsModal();
});
</script>
</body>
</html> 