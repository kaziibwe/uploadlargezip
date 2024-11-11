






<?php
class FileUploadHandler {
    private $uploadDir = '/home/kaziibwe-alfred/upload/zip';
    private $extractDir = '/home/kaziibwe-alfred/upload/extract';
    private $maxFileSize = 5368709120; // 5GB in bytes
    private $minFileSize = 1024;        // 1KB minimum
    private $db;
    
    public function __construct() {
        // Database connection
        $this->connectDatabase();
        
        // Create directories if they don't exist
        if (!file_exists($this->uploadDir)) {
            if (!mkdir($this->uploadDir, 0755, true)) {
                throw new Exception('Failed to create upload directory');
            }
        }
        
        if (!file_exists($this->extractDir)) {
            if (!mkdir($this->extractDir, 0755, true)) {
                throw new Exception('Failed to create extraction directory');
            }
        }
        
        // Check directory permissions
        if (!is_writable($this->uploadDir)) {
            throw new Exception('Upload directory is not writable');
        }
        
        if (!is_writable($this->extractDir)) {
            throw new Exception('Extract directory is not writable');
        }
    }
    
    private function connectDatabase() {
        try {
            $host = 'localhost';
            $dbname = 'uploadzip';
            $username = 'alfred';
            $password = 'Ka075.';
            
            $this->db = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
            $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch(PDOException $e) {
            throw new Exception('Database connection failed: ' . $e->getMessage());
        }
    }
    
    private function saveToDatabase($fileName, $fileUrl) {
        try {
            $stmt = $this->db->prepare("INSERT INTO files (name, url, created_at) VALUES (?, ?, NOW())");
            return $stmt->execute([$fileName, $fileUrl]);
        } catch(PDOException $e) {
            throw new Exception('Failed to save to database: ' . $e->getMessage());
        }
    }
    
    public function handleUpload($fileInputName) {
        try {
            // Validate file upload
            if (!isset($_FILES[$fileInputName])) {
                throw new Exception('No file uploaded');
            }
            
            $file = $_FILES[$fileInputName];
            
            // Check for upload errors
            if ($file['error'] !== UPLOAD_ERR_OK) {
                throw new Exception('Upload error: ' . $this->getUploadErrorMessage($file['error']));
            }
            
            // Validate file size
            if ($file['size'] > $this->maxFileSize) {
                throw new Exception('File is too large. Maximum size is 5GB');
            }
            
            if ($file['size'] < $this->minFileSize) {
                throw new Exception('File is too small. Minimum size is 1KB');
            }
            
            // Validate file type
            if ($file['type'] !== 'application/zip' && 
                pathinfo($file['name'], PATHINFO_EXTENSION) !== 'zip') {
                throw new Exception('Only ZIP files are allowed');
            }
            
            // Generate unique filename with timestamp
            $timestamp = date('Y-m-d_H-i-s');
            $zipFileName = uniqid() . '_' . $timestamp . '_' . $file['name'];
            $zipFilePath = $this->uploadDir . '/' . $zipFileName;
            
            // Move uploaded file
            if (!move_uploaded_file($file['tmp_name'], $zipFilePath)) {
                throw new Exception('Failed to move uploaded file');
            }
            
            // Create unique extraction directory using original file name
            $extractFolderName = pathinfo($file['name'], PATHINFO_FILENAME) . '_' . $timestamp;
            $extractPath = $this->extractDir . '/' . $extractFolderName;
            
            if (!mkdir($extractPath, 0755, true)) {
                throw new Exception('Failed to create extraction directory');
            }
            
            // Extract zip file
            $zip = new ZipArchive();
            if ($zip->open($zipFilePath) !== true) {
                throw new Exception('Failed to open zip file');
            }
            
            // Check zip file contents before extracting
            $totalSize = 0;
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $stat = $zip->statIndex($i);
                $totalSize += $stat['size'];
                
                // Security check for path traversal
                if (strpos($stat['name'], '..') !== false) {
                    $zip->close();
                    throw new Exception('Invalid file path in ZIP');
                }
            }
            
            // Extract files
            if (!$zip->extractTo($extractPath)) {
                $zip->close();
                throw new Exception('Failed to extract zip file');
            }
            
            $zip->close();
            
            // Delete the zip file after successful extraction
            if (!unlink($zipFilePath)) {
                throw new Exception('Failed to delete zip file');
            }
            
            // Generate public URL (adjust according to your server setup)
            $extractedUrl = '/upload/extract/' . $extractFolderName;
            
            // Save to database
            $this->saveToDatabase($file['name'], $extractedUrl);
            
            return [
                'success' => true,
                'message' => 'File uploaded and extracted successfully',
                'extracted_path' => $extractPath,
                'url' => $extractedUrl,
                'original_name' => $file['name'],
                'total_size' => $this->formatSize($totalSize),
                'files' => $this->getDirectoryStructure($extractPath)
            ];
            
        } catch (Exception $e) {
            // Clean up on failure
            if (isset($zipFilePath) && file_exists($zipFilePath)) {
                unlink($zipFilePath);
            }
            if (isset($extractPath) && file_exists($extractPath)) {
                $this->removeDirectory($extractPath);
            }
            
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
    
    // ... (rest of the existing methods remain the same)



    private function getDirectoryStructure($path) {
        $structure = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($path, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );
        
        foreach ($iterator as $item) {
            $structure[] = [
                'path' => str_replace($path . '/', '', $item->getPathname()),
                'type' => $item->isDir() ? 'directory' : 'file',
                'size' => $item->isFile() ? $this->formatSize($item->getSize()) : null
            ];
        }
        
        return $structure;
    }
    
    private function formatSize($bytes) {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= pow(1024, $pow);
        
        return round($bytes, 2) . ' ' . $units[$pow];
    }
    
    private function removeDirectory($dir) {
        if (!is_dir($dir)) {
            return;
        }
        
        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            is_dir($path) ? $this->removeDirectory($path) : unlink($path);
        }
        
        return rmdir($dir);
    }
    
    private function getUploadErrorMessage($code) {
        $errors = [
            UPLOAD_ERR_INI_SIZE => 'File exceeds upload_max_filesize directive in php.ini',
            UPLOAD_ERR_FORM_SIZE => 'File exceeds MAX_FILE_SIZE directive specified in the HTML form',
            UPLOAD_ERR_PARTIAL => 'File was only partially uploaded',
            UPLOAD_ERR_NO_FILE => 'No file was uploaded',
            UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder',
            UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
            UPLOAD_ERR_EXTENSION => 'File upload stopped by extension'
        ];
        
        return isset($errors[$code]) ? $errors[$code] : 'Unknown upload error';
    }
}