<?php
// Set appropriate error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Initialize variables
$uploadResult = null;
$files = [];

// Database connection

try {
    $db = new PDO("mysql:host=localhost;dbname=uploadzip", "alfred", "Ka075.");
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Fetch existing files
    $stmt = $db->query("SELECT * FROM files ORDER BY created_at DESC");
    $files = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    $dbError = 'Database connection failed: ' . $e->getMessage();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once 'FileUploadHandler.php';
    
    try {
        $handler = new FileUploadHandler();
        $uploadResult = $handler->handleUpload('zip_file');
        
        // Refresh the page to show the new file in the list
        if ($uploadResult['success']) {
            header('Location: ' . $_SERVER['PHP_SELF']);
            exit;
        }
    } catch (Exception $e) {
        $uploadResult = [
            'success' => false,
            'message' => $e->getMessage()
        ];
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>File Upload and Management</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 800px;
            margin: 20px auto;
            padding: 0 20px;
        }
        .result {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 4px;
        }
        .success {
            background-color: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
        }
        .error {
            background-color: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
        }
        .link {
            word-break: break-all;
            margin: 10px 0;
        }
        form {
            margin-top: 20px;
            padding: 20px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        input[type="file"] {
            margin-bottom: 10px;
        }
        input[type="submit"] {
            background-color: #007bff;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }
        input[type="submit"]:hover {
            background-color: #0056b3;
        }
        .files-list {
            margin-top: 30px;
        }
        .files-list table {
            width: 100%;
            border-collapse: collapse;
        }
        .files-list th, .files-list td {
            padding: 10px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        .files-list th {
            background-color: #f8f9fa;
        }
    </style>
</head>
<body>
    <h1>File Upload and Management</h1>

    <?php if (isset($dbError)): ?>
        <div class="result error">
            <p><?php echo htmlspecialchars($dbError); ?></p>
        </div>
    <?php endif; ?>

    <?php if ($uploadResult): ?>
        <div class="result <?php echo $uploadResult['success'] ? 'success' : 'error'; ?>">
            <?php if ($uploadResult['success']): ?>
                <h3>Upload Successful!</h3>
                <p><?php echo htmlspecialchars($uploadResult['message']); ?></p>
                <p><strong>Original File:</strong> <?php echo htmlspecialchars($uploadResult['original_name']); ?></p>
                <p><strong>Total Size:</strong> <?php echo htmlspecialchars($uploadResult['total_size']); ?></p>
                <p><strong>URL:</strong></p>
                <div class="link"><?php echo htmlspecialchars($uploadResult['url']); ?></div>
            <?php else: ?>
                <h3>Upload Failed</h3>
                <p><?php echo htmlspecialchars($uploadResult['message']); ?></p>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <form action="" method="post" enctype="multipart/form-data">
        <h2>Upload ZIP File</h2>
        <p>Maximum file size: 5GB</p>
        <input type="file" name="zip_file" accept=".zip" required>
        <br>
        <input type="submit" value="Upload and Extract">
    </form>

    <div class="files-list">
        <h2>Uploaded Files</h2>
        <?php if (!empty($files)): ?>
            <table>
                <thead>
                    <tr>
                        <th>File Name</th>
                        <th>Upload Date</th>
                        <th>URL</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($files as $file): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($file['name']); ?></td>
                            <td><?php echo htmlspecialchars($file['created_at']); ?></td>
                            <td>
                                <a href="<?php echo htmlspecialchars($file['url']); ?>" target="_blank">
                                    View Files
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p>No files uploaded yet.</p>
        <?php endif; ?>
    </div>
</body>
</html>