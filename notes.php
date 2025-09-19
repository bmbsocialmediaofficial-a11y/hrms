<?php
session_start();

require_once 'config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

try {
    $conn = new PDO("mysql:host=$servername;dbname=$dbname", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

// Create notes table if it doesn't exist
$createTableSQL = "
CREATE TABLE IF NOT EXISTS notes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT(6) UNSIGNED NOT NULL,
    title VARCHAR(255) NOT NULL,
    content TEXT,
    category VARCHAR(100),
    is_important BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE
);
";

$conn->exec($createTableSQL);

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['create_note'])) {
        $title = $_POST['title'];
        $content = $_POST['content'];
        $category = $_POST['category'];
        $is_important = isset($_POST['is_important']) ? 1 : 0;
        $employee_id = $_SESSION['user_id'];
        
        $stmt = $conn->prepare("INSERT INTO notes (employee_id, title, content, category, is_important) 
                               VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$employee_id, $title, $content, $category, $is_important]);
        
        header("Location: notes.php");
        exit();
    }
    
    if (isset($_POST['update_note'])) {
        $note_id = $_POST['note_id'];
        $title = $_POST['title'];
        $content = $_POST['content'];
        $category = $_POST['category'];
        $is_important = isset($_POST['is_important']) ? 1 : 0;
        
        // Verify that the note belongs to the current user
        $stmt = $conn->prepare("SELECT * FROM notes WHERE id = ? AND employee_id = ?");
        $stmt->execute([$note_id, $_SESSION['user_id']]);
        $note = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($note) {
            $stmt = $conn->prepare("UPDATE notes SET title=?, content=?, category=?, is_important=? WHERE id=?");
            $stmt->execute([$title, $content, $category, $is_important, $note_id]);
        }
        
        header("Location: notes.php");
        exit();
    }
}

// Handle note deletion
if (isset($_GET['delete'])) {
    $note_id = $_GET['delete'];
    
    // Verify that the note belongs to the current user
    $stmt = $conn->prepare("SELECT * FROM notes WHERE id = ? AND employee_id = ?");
    $stmt->execute([$note_id, $_SESSION['user_id']]);
    $note = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($note) {
        $stmt = $conn->prepare("DELETE FROM notes WHERE id = ?");
        $stmt->execute([$note_id]);
    }
    
    header("Location: notes.php");
    exit();
}

// Get note data for editing if note_id is provided
$edit_note = null;
if (isset($_GET['edit'])) {
    $note_id = $_GET['edit'];
    $stmt = $conn->prepare("SELECT * FROM notes WHERE id = ? AND employee_id = ?");
    $stmt->execute([$note_id, $_SESSION['user_id']]);
    $edit_note = $stmt->fetch(PDO::FETCH_ASSOC);
}

// Get all notes for the current user
$employee_id = $_SESSION['user_id'];
$filter = isset($_GET['filter']) ? $_GET['filter'] : 'all';

if ($filter === 'important') {
    $stmt = $conn->prepare("SELECT * FROM notes WHERE employee_id = ? AND is_important = TRUE ORDER BY updated_at DESC");
    $stmt->execute([$employee_id]);
} else if ($filter === 'category' && isset($_GET['category'])) {
    $category = $_GET['category'];
    $stmt = $conn->prepare("SELECT * FROM notes WHERE employee_id = ? AND category = ? ORDER BY updated_at DESC");
    $stmt->execute([$employee_id, $category]);
} else {
    $stmt = $conn->prepare("SELECT * FROM notes WHERE employee_id = ? ORDER BY updated_at DESC");
    $stmt->execute([$employee_id]);
}

$notes = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get all categories for the current user
$stmt = $conn->prepare("SELECT DISTINCT category FROM notes WHERE employee_id = ? AND category IS NOT NULL ORDER BY category");
$stmt->execute([$employee_id]);
$categories = $stmt->fetchAll(PDO::FETCH_COLUMN);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BMB Noteview - Personal Note Taking System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        body {
            background: linear-gradient(135deg, #6a11cb 0%, #2575fc 100%);
            min-height: 100vh;
            color: #333;
            position: relative;
            overflow-x: hidden;
        }
        
        .logo-container {
            position: absolute;
            top: 20px;
            left: 20px;
            z-index: 10;
        }
        
        .logo {
            width: 230px;
            height: auto;
            border-radius: 0px;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.3);
            transition: all 0.3s ease;
        }
        
        .logo:hover {
            transform: translateY(-5px);
            box-shadow: 0 12px 30px rgba(0, 0, 0, 0.4);
        }
        
        .container {
            display: flex;
            flex-direction: column;
            min-height: 100vh;
            padding: 100px 20px 40px;
        }
        
        .header {
            text-align: center;
            margin-bottom: 30px;
            color: white;
        }
        
        .header h1 {
            font-size: 2.8rem;
            margin-bottom: 10px;
            text-shadow: 2px 2px 8px rgba(0, 0, 0, 0.3);
        }
        
        .header p {
            font-size: 1.2rem;
            opacity: 0.9;
        }
        
        .actions {
            display: flex;
            justify-content: center;
            margin-bottom: 20px;
            gap: 10px;
            flex-wrap: wrap;
        }
        
        .btn {
            padding: 10px 20px;
            border-radius: 20px;
            cursor: pointer;
            transition: all 0.3s;
            font-weight: 600;
            display: flex;
            align-items: center;
            text-decoration: none;
        }
        
        .btn i {
            margin-right: 8px;
        }
        
        .btn-primary {
            background: rgba(255, 255, 255, 0.9);
            border: 1px solid #6a11cb;
            color: #6a11cb;
        }
        
        .btn-primary:hover {
            background: #6a11cb;
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.2);
        }
        
        .btn-secondary {
            background: rgba(255, 255, 255, 0.9);
            border: 1px solid #17a2b8;
            color: #17a2b8;
        }
        
        .btn-secondary:hover {
            background: #17a2b8;
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.2);
        }
        
        .filters {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            flex-wrap: wrap;
            justify-content: center;
        }
        
        .filter-btn {
            padding: 8px 15px;
            background: rgba(255, 255, 255, 0.9);
            border: 1px solid #6a11cb;
            border-radius: 20px;
            color: #6a11cb;
            cursor: pointer;
            transition: all 0.3s;
            font-weight: 600;
            text-decoration: none;
        }
        
        .filter-btn.active, .filter-btn:hover {
            background: #6a11cb;
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.2);
        }
        
        .notes-panel {
            background: rgba(255, 255, 255, 0.9);
            border-radius: 15px;
            padding: 20px;
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.15);
            height: max-content;
            display: flex;
            flex-direction: column;
        }
        
        .notes-panel-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }
        
        .notes-panel-header h2 {
            color: #6a11cb;
            display: flex;
            align-items: center;
        }
        
        .notes-panel-header h2 i {
            margin-right: 10px;
        }
        
        .notes-container {
            flex: 1;
            overflow-y: auto;
        }
        
        .notes-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
        }
        
        .note-item {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 3px 10px rgba(0, 0, 0, 0.1);
            border-left: 4px solid #6a11cb;
            transition: all 0.3s;
            position: relative;
            display: flex;
            flex-direction: column;
        }
        
        .note-item:hover {
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.15);
        }
        
        .note-item.important {
            border-left-color: #dc3545;
            background: linear-gradient(to right, #fff 90%, #ffebee 100%);
        }
        
        .note-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 15px;
        }
        
        .note-title {
            font-weight: bold;
            font-size: 1.2rem;
            color: #6a11cb;
            margin-right: 10px;
            word-break: break-word;
        }
        
        .note-category {
            padding: 3px 10px;
            border-radius: 15px;
            font-size: 0.8rem;
            font-weight: bold;
            background: #6a11cb;
            color: white;
            white-space: nowrap;
        }
        
        .note-content {
            flex: 1;
            margin-bottom: 15px;
            line-height: 1.5;
            word-break: break-word;
        }
        
        .note-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: auto;
            font-size: 0.85rem;
            color: #666;
        }
        
        .note-date {
            font-style: italic;
        }
        
        .note-actions {
            display: flex;
            gap: 8px;
        }
        
        .action-btn {
            padding: 5px 10px;
            border-radius: 5px;
            border: none;
            cursor: pointer;
            font-size: 0.8rem;
            display: flex;
            align-items: center;
            transition: all 0.3s;
        }
        
        .action-btn i {
            margin-right: 5px;
        }
        
        .btn-edit {
            background: #6a11cb;
            color: white;
        }
        
        .btn-edit:hover {
            background: #5a0fb7;
        }
        
        .btn-delete {
            background: #dc3545;
            color: white;
        }
        
        .btn-delete:hover {
            background: #bd2130;
        }
        
        .important-badge {
            position: absolute;
            top: 10px;
            right: 10px;
            color: #dc3545;
            font-size: 1.2rem;
        }
        
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            justify-content: center;
            align-items: center;
        }
        
        .modal-content {
            background: white;
            border-radius: 15px;
            padding: 30px;
            width: 90%;
            max-width: 600px;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.3);
        }
        
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .close {
            font-size: 1.5rem;
            cursor: pointer;
            color: #999;
        }
        
        .close:hover {
            color: #333;
        }
        
        .form-group {
            margin-bottom: 15px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }
        
        .form-control {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 1rem;
        }
        
        textarea.form-control {
            min-height: 150px;
            resize: vertical;
        }
        
        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }
        
        .form-check {
            display: flex;
            align-items: center;
        }
        
        .form-check input {
            margin-right: 8px;
        }
        
        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: #666;
        }
        
        .empty-state i {
            font-size: 3rem;
            margin-bottom: 15px;
            color: #6a11cb;
        }
        
        .empty-state p {
            font-size: 1.1rem;
            margin-bottom: 20px;
        }
        
        @media (max-width: 768px) {
            .header h1 {
                font-size: 2.2rem;
            }
            
            .logo-container {
                position: relative;
                top: 0;
                left: 0;
                text-align: center;
                margin-bottom: 20px;
            }
            
            .container {
                padding-top: 20px;
            }
            
            .filters {
                justify-content: center;
            }
            
            .notes-grid {
                grid-template-columns: 1fr;
            }
            
            .form-grid {
                grid-template-columns: 1fr;
            }
        }
		.btn-logout {
    background: rgba(255, 255, 255, 0.15);
    color: white;
    border: 1px solid rgba(255, 255, 255, 0.3);
    text-decoration: none;
}

.btn-logout:hover {
    background: rgba(255, 100, 100, 0.2);
    color: #ffcccc;
    transform: translateY(-2px);
    box-shadow: 0 4px 10px rgba(0, 0, 0, 0.2);
}
    </style>
</head>
<body>
    <div class="logo-container">
        <img src="https://ik.imagekit.io/nuvq7aygp/BMB_Logo.jpg" alt="BMB Logo" class="logo">
    </div>
    
    <div class="container">
        <div class="header">
            <h1>BMB Noteview</h1>
            <p>Your personal note taking system</p>
        </div>
        
<div class="actions">
    <button class="btn btn-primary" onclick="openModal('createNoteModal')">
        <i class="fas fa-plus"></i> Create New Note
    </button>
    <a href="bmb_taskview.php" class="btn btn-secondary">
        <i class="fas fa-tasks"></i> Back to Tasks
    </a>
	
	
	    <a href="start.php" class="btn btn-logout">
        <i class="fas fa-sign-out-alt"></i> Home
    </a>
	
    <a href="logout.php" class="btn btn-logout">
        <i class="fas fa-sign-out-alt"></i> Logout
    </a>
	
</div>
        
        <div class="filters">
            <a href="notes.php?filter=all" class="filter-btn <?php echo $filter === 'all' ? 'active' : ''; ?>">All Notes</a>
            <a href="notes.php?filter=important" class="filter-btn <?php echo $filter === 'important' ? 'active' : ''; ?>">Important</a>
            
            <?php foreach ($categories as $category): ?>
                <a href="notes.php?filter=category&category=<?php echo urlencode($category); ?>" class="filter-btn <?php echo (isset($_GET['category']) && $_GET['category'] === $category) ? 'active' : ''; ?>">
                    <?php echo htmlspecialchars($category); ?>
                </a>
            <?php endforeach; ?>
        </div>
        
        <div class="notes-panel">
            <div class="notes-panel-header">
                <h2><i class="fas fa-sticky-note"></i> My Notes</h2>
                <span id="notes-count"><?php echo count($notes); ?> notes</span>
            </div>
            
            <div class="notes-container">
                <?php if (count($notes) > 0): ?>
                    <div class="notes-grid">
                        <?php foreach ($notes as $note): ?>
                            <div class="note-item <?php echo $note['is_important'] ? 'important' : ''; ?>">
                                <?php if ($note['is_important']): ?>
                                    <div class="important-badge" title="Important Note">
                                        <i class="fas fa-exclamation-circle"></i>
                                    </div>
                                <?php endif; ?>
                                
                                <div class="note-header">
                                    <div class="note-title"><?php echo htmlspecialchars($note['title']); ?></div>
                                    <?php if ($note['category']): ?>
                                        <span class="note-category"><?php echo htmlspecialchars($note['category']); ?></span>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="note-content">
                                    <?php echo nl2br(htmlspecialchars($note['content'])); ?>
                                </div>
                                
                                <div class="note-footer">
                                    <div class="note-date">
                                        Updated: <?php echo date('M j, Y g:i A', strtotime($note['updated_at'])); ?>
                                    </div>
                                    <div class="note-actions">
                                        <button class="action-btn btn-edit" onclick="editNote(<?php echo $note['id']; ?>)">
                                            <i class="fas fa-edit"></i> Edit
                                        </button>
                                        <button class="action-btn btn-delete" onclick="deleteNote(<?php echo $note['id']; ?>)">
                                            <i class="fas fa-trash"></i> Delete
                                        </button>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-sticky-note"></i>
                        <p>You don't have any notes yet.</p>
                        <button class="btn btn-primary" onclick="openModal('createNoteModal')">
                            <i class="fas fa-plus"></i> Create Your First Note
                        </button>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Create Note Modal -->
    <div id="createNoteModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Create New Note</h2>
                <span class="close" onclick="closeModal('createNoteModal')">&times;</span>
            </div>
            <form action="notes.php" method="POST">
                <div class="form-group">
                    <label for="title">Note Title</label>
                    <input type="text" id="title" name="title" class="form-control" required>
                </div>
                
                <div class="form-group">
                    <label for="content">Content</label>
                    <textarea id="content" name="content" class="form-control" rows="6" required></textarea>
                </div>
                
                <div class="form-grid">
                    <div class="form-group">
                        <label for="category">Category (optional)</label>
                        <input type="text" id="category" name="category" class="form-control" list="categories">
                        <datalist id="categories">
                            <?php foreach ($categories as $category): ?>
                                <option value="<?php echo htmlspecialchars($category); ?>">
                            <?php endforeach; ?>
                        </datalist>
                    </div>
                    
                    <div class="form-group">
                        <label for="is_important" class="form-check">
                            <input type="checkbox" id="is_important" name="is_important" value="1">
                            Mark as Important
                        </label>
                    </div>
                </div>
                
                <button type="submit" name="create_note" class="btn btn-primary">Save Note</button>
                <button type="button" class="btn btn-secondary" onclick="closeModal('createNoteModal')">Cancel</button>
            </form>
        </div>
    </div>
    
    <!-- Edit Note Modal -->
    <div id="editNoteModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Edit Note</h2>
                <span class="close" onclick="closeModal('editNoteModal')">&times;</span>
            </div>
            <form action="notes.php" method="POST">
                <input type="hidden" id="edit_note_id" name="note_id" value="<?php echo $edit_note['id'] ?? ''; ?>">
                
                <div class="form-group">
                    <label for="edit_title">Note Title</label>
                    <input type="text" id="edit_title" name="title" class="form-control" value="<?php echo $edit_note['title'] ?? ''; ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="edit_content">Content</label>
                    <textarea id="edit_content" name="content" class="form-control" rows="6" required><?php echo $edit_note['content'] ?? ''; ?></textarea>
                </div>
                
                <div class="form-grid">
                    <div class="form-group">
                        <label for="edit_category">Category (optional)</label>
                        <input type="text" id="edit_category" name="category" class="form-control" value="<?php echo $edit_note['category'] ?? ''; ?>" list="categories">
                    </div>
                    
                    <div class="form-group">
                        <label for="edit_is_important" class="form-check">
                            <input type="checkbox" id="edit_is_important" name="is_important" value="1" <?php echo ($edit_note['is_important'] ?? 0) ? 'checked' : ''; ?>>
                            Mark as Important
                        </label>
                    </div>
                </div>
                
                <button type="submit" name="update_note" class="btn btn-primary">Update Note</button>
                <button type="button" class="btn btn-secondary" onclick="closeModal('editNoteModal')">Cancel</button>
            </form>
        </div>
    </div>

    <script>
        // Modal functions
        function openModal(modalId) {
            document.getElementById(modalId).style.display = 'flex';
        }
        
        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
        }
        
        // Close modal when clicking outside of it
        window.onclick = function(event) {
            if (event.target.classList.contains('modal')) {
                event.target.style.display = 'none';
            }
        }
        
        // Edit note function
        function editNote(noteId) {
            window.location.href = 'notes.php?edit=' + noteId;
        }
        
        // Delete note function
        function deleteNote(noteId) {
            if (confirm('Are you sure you want to delete this note?')) {
                window.location.href = 'notes.php?delete=' + noteId;
            }
        }
        
        // Open edit modal if edit parameter is present
        <?php if (isset($_GET['edit']) && $edit_note): ?>
        openModal('editNoteModal');
        <?php endif; ?>
    </script>
</body>
</html>