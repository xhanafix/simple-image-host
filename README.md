# Image Host PHP App

A modern, responsive, and secure image hosting web app built with a single PHP file.

## Features
- **Drag & Drop Upload:** Upload one or multiple images at once (JPG, PNG, GIF, max 10MB each).
- **Bulk Upload:** Select or drag multiple files for upload.
- **Image Preview:** See thumbnails before uploading.
- **Share Codes:** Get Direct Link, HTML, BBCode, and Markdown for each uploaded image.
- **Manage Images:** View, preview, and delete images individually or in bulk.
- **Select All:** Quickly select all images for bulk deletion.
- **Dark/Light Theme:** Auto-detects system preference, with manual toggle.
- **Responsive Design:** Works on desktop and mobile.
- **Security:** Validates file type/size, prevents path traversal, and only allows image uploads.

## Setup
1. **Requirements:**
   - PHP 7.2 or higher
   - A web server (Apache, Nginx, etc.)
   - Write permissions for the `/uploads` directory

2. **Installation:**
   - Place `index.php` in your web root or desired directory.
   - (Optional) If not present, create an empty `uploads` directory in the same folder as `index.php`.
   - Ensure the web server can write to the `uploads` directory (e.g., `chmod 755 uploads` on Linux).

3. **Usage:**
   - Open `index.php` in your browser.
   - Use the **Upload Image** tab to drag & drop or select images for upload.
   - After upload, copy any of the share codes for your image.
   - Use the **Manage Images** tab to view, preview, select, and delete images.
   - Use the **Select All** checkbox to select/deselect all images for bulk deletion.
   - Click the theme toggle (top right) to switch between dark and light mode.

## Security Notes
- Only image files (JPG, PNG, GIF) up to 10MB are allowed.
- All uploaded files are stored in the `/uploads` directory with unique names.
- Deletion is only allowed for files in the `/uploads` directory.
- No authentication is included; anyone with access to the page can upload or delete images.

## Customization
- To restrict access, add authentication logic to `index.php`.
- To change allowed file types or size, edit the `$allowedTypes` and `$maxFileSize` variables in `index.php`.
- To change the upload directory, edit the `$uploadDir` and `$uploadUrl` variables.

## License
This project is open source and free to use for personal or commercial projects. 