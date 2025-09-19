<?php
session_start();

// Include the configuration file
require_once 'config.php';

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FTP File Manager</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        body {
            background: linear-gradient(135deg, #1a2a6c 0%, #2c3e50 100%);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
            position: relative;
            overflow-x: hidden;
        }
        
        /* Development pattern background */
        body::before {
            content: "";
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-image: 
                linear-gradient(45deg, rgba(255, 255, 255, 0.05) 25%, transparent 25%),
                linear-gradient(-45deg, rgba(255, 255, 255, 0.05) 25%, transparent 25%),
                linear-gradient(45deg, transparent 75%, rgba(255, 255, 255, 0.05) 75%),
                linear-gradient(-45deg, transparent 75%, rgba(255, 255, 255, 0.05) 75%);
            background-size: 20px 20px;
            background-position: 0 0, 0 10px, 10px -10px, -10px 0px;
            z-index: 0;
        }
        
        .logo-container {
            position: absolute;
            top: 20px;
            left: 20px;
            z-index: 100;
        }
        
        .logo-container img {
            width: 230px;
            height: auto;
        }
        
        .container {
            width: 100%;
            max-width: 800px;
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
            overflow: hidden;
            margin-top: 40px; /* Added to accommodate the logo */
            z-index: 1;
            position: relative;
        }
        
        .header {
            background: #3498db;
            color: white;
            padding: 25px;
            text-align: center;
        }
        
        .header h1 {
            font-size: 24px;
            margin-bottom: 10px;
        }
        
        .header p {
            font-size: 14px;
            opacity: 0.9;
        }
        
        .tabs {
            display: flex;
            background: #2980b9;
        }
        
        .tab {
            flex: 1;
            padding: 15px;
            text-align: center;
            color: white;
            cursor: pointer;
            transition: background 0.3s;
        }
        
        .tab.active {
            background: #1f618d;
            font-weight: bold;
        }
        
        .tab-content {
            display: none;
            padding: 30px;
        }
        
        .tab-content.active {
            display: block;
        }
        
        .upload-area {
            border: 3px dashed #3498db;
            border-radius: 10px;
            padding: 40px 20px;
            text-align: center;
            margin-bottom: 20px;
            transition: all 0.3s;
            cursor: pointer;
            position: relative;
        }
        
        .upload-area.active {
            background-color: #e6f2ff;
            border-color: #1f618d;
        }
        
        .upload-area i {
            font-size: 50px;
            color: #3498db;
            margin-bottom: 15px;
        }
        
        .upload-area h3 {
            color: #333;
            margin-bottom: 10px;
        }
        
        .upload-area p {
            color: #777;
            font-size: 14px;
        }
        
        .file-input {
            position: absolute;
            width: 100%;
            height: 100%;
            top: 0;
            left: 0;
            opacity: 0;
            cursor: pointer;
        }
        
        .btn {
            background: #3498db;
            color: white;
            border: none;
            padding: 12px 25px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 16px;
            font-weight: 600;
            display: block;
            width: 100%;
            transition: background 0.3s;
            margin-top: 15px;
        }
        
        .btn:hover {
            background: #2980b9;
        }
        
        .btn:disabled {
            background: #b0b0b0;
            cursor: not-allowed;
        }
        
        .btn-secondary {
            background: #6c757d;
        }
        
        .btn-secondary:hover {
            background: #5a6268;
        }
        
        .progress-container {
            margin-top: 20px;
            display: none;
        }
        
        .progress-bar {
            height: 10px;
            background: #e0e0e0;
            border-radius: 5px;
            overflow: hidden;
            margin-bottom: 10px;
        }
        
        .progress {
            height: 100%;
            background: linear-gradient(90deg, #3498db, #1f618d);
            width: 0%;
            transition: width 0.3s;
        }
        
        .status {
            text-align: center;
            font-size: 14px;
            color: #555;
        }
        
        .result {
            margin-top: 20px;
            padding: 15px;
            border-radius: 5px;
            display: none;
            text-align: center;
        }
        
        .success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .file-info {
            margin-top: 15px;
            padding: 10px;
            background: #f9f9f9;
            border-radius: 5px;
            font-size: 14px;
            display: none;
        }
        
        .file-info p {
            margin: 5px 0;
            color: #555;
        }
        
        .file-size {
            font-weight: bold;
        }
        
        .file-type {
            text-transform: uppercase;
        }
        
        .form-notice {
            margin-top: 15px;
            padding: 10px;
            background: #fff3cd;
            border-radius: 5px;
            font-size: 14px;
            color: #856404;
            border: 1px solid #ffeeba;
            text-align: center;
        }
        
        .files-list {
            margin-top: 20px;
            max-height: 300px;
            overflow-y: auto;
            border: 1px solid #ddd;
            border-radius: 5px;
            padding: 10px;
        }
        
        .file-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px;
            border-bottom: 1px solid #eee;
        }
        
        .file-item:last-child {
            border-bottom: none;
        }
        
        .file-name {
            flex: 1;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        
        .file-actions {
            display: flex;
            gap: 10px;
        }
        
        .refresh-btn {
            background: #28a745;
            color: white;
            border: none;
            padding: 8px 15px;
            border-radius: 5px;
            cursor: pointer;
            margin-bottom: 15px;
        }
        
        .refresh-btn:hover {
            background: #218838;
        }
        
        .no-files {
            text-align: center;
            padding: 20px;
            color: #777;
        }
        
        .btn-logout {
            background: rgba(255, 255, 255, 0.15);
            color: white;
            border: 1px solid rgba(255, 255, 255, 0.3);
            text-decoration: none;
            width: max-content;
            display: table-cell;
            margin-right: 5px;
            padding: 8px 15px;
            border-radius: 5px;
            transition: all 0.3s ease;
        }
        
        .btn-logout:hover {
            background: rgba(255, 100, 100, 0.2);
            color: #ffcccc;
            transform: translateY(-2px);
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.2);
        }
        
        .top-right-buttons {
            position: absolute;
            top: 20px;
            right: 20px;
            z-index: 100;
            display: table;
            border-spacing: 5px;
        }
        
        /* Media queries for responsive design */
        @media (max-width: 768px) {
            .logo-container {
                position: relative;
                top: 0;
                left: 0;
                text-align: center;
                margin-bottom: 10px;
                width: 100%;
            }
            
            .logo-container img {
                width: 180px;
                height: auto;
            }
            
            .top-right-buttons {
                position: relative;
                top: 0;
                right: 0;
                margin: 0 auto 15px;
                display: flex;
                justify-content: center;
            }
            
            .container {
                margin-top: 0;
                width: 100%;
                max-width: 100%;
                border-radius: 0;
            }
            
            body {
                flex-direction: column;
                padding: 10px;
                display: block;
            }
            
            .header {
                padding: 15px;
            }
            
            .header h1 {
                font-size: 20px;
            }
            
            .tab-content {
                padding: 15px;
            }
            
            .upload-area {
                padding: 20px 10px;
            }
            
            .upload-area i {
                font-size: 40px;
            }
            
            .files-list {
                max-height: 250px;
            }
        }
        
        @media (max-width: 480px) {
            .logo-container img {
                width: 150px;
            }
            
            .btn-logout {
                padding: 6px 12px;
                font-size: 14px;
            }
            
            .header h1 {
                font-size: 18px;
            }
            
            .header p {
                font-size: 12px;
            }
            
            .tab {
                padding: 12px 5px;
                font-size: 14px;
            }
            
            .upload-area h3 {
                font-size: 16px;
            }
            
            .upload-area p {
                font-size: 12px;
            }
            
            .file-info {
                font-size: 12px;
            }
            
            .form-notice {
                font-size: 12px;
            }
        }
    </style>
</head>
<body>
    <div class="logo-container">
        <img src="https://ik.imagekit.io/nuvq7aygp/BMB_Logo.jpg" alt="BMB Logo">
    </div>
    
    <div class="top-right-buttons">
        <a href="start.php" class="btn btn-logout">
            <i class="fas fa-sign-out-alt"></i> 🏠︎ Home
        </a>
        <a href="logout.php" class="btn btn-logout">
            <i class="fas fa-sign-out-alt"></i> ➜] Logout
        </a>
    </div>
    
    <div class="container">
        <div class="header">
            <h1>Development File Manager</h1>
            <p>Upload and download documents related to BMB Development.</p>
        </div>
        
        <div class="tabs">
            <div class="tab active" data-tab="upload">Upload Files</div>
            <div class="tab" data-tab="download">Download Files</div>
        </div>
        
        <!-- Upload Tab -->
        <div class="tab-content active" id="upload-tab">
            <div class="upload-container">
                <form id="uploadForm" enctype="multipart/form-data" method="post" action="ftp_manager_development.php">
                    <div class="upload-area" id="uploadArea">
                        <i>📁</i>
                        <h3>Drag & Drop your file here</h3>
                        <p>or click to browse</p>
                        <p>Supported formats: Images, Videos, Documents</p>
                        <p>Maximum file size: 50MB</p>
                        <input type="file" id="fileInput" name="file" class="file-input">
                    </div>
                    
                    <div class="file-info" id="fileInfo">
                        <p>Selected file: <span id="fileName"></span></p>
                        <p>File size: <span id="fileSize" class="file-size"></span></p>
                        <p>File type: <span id="fileType" class="file-type"></span></p>
                    </div>
                    
                    <button type="submit" id="uploadBtn" class="btn" disabled>Upload to FTP Server</button>
                </form>
                
                <div class="progress-container" id="progressContainer">
                    <div class="progress-bar">
                        <div class="progress" id="progress"></div>
                    </div>
                    <div class="status" id="status">Preparing to upload...</div>
                </div>
                
                <div class="result" id="result"></div>
                
                <div class="form-notice">
                    This application should be used only for Buymeabook development related activities.
                </div>
            </div>
        </div>
        
        <!-- Download Tab -->
        <div class="tab-content" id="download-tab">
            <div class="download-container">
                <button class="refresh-btn" id="refreshBtn">Refresh File List</button>
                
                <div class="files-list" id="filesList">
                    <div class="no-files">No files found. Click refresh to load files.</div>
                </div>
                
                <div class="result" id="downloadResult"></div>
                
                <div class="form-notice">
                    This application should be used only for Buymeabook development related activities.
                </div>
            </div>
        </div>
    </div>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Tab switching functionality
            const tabs = document.querySelectorAll('.tab');
            const tabContents = document.querySelectorAll('.tab-content');
            
            tabs.forEach(tab => {
                tab.addEventListener('click', () => {
                    const tabId = tab.getAttribute('data-tab');
                    
                    // Update active tab
                    tabs.forEach(t => t.classList.remove('active'));
                    tab.classList.add('active');
                    
                    // Show corresponding content
                    tabContents.forEach(content => {
                        content.classList.remove('active');
                        if (content.id === `${tabId}-tab`) {
                            content.classList.add('active');
                        }
                    });
                    
                    // If switching to download tab, load files
                    if (tabId === 'download') {
                        loadFiles();
                    }
                });
            });
            
            // Upload functionality
            const uploadForm = document.getElementById('uploadForm');
            const uploadArea = document.getElementById('uploadArea');
            const fileInput = document.getElementById('fileInput');
            const uploadBtn = document.getElementById('uploadBtn');
            const progressContainer = document.getElementById('progressContainer');
            const progress = document.getElementById('progress');
            const status = document.getElementById('status');
            const result = document.getElementById('result');
            const fileInfo = document.getElementById('fileInfo');
            const fileName = document.getElementById('fileName');
            const fileSize = document.getElementById('fileSize');
            const fileType = document.getElementById('fileType');
            
            let selectedFile = null;
            
            // Drag and drop functionality
            ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
                uploadArea.addEventListener(eventName, preventDefaults, false);
            });
            
            function preventDefaults(e) {
                e.preventDefault();
                e.stopPropagation();
            }
            
            ['dragenter', 'dragover'].forEach(eventName => {
                uploadArea.addEventListener(eventName, highlight, false);
            });
            
            ['dragleave', 'drop'].forEach(eventName => {
                uploadArea.addEventListener(eventName, unhighlight, false);
            });
            
            function highlight() {
                uploadArea.classList.add('active');
            }
            
            function unhighlight() {
                uploadArea.classList.remove('active');
            }
            
            uploadArea.addEventListener('drop', handleDrop, false);
            
            function handleDrop(e) {
                const dt = e.dataTransfer;
                const files = dt.files;
                
                if (files.length > 0) {
                    handleFiles(files);
                }
            }
            
            fileInput.addEventListener('change', function() {
                handleFiles(this.files);
            });
            
            function handleFiles(files) {
                if (files.length > 0) {
                    selectedFile = files[0];
                    
                    // Check file size before proceeding (client-side validation)
                    if (selectedFile.size > 50 * 1024 * 1024) {
                        showResult('error', 'File size too large. Maximum allowed size is 50MB.');
                        fileInput.value = '';
                        return;
                    }
                    
                    // Display file information
                    fileName.textContent = selectedFile.name;
                    fileSize.textContent = formatFileSize(selectedFile.size);
                    fileType.textContent = selectedFile.type || 'Unknown';
                    fileInfo.style.display = 'block';
                    
                    // Enable upload button
                    uploadBtn.disabled = false;
                }
            }
            
            function formatFileSize(bytes) {
                if (bytes === 0) return '0 Bytes';
                const k = 1024;
                const sizes = ['Bytes', 'KB', 'MB', 'GB'];
                const i = Math.floor(Math.log(bytes) / Math.log(k));
                return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
            }
            
            uploadForm.addEventListener('submit', function(e) {
                e.preventDefault();
                
                if (!selectedFile) return;
                
                // Disable upload button during upload
                uploadBtn.disabled = true;
                
                // Show progress container
                progressContainer.style.display = 'block';
                result.style.display = 'none';
                
                // Create FormData with the file
                const formData = new FormData();
                formData.append('file', selectedFile);
                formData.append('action', 'upload');
                
                // Send to server
                const xhr = new XMLHttpRequest();
                
                // Track upload progress
                xhr.upload.addEventListener('progress', function(e) {
                    if (e.lengthComputable) {
                        const percentComplete = (e.loaded / e.total) * 100;
                        progress.style.width = percentComplete + '%';
                        status.textContent = 'Uploading: ' + Math.round(percentComplete) + '%';
                    }
                });
                
                xhr.addEventListener('load', function() {
                    if (xhr.status === 200) {
                        try {
                            const response = JSON.parse(xhr.responseText);
                            
                            if (response.success) {
                                showResult('success', response.message);
                            } else {
                                showResult('error', response.message);
                            }
                        } catch (e) {
                            showResult('error', 'Invalid server response: ' + xhr.responseText);
                        }
                    } else {
                        showResult('error', 'Upload failed. Server returned status: ' + xhr.status);
                    }
                    
                    // Reset UI
                    progressContainer.style.display = 'none';
                    uploadBtn.disabled = false;
                    
                    // Clear file selection
                    fileInput.value = '';
                    fileInfo.style.display = 'none';
                    selectedFile = null;
                });
                
                xhr.addEventListener('error', function() {
                    showResult('error', 'An error occurred during upload. Please check your connection.');
                    progressContainer.style.display = 'none';
                    uploadBtn.disabled = false;
                });
                
                xhr.open('POST', 'ftp_manager_development.php', true);
                xhr.send(formData);
            });
            
            function showResult(type, message) {
                result.textContent = message;
                result.className = 'result ' + type;
                result.style.display = 'block';
            }
            
            // Download functionality
            const refreshBtn = document.getElementById('refreshBtn');
            const filesList = document.getElementById('filesList');
            const downloadResult = document.getElementById('downloadResult');
            
            refreshBtn.addEventListener('click', loadFiles);
            
            function loadFiles() {
                filesList.innerHTML = '<div class="no-files">Loading files...</div>';
                
                const xhr = new XMLHttpRequest();
                xhr.open('POST', 'ftp_manager_development.php', true);
                xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
                
                xhr.onload = function() {
                    if (xhr.status === 200) {
                        try {
                            const response = JSON.parse(xhr.responseText);
                            
                            if (response.success) {
                                displayFiles(response.files);
                            } else {
                                filesList.innerHTML = `<div class="no-files">${response.message}</div>`;
                            }
                        } catch (e) {
                            filesList.innerHTML = '<div class="no-files">Error parsing server response</div>';
                        }
                    } else {
                        filesList.innerHTML = '<div class="no-files">Failed to load files</div>';
                    }
                };
                
                xhr.onerror = function() {
                    filesList.innerHTML = '<div class="no-files">Network error loading files</div>';
                };
                
                xhr.send('action=list_files');
            }
            
            function displayFiles(files) {
                if (!files || files.length === 0) {
                    filesList.innerHTML = '<div class="no-files">No files found on the server</div>';
                    return;
                }
                
                filesList.innerHTML = '';
                
                files.forEach(file => {
                    const fileItem = document.createElement('div');
                    fileItem.className = 'file-item';
                    
                    const fileName = document.createElement('div');
                    fileName.className = 'file-name';
                    fileName.textContent = file;
                    
                    const fileActions = document.createElement('div');
                    fileActions.className = 'file-actions';
                    
                    const downloadBtn = document.createElement('button');
                    downloadBtn.className = 'btn-secondary';
                    downloadBtn.textContent = 'Download';
                    downloadBtn.addEventListener('click', () => downloadFile(file));
                    
                    fileActions.appendChild(downloadBtn);
                    fileItem.appendChild(fileName);
                    fileItem.appendChild(fileActions);
                    
                    filesList.appendChild(fileItem);
                });
            }
            
            function downloadFile(filename) {
                downloadResult.style.display = 'none';
                downloadResult.textContent = 'Preparing download...';
                downloadResult.className = 'result success';
                downloadResult.style.display = 'block';
                
                // Create a form and submit it to trigger the download
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = 'ftp_manager_development.php';
                
                const actionInput = document.createElement('input');
                actionInput.type = 'hidden';
                actionInput.name = 'action';
                actionInput.value = 'download';
                form.appendChild(actionInput);
                
                const fileInput = document.createElement('input');
                fileInput.type = 'hidden';
                fileInput.name = 'file_name';
                fileInput.value = filename;
                form.appendChild(fileInput);
                
                document.body.appendChild(form);
                form.submit();
                document.body.removeChild(form);
                
                // Show a message after a short delay
                setTimeout(() => {
                    downloadResult.textContent = 'Download started for ' + filename;
                    downloadResult.className = 'result success';
                }, 1000);
            }
        });
    </script>
</body>
</html>