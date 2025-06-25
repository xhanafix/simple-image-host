<?php
// Set the upload directory
$uploadDir = __DIR__ . '/uploads/';
$uploadUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://" . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) . '/uploads/';

$message = '';
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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Uploaded Images</title>
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
        .container {
            background: var(--container);
            padding: 32px 24px 24px 24px;
            border-radius: 16px;
            max-width: 700px;
            margin: 48px auto;
            box-shadow: 0 4px 24px rgba(0,0,0,0.08);
            display: flex;
            flex-direction: column;
            align-items: center;
        }
        h2 {
            margin-top: 0;
            font-weight: 600;
            color: var(--text);
            font-size: 1.4rem;
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
            .container {
                max-width: 98vw;
                padding: 18px 4vw 18px 4vw;
                margin: 16px auto;
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
    </style>
</head>
<body>
<div class="container">
    <h2>Manage Uploaded Images</h2>
    <?php if ($message): ?>
        <div class="message<?php echo ($message === 'File deleted successfully!') ? ' success' : ''; ?>"><?php echo htmlspecialchars($message); ?></div>
    <?php endif; ?>
    <div class="images">
    <?php
    if (is_dir($uploadDir)) {
        $files = array_diff(scandir($uploadDir), array('.', '..'));
        $hasImages = false;
        foreach ($files as $file) {
            $filePath = $uploadDir . $file;
            $fileUrl = $uploadUrl . $file;
            if (is_file($filePath) && @getimagesize($filePath)) {
                $hasImages = true;
                echo '<div class="img-card">';
                echo '<img src="' . htmlspecialchars($fileUrl) . '" alt="">';
                echo '<div class="filename">' . htmlspecialchars($file) . '</div>';
                echo '<form method="post"><input type="hidden" name="delete_file" value="' . htmlspecialchars($file) . '"><button type="submit">Delete</button></form>';
                echo '</div>';
            }
        }
        if (!$hasImages) {
            echo '<div style="color:var(--text);opacity:0.7;font-size:1em;">No images uploaded yet.</div>';
        }
    }
    ?>
    </div>
</div>
<script>
// Optional: Theme auto-detect (matches index.php)
function setTheme(mode) {
    document.documentElement.setAttribute('data-theme', mode);
}
function getPreferredTheme() {
    return localStorage.getItem('theme') || (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
}
setTheme(getPreferredTheme());
</script>
</body>
</html> 