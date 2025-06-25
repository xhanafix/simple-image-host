<?php
// Always start session at the very top, before any output
if (session_status() === PHP_SESSION_NONE) session_start();

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

// Handle multiple image uploads (POST/Redirect/GET)
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
        $_SESSION['uploadResults'] = $uploadResults;
        $_SESSION['uploadMessage'] = $message;
        header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?') . '?upload=success');
        exit;
    }
}

// Show upload results after redirect (GET)
if (isset($_GET['upload']) && $_GET['upload'] === 'success' && isset($_SESSION['uploadResults'])) {
    $shareLinks = $_SESSION['uploadResults'];
    $message = $_SESSION['uploadMessage'] ?? '';
    unset($_SESSION['uploadResults'], $_SESSION['uploadMessage']);
}

// Instead, get all files sorted by newest first
$allFiles = array_values(array_filter(array_diff(scandir($uploadDir), array('.', '..')), function($file) use ($uploadDir) {
    $filePath = $uploadDir . $file;
    return is_file($filePath) && @getimagesize($filePath);
}));
usort($allFiles, function($a, $b) use ($uploadDir) {
    return filemtime($uploadDir . $b) <=> filemtime($uploadDir . $a);
});
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
        .upload-progress {
            width: 100%;
            height: 4px;
            background: var(--input-border);
            border-radius: 2px;
            overflow: hidden;
            margin: 12px 0;
            display: none;
        }
        .upload-progress-bar {
            height: 100%;
            background: var(--primary);
            width: 0%;
            transition: width 0.3s ease;
            border-radius: 2px;
        }
        .upload-status {
            text-align: center;
            color: var(--primary);
            font-size: 0.9em;
            font-weight: 600;
            margin: 8px 0;
            display: none;
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
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 24px;
            justify-items: center;
            align-items: start;
            margin-bottom: 24px;
            padding: 24px 0 0 0;
            background: linear-gradient(135deg, var(--bg-alt) 0%, var(--bg) 100%);
            border-radius: 18px;
            box-shadow: 0 2px 16px rgba(0,0,0,0.04);
            min-height: 200px;
            /* For smooth scroll */
            scroll-behavior: smooth;
        }
        .images.loading::after {
            content: 'Loading...';
            display: block;
            text-align: center;
            color: var(--primary);
            font-size: 1.1em;
            padding: 18px 0;
            grid-column: 1/-1;
        }
        .img-card {
            background: var(--container);
            border-radius: 16px;
            box-shadow: 0 4px 16px rgba(0,0,0,0.10);
            padding: 0 0 16px 0;
            display: flex;
            flex-direction: column;
            align-items: center;
            width: 100%;
            min-width: 0;
            max-width: 220px;
            position: relative;
            transition: box-shadow 0.2s, transform 0.2s, border 0.2s;
            cursor: pointer;
            border: 1.5px solid var(--input-border);
            overflow: hidden;
            color: var(--text);
        }
        .img-card:hover {
            box-shadow: 0 8px 32px rgba(79,140,255,0.18);
            transform: translateY(-4px) scale(1.03);
            border: 1.5px solid var(--primary);
        }
        .img-card img {
            width: 100%;
            aspect-ratio: 1/1;
            object-fit: cover;
            border-radius: 0;
            margin-bottom: 0;
            box-shadow: none;
            background: #f3f6fa;
            opacity: 0;
            transition: opacity 0.5s;
            display: block;
        }
        .img-card img.loaded {
            opacity: 1;
        }
        .img-card .filename {
            font-size: 1em;
            word-break: break-all;
            margin: 0;
            text-align: center;
            max-width: 90%;
            color: var(--primary-dark);
            background: rgba(255,255,255,0.9);
            border-radius: 6px;
            padding: 6px 10px;
            font-weight: 600;
            box-shadow: 0 1px 2px rgba(0,0,0,0.03);
            margin-top: 10px;
            margin-bottom: 8px;
            align-self: center;
        }
        .img-card .upload-date {
            font-size: 0.93em;
            color: var(--primary-dark);
            opacity: 0.85;
            margin-bottom: 6px;
            text-align: center;
        }
        .img-card form {
            margin: 0;
            width: 100%;
            display: flex;
            justify-content: center;
        }
        .img-card button {
            background: #d9534f;
            color: #fff;
            border: none;
            border-radius: 6px;
            padding: 6px 18px;
            font-size: 1em;
            cursor: pointer;
            margin-top: 0;
            margin-bottom: 0;
            transition: background 0.2s;
            font-weight: 600;
        }
        .img-card button:hover {
            background: #c82333;
        }
        @media (max-width: 900px) {
            .images {
                grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
                gap: 12px;
                padding: 12px 0 0 0;
            }
            .img-card {
                max-width: 100%;
                min-width: 0;
            }
        }
        @media (max-width: 600px) {
            .container, .container.manage {
                max-width: 100vw;
                padding: 10px 2vw 10px 2vw;
                margin: 10px auto 0 auto;
            }
            .images {
                grid-template-columns: repeat(auto-fit, minmax(110px, 1fr));
                gap: 8px;
                padding: 8px 0 0 0;
            }
            .img-card {
                max-width: 100%;
                min-width: 0;
            }
        }
        /* Pagination controls */
        .gallery-pagination {
            margin-top: 0;
            margin-bottom: 24px;
            display: flex;
            justify-content: center;
            gap: 16px;
            align-items: center;
        }
        .gallery-pagination a, .gallery-pagination span {
            padding: 8px 18px;
            border-radius: 8px;
            background: var(--input-bg);
            color: var(--primary);
            text-decoration: none;
            font-weight: 700;
            font-size: 1.05em;
            transition: background 0.2s, color 0.2s;
        }
        .gallery-pagination a[aria-disabled="true"] {
            pointer-events: none;
            opacity: 0.5;
        }
        .gallery-pagination span {
            color: var(--text);
            background: transparent;
            font-weight: 600;
            font-size: 1em;
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
    <svg id="themeIcon" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
        <path d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
    </svg>
</button>
<div class="nav">
    <button class="nav-btn active" id="tab-upload" onclick="showTab('upload')">Upload Image</button>
    <button class="nav-btn" id="tab-manage" onclick="showTab('manage')">Manage Images</button>
</div>
<div id="section-upload" class="container">
    <div class="upload-icon">
        <svg width="48" height="48" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
            <path d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
    </div>
    <h2>Upload Images</h2>
    <?php if ($message && !$shareLinks): ?>
        <div class="message<?php echo ($message === 'Image uploaded successfully!') ? ' success' : ''; ?>"><?php echo htmlspecialchars($message); ?></div>
    <?php endif; ?>
    <form method="post" enctype="multipart/form-data" id="uploadForm" autocomplete="off">
        <div class="drop-area" id="dropArea">
            <svg width="28" height="28" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
            <span id="dropText">Drag & drop images or click to select</span>
            <input type="file" name="images[]" id="fileInput" accept="image/jpeg,image/png,image/gif" multiple required style="display:none;">
        </div>
        <div class="upload-progress" id="uploadProgress">
            <div class="upload-progress-bar" id="uploadProgressBar"></div>
        </div>
        <div class="upload-status" id="uploadStatus"></div>
        <div class="preview" id="preview"></div>
        <button type="submit" id="uploadBtn">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" style="margin-right: 8px;">
                <path d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
            Upload All
        </button>
        <div class="message" id="clientError" style="display:none;"></div>
    </form>
    <?php if (!empty($shareLinks) && is_array($shareLinks)): ?>
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
    <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 16px;">
        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
            <path d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
        <h2>Manage Images</h2>
    </div>
    <?php if ($message && !$shareLinks): ?>
        <div class="message<?php echo ($message === 'File deleted successfully!' || strpos($message, 'deleted successfully!') !== false) ? ' success' : ''; ?>"><?php echo htmlspecialchars($message); ?></div>
    <?php endif; ?>
    <div style="margin-bottom: 16px; display: flex; gap: 8px; align-items: center; justify-content: center;">
        <label style="display: flex; align-items: center; gap: 6px; font-weight: 600; color: var(--text); cursor: pointer;">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
            <input type="checkbox" id="selectAllCheckbox" style="width: 18px; height: 18px; cursor: pointer;">
            Select All
        </label>
        <button id="deleteSelectedBtn" onclick="deleteSelected()" style="background: #d9534f; color: #fff; border: none; border-radius: 6px; padding: 8px 16px; font-size: 0.95em; cursor: pointer; display: none; display: flex; align-items: center; gap: 6px;">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
            Delete Selected
        </button>
    </div>
    <form id="bulkDeleteForm" method="post" style="display: none;">
        <input type="hidden" name="bulk_delete" value="1">
        <div id="selectedFilesInputs"></div>
    </form>
    <div class="images" id="imagesGallery">
        <!-- Images will be loaded here by JS -->
    </div>
    <!-- Modal for image details -->
    <div id="imgDetailsModal" style="display:none;position:fixed;z-index:1000;top:0;left:0;width:100vw;height:100vh;background:rgba(0,0,0,0.45);align-items:center;justify-content:center;">
        <div style="background:var(--container);padding:28px 18px 18px 18px;border-radius:14px;max-width:98vw;max-height:92vh;overflow:auto;box-shadow:0 8px 32px rgba(0,0,0,0.18);position:relative;display:flex;flex-direction:column;align-items:center;">
            <button onclick="closeImgDetails()" style="position:absolute;top:10px;right:14px;background:none;border:none;color:var(--text);cursor:pointer;display:flex;align-items:center;justify-content:center;width:32px;height:32px;border-radius:50%;transition:background 0.2s;">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M6 18L18 6M6 6l12 12" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
            </button>
            <div style="display: flex; align-items: center; gap: 16px; margin-bottom: 8px;">
                <button id="modalPrevBtn" style="background: none; border: none; color: var(--primary); cursor: pointer; padding: 8px; border-radius: 50%; transition: background 0.2s; display: flex; align-items: center; justify-content: center;">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M15 19l-7-7 7-7" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                </button>
                <img id="modalImg" src="" alt="" style="max-width:320px;max-height:320px;border-radius:10px;box-shadow:0 2px 8px rgba(0,0,0,0.08);">
                <button id="modalNextBtn" style="background: none; border: none; color: var(--primary); cursor: pointer; padding: 8px; border-radius: 50%; transition: background 0.2s; display: flex; align-items: center; justify-content: center;">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M9 5l7 7-7 7" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                </button>
            </div>
            <div style="width:100%;max-width:320px;">
                <div style="margin-bottom:6px;"><b>Filename:</b> <span id="modalFilename"></span></div>
                <div style="margin-bottom:6px;"><b>Size:</b> <span id="modalSize"></span></div>
                <div style="margin-bottom:6px;"><b>Dimensions:</b> <span id="modalDims"></span></div>
                <div style="margin-bottom:6px;"><b>Direct Link:</b> <input id="modalDirect" type="text" readonly style="width:100%;" onclick="this.select();"></div>
                <div style="margin-bottom:6px;"><b>HTML:</b> <input id="modalHtml" type="text" readonly style="width:100%;" onclick="this.select();"></div>
                <div style="margin-bottom:6px;"><b>BBCode:</b> <input id="modalBB" type="text" readonly style="width:100%;" onclick="this.select();"></div>
                <div style="margin-bottom:6px;"><b>Markdown:</b> <input id="modalMd" type="text" readonly style="width:100%;" onclick="this.select();"></div>
                <div style="margin-bottom:6px;"><b>Uploaded:</b> <span id="modalUploadTime"></span></div>
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
            ? '<path d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>'
            : '<path d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>';
    }
}

// Tab navigation
class TabManager {
    constructor() {
        this.init();
    }

    init() {
        // Auto-show correct tab based on action or page param
        const urlParams = new URLSearchParams(window.location.search);
        const hasPageParam = urlParams.has('page');
        const shouldShowManage = hasPageParam || <?php echo isset($_POST['delete_file']) || isset($_POST['bulk_delete']) ? 'true' : 'false'; ?>;
        this.showTab(shouldShowManage ? 'manage' : 'upload');
        // Listen for tab clicks
        document.getElementById('tab-upload').addEventListener('click', () => this.showTab('upload'));
        document.getElementById('tab-manage').addEventListener('click', () => this.showTab('manage'));
        // If hash is #section-manage, show manage tab
        if (window.location.hash === '#section-manage') {
            this.showTab('manage');
        }
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
        // Update hash for navigation
        if (tab === 'manage') {
            window.location.hash = '#section-manage';
        } else {
            window.location.hash = '';
        }
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
        this.uploadProgress = document.getElementById('uploadProgress');
        this.uploadProgressBar = document.getElementById('uploadProgressBar');
        this.uploadStatus = document.getElementById('uploadStatus');
        
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
        
        // More specific error handling - remove overly broad error handler
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
            removeBtn.innerHTML = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M6 18L18 6M6 6l12 12" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>';
            removeBtn.onclick = () => previewItem.remove();
            
            previewItem.append(img, info, removeBtn);
            this.preview.appendChild(previewItem);
        };
        reader.readAsDataURL(file);
    }

    showProgress(show = true) {
        if (this.uploadProgress) {
            this.uploadProgress.style.display = show ? 'block' : 'none';
        }
        if (this.uploadStatus) {
            this.uploadStatus.style.display = show ? 'block' : 'none';
        }
    }

    updateProgress(percent, status = '') {
        if (this.uploadProgressBar) {
            this.uploadProgressBar.style.width = percent + '%';
        }
        if (this.uploadStatus && status) {
            this.uploadStatus.textContent = status;
        }
    }

    handleSubmit(e) {
        if (!this.fileInput.files || this.fileInput.files.length === 0) {
            this.showError('Please select at least one image to upload.');
            e.preventDefault();
            return;
        }
        
        this.uploadBtn.disabled = true;
        this.uploadBtn.textContent = 'Uploading...';
        this.showProgress(true);
        this.updateProgress(0, 'Preparing upload...');
        
        // Simulate upload progress
        let progress = 0;
        const progressInterval = setInterval(() => {
            progress += Math.random() * 15;
            if (progress > 90) progress = 90;
            this.updateProgress(progress, `Uploading... ${Math.round(progress)}%`);
        }, 200);
        
        // Reset form and button after successful upload
        setTimeout(() => {
            clearInterval(progressInterval);
            this.updateProgress(100, 'Upload complete!');
            setTimeout(() => {
                this.resetForm();
            }, 1000);
        }, 2000);
    }

    resetForm() {
        if (this.uploadBtn) {
            this.uploadBtn.disabled = false;
            this.uploadBtn.textContent = 'Upload All';
        }
        if (this.uploadForm) {
            this.uploadForm.reset();
        }
        this.clearPreview();
        this.hideError();
        this.showProgress(false);
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

    show(filename, url, width, height, size, uploadTime) {
        if (!this.modal) return;
        
        document.getElementById('modalImg').src = url;
        document.getElementById('modalFilename').textContent = filename;
        document.getElementById('modalSize').textContent = this.formatBytes(size);
        document.getElementById('modalDims').textContent = `${width} × ${height}`;
        document.getElementById('modalDirect').value = url;
        document.getElementById('modalHtml').value = `<img src="${url}" alt="Image" />`;
        document.getElementById('modalBB').value = `[img]${url}[/img]`;
        document.getElementById('modalMd').value = `![alt text](${url})`;
        document.getElementById('modalUploadTime').textContent = this.formatUploadTime(uploadTime);
        
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

    formatUploadTime(timestamp) {
        if (!timestamp) return '';
        const date = new Date(timestamp * 1000);
        return date.getFullYear() + '-' + String(date.getMonth() + 1).padStart(2, '0') + '-' + String(date.getDate()).padStart(2, '0') + ' ' + String(date.getHours()).padStart(2, '0') + ':' + String(date.getMinutes()).padStart(2, '0') + ':' + String(date.getSeconds()).padStart(2, '0');
    }
}

// Global functions for backward compatibility
function showTab(tab) {
    window.tabManager?.showTab(tab);
}

function showImageDetails(filename, url, width, height, size, uploadTime) {
    window.imageModal?.show(filename, url, width, height, size, uploadTime);
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

// Infinite scroll for Manage Images
let allFiles = <?php echo json_encode($allFiles); ?>;
const uploadDir = <?php echo json_encode($uploadDir); ?>;
const uploadUrl = <?php echo json_encode($uploadUrl); ?>;
const imagesPerBatch = 12;
let loadedCount = 0;
let loading = false;

function refreshImageList() {
    // This would ideally be an AJAX call to get fresh file list
    // For now, we'll reload the page to get fresh data
    if (window.location.hash === '#section-manage') {
        window.location.reload();
    }
}

function renderImageCard(file) {
    const filePath = uploadDir + file;
    const fileUrl = uploadUrl + file;
    const imgInfo = window.allImageInfo[file] || [0,0];
    const fileSize = window.allImageSizes[file] || 0;
    const uploadTime = window.allImageTimes[file] || 0;
    return `<div class="img-card" tabindex="0" data-size="${fileSize}" data-uploadtime="${uploadTime}">
        <input type="checkbox" class="file-checkbox" value="${file}" style="position: absolute; top: 8px; left: 8px; z-index: 10;">
        <img src="${fileUrl}" alt="" loading="lazy" onload="this.classList.add('loaded')" onclick="showImageDetails('${file.replace(/'/g, "\\'")}', '${fileUrl.replace(/'/g, "\\'")}', ${imgInfo[0]}, ${imgInfo[1]}, ${fileSize}, ${uploadTime})" style="cursor: pointer;">
        <div class="filename">${file}</div>
        <div class="upload-date" style="font-size:0.93em;color:var(--text);opacity:0.7;margin-bottom:6px;">Uploaded: ${formatUploadTime(uploadTime)}</div>
        <form method="post">
            <input type="hidden" name="delete_file" value="${file}">
            <button type="submit" style="display: flex; align-items: center; gap: 6px; background: #d9534f; color: #fff; border: none; border-radius: 6px; padding: 6px 18px; font-size: 1em; cursor: pointer; margin-top: 0; margin-bottom: 0; transition: background 0.2s; font-weight: 600;">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
                Delete
            </button>
        </form>
    </div>`;
}

// Precompute image info in PHP for all files
window.allImageInfo = {};
window.allImageSizes = {};
window.allImageTimes = {};
<?php foreach ($allFiles as $file):
    $filePath = $uploadDir . $file;
    $imgInfo = @getimagesize($filePath);
    $fileSize = filesize($filePath);
    $uploadTime = filemtime($filePath);
?>
window.allImageInfo[<?php echo json_encode($file); ?>] = [<?php echo (int)($imgInfo[0] ?? 0); ?>, <?php echo (int)($imgInfo[1] ?? 0); ?>];
window.allImageSizes[<?php echo json_encode($file); ?>] = <?php echo (int)$fileSize; ?>;
window.allImageTimes[<?php echo json_encode($file); ?>] = <?php echo (int)$uploadTime; ?>;
<?php endforeach; ?>

function loadNextBatch() {
    if (loading) return;
    loading = true;
    const gallery = document.getElementById('imagesGallery');
    if (!gallery) return;
    gallery.classList.add('loading');
    setTimeout(() => { // Simulate async
        let html = '';
        for (let i = loadedCount; i < Math.min(loadedCount + imagesPerBatch, allFiles.length); i++) {
            html += renderImageCard(allFiles[i]);
        }
        gallery.insertAdjacentHTML('beforeend', html);
        loadedCount += imagesPerBatch;
        gallery.classList.remove('loading');
        loading = false;
        // Re-initialize checkboxes and modal triggers
        if (window.fileManager) window.fileManager.init();
        collectModalImages();
    }, 200); // Simulate loading delay
}

function handleScroll() {
    const gallery = document.getElementById('imagesGallery');
    if (!gallery) return;
    const scrollY = window.scrollY || window.pageYOffset;
    const viewport = window.innerHeight;
    const galleryRect = gallery.getBoundingClientRect();
    if (galleryRect.bottom - 100 < viewport) {
        if (loadedCount < allFiles.length) {
            loadNextBatch();
        }
    }
}

function initGallery() {
    loadedCount = 0;
    const gallery = document.getElementById('imagesGallery');
    if (gallery) {
        gallery.innerHTML = '';
        if (allFiles.length === 0) {
            gallery.innerHTML = '<div style="color:var(--text);opacity:0.7;font-size:1em;grid-column:1/-1;text-align:center;padding:40px;">No images uploaded yet.</div>';
        } else {
            loadNextBatch();
        }
    }
}

window.addEventListener('scroll', handleScroll);

// Add modal navigation functions back
window.imageModalImages = [];
window.imageModalIndex = 0;

function collectModalImages() {
    window.imageModalImages = [];
    document.querySelectorAll('.images .img-card img').forEach((img) => {
        const card = img.closest('.img-card');
        const filename = card.querySelector('.filename').textContent;
        const url = img.src;
        const size = parseInt(card.getAttribute('data-size')) || 0;
        const dims = img.naturalWidth && img.naturalHeight ? [img.naturalWidth, img.naturalHeight] : [0,0];
        const uploadTime = parseInt(card.getAttribute('data-uploadtime')) || 0;
        window.imageModalImages.push({ filename, url, size, dims, uploadTime });
    });
}

function showImageDetails(filename, url, width, height, size, uploadTime) {
    collectModalImages();
    if (!window.imageModalImages.length) return;
    const idx = window.imageModalImages.findIndex(img => img.filename === filename && img.url === url);
    window.imageModalIndex = idx >= 0 ? idx : 0;
    showImageDetailsByIndex(window.imageModalIndex);
}

function showImageDetailsByIndex(idx) {
    const imgs = window.imageModalImages;
    if (!imgs.length) return;
    if (idx < 0) idx = imgs.length - 1;
    if (idx >= imgs.length) idx = 0;
    window.imageModalIndex = idx;
    const img = imgs[idx];
    var modal = document.getElementById('imgDetailsModal');
    if (!modal) return;
    modal.style.display = 'flex';
    document.getElementById('modalImg').src = img.url;
    document.getElementById('modalFilename').textContent = img.filename;
    document.getElementById('modalSize').textContent = formatBytes(img.size);
    document.getElementById('modalDims').textContent = img.dims[0] + ' × ' + img.dims[1];
    document.getElementById('modalDirect').value = img.url;
    document.getElementById('modalHtml').value = '<img src="' + img.url + '" alt="Image" />';
    document.getElementById('modalBB').value = '[img]' + img.url + '[/img]';
    document.getElementById('modalMd').value = '![alt text](' + img.url + ')';
    document.getElementById('modalUploadTime').textContent = formatUploadTime(img.uploadTime);
}

document.getElementById('modalPrevBtn').onclick = function(e) {
    e.stopPropagation();
    if (!window.imageModalImages.length) return;
    showImageDetailsByIndex(window.imageModalIndex - 1);
};
document.getElementById('modalNextBtn').onclick = function(e) {
    e.stopPropagation();
    if (!window.imageModalImages.length) return;
    showImageDetailsByIndex(window.imageModalIndex + 1);
};
document.addEventListener('keydown', function(e) {
    var modal = document.getElementById('imgDetailsModal');
    if (modal && modal.style.display === 'flex' && window.imageModalImages.length) {
        if (e.key === 'ArrowLeft') showImageDetailsByIndex(window.imageModalIndex - 1);
        if (e.key === 'ArrowRight') showImageDetailsByIndex(window.imageModalIndex + 1);
    }
});

function formatBytes(bytes) {
    if (bytes < 1024) return bytes + ' B';
    let k = 1024, sizes = ['B', 'KB', 'MB', 'GB', 'TB'], i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
}

function formatUploadTime(timestamp) {
    if (!timestamp) return '';
    const date = new Date(timestamp * 1000);
    return date.getFullYear() + '-' + String(date.getMonth() + 1).padStart(2, '0') + '-' + String(date.getDate()).padStart(2, '0') + ' ' + String(date.getHours()).padStart(2, '0') + ':' + String(date.getMinutes()).padStart(2, '0') + ':' + String(date.getSeconds()).padStart(2, '0');
}

// Initialize gallery when DOM is ready
document.addEventListener('DOMContentLoaded', function() {
    initGallery();
});

// Refresh gallery when switching to Manage Images tab
const originalShowTab = window.tabManager?.showTab;
if (window.tabManager) {
    window.tabManager.showTab = function(tab) {
        originalShowTab.call(this, tab);
        if (tab === 'manage') {
            setTimeout(initGallery, 100); // Small delay to ensure tab is visible
        }
    };
}
</script>
</body>
</html> 