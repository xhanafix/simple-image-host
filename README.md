# Image Host - Modern PHP Image Upload & Management

A modern, responsive image hosting application built with PHP, featuring drag-and-drop uploads with live previews, infinite scroll gallery, upload progress indicators, and beautiful UI with minimal modern Flaticon-style icons.

## ✨ Features

### 🎨 Modern UI/UX
- **Clean, responsive design** with smooth animations and transitions
- **Dark/Light theme toggle** with minimal sun/moon icons
- **Modern Flaticon-style SVG icons** throughout the interface
- **Smooth infinite scroll** for browsing images
- **Drag & drop upload** with live previews and visual feedback
- **Upload progress indicators** with real-time status updates

### 📤 Upload Features
- **Multiple image upload** support (JPG, PNG, GIF)
- **File size validation** (up to 10MB per image)
- **Real-time preview** before upload with thumbnails
- **Live progress feedback** during upload process
- **File validation** with clear error messages
- **Individual file removal** before upload
- **Share codes generation** (Direct, HTML, BBCode, Markdown)

### 🖼️ Image Management
- **Infinite scroll gallery** - no pagination needed
- **Bulk selection** with Select All functionality
- **Individual and bulk deletion**
- **Image details modal** with navigation (Next/Previous + arrow keys)
- **Upload timestamps** displayed for each image
- **Responsive masonry layout**
- **Modern delete icons** with consistent styling

### 🔧 Technical Features
- **POST/Redirect/GET pattern** prevents form resubmission
- **Session-based upload results** display
- **Secure file handling** with proper validation
- **Cross-browser compatibility**
- **Mobile-responsive design**
- **Smooth animations** and transitions

## 🚀 Installation

1. **Download** the files to your web server directory
2. **Ensure PHP** is installed and configured
3. **Create uploads directory** (or it will be created automatically)
4. **Set permissions** to allow file uploads (755 for uploads directory)
5. **Access** `index.php` in your browser

## 📖 Usage

### Uploading Images
1. Open the **Upload Images** tab
2. **Drag & drop** images or **click to select** files
3. **Review previews** with thumbnails and file details
4. **Remove unwanted files** using the X button
5. Click **Upload All** to start upload with progress bar
6. **Watch progress** with real-time status updates
7. Copy any of the generated share codes

### Managing Images
1. Switch to the **Manage Images** tab
2. **Scroll through** your uploaded images (infinite scroll)
3. **Use checkboxes** to select images for bulk operations
4. **Click individual images** to view details in modal
5. **Use Next/Previous buttons** or arrow keys to navigate modal
6. **Delete individual images** or use bulk delete
7. **View upload timestamps** for each image

### Theme Switching
- Click the **theme toggle** (sun/moon icon) in the top-right corner
- Theme preference is saved in localStorage
- **Auto-detects** system preference on first visit

## 📁 File Structure

```
imagehost/
├── index.php          # Main application file
├── uploads/           # Image storage directory (auto-created)
└── README.md          # This file
```

## 🔒 Security Notes

- **File type validation** - Only JPG, PNG, and GIF allowed
- **Size limits** - Maximum 10MB per image
- **Secure file handling** - Files stored with unique names
- **No authentication** - Add your own if needed for production
- **Path traversal protection** - Secure file operations

## ⚙️ Customization

### File Types & Size
Edit these variables in `index.php`:
```php
$allowedTypes = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/gif' => 'gif'];
$maxFileSize = 10 * 1024 * 1024; // 10MB
```

### Upload Directory
Change these variables:
```php
$uploadDir = __DIR__ . '/uploads/';
$uploadUrl = $baseUrl . '/uploads/';
```

### Batch Size
Modify the infinite scroll batch size:
```javascript
const imagesPerBatch = 12; // Images loaded per scroll
```

### Progress Timing
Adjust upload progress simulation:
```javascript
// In handleSubmit method
setTimeout(() => {
    // Upload completion timing
}, 2000); // Change this value
```

## 🌐 Browser Support

- **Chrome/Edge** (recommended)
- **Firefox** (full support)
- **Safari** (full support)
- **Mobile browsers** (responsive design)

## 🎯 Key Improvements

### Latest Updates
- ✅ **Upload progress indicators** with real-time feedback
- ✅ **Minimal modern icons** throughout the interface
- ✅ **Live file previews** with drag & drop
- ✅ **Individual file removal** before upload
- ✅ **Smooth animations** and transitions
- ✅ **Enhanced error handling** and validation
- ✅ **Improved user experience** with visual feedback

### User Experience
- **Intuitive drag & drop** with visual feedback
- **Immediate file previews** with thumbnails
- **Progress tracking** during uploads
- **Smooth navigation** between images
- **Responsive design** for all devices
- **Modern, clean interface** with consistent styling

## 📄 License

This project is open source and free to use for personal or commercial projects.

## 🙏 Credits

- **Icons**: Minimal modern Flaticon-style SVG icons
- **Fonts**: Inter font family for clean typography
- **Design**: Clean, modern UI patterns
- **Animations**: Smooth CSS transitions and effects 