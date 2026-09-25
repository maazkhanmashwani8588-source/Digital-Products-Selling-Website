<?php
/**
 * Admin Product File Upload Endpoint
 * 
 * POST /backend/endpoints/admin_upload_product_file.php
 * Requires admin authentication
 * 
 * Request:
 * - POST multipart/form-data with 'file' field
 * - Query params: product_id, version (optional)
 * 
 * Response:
 * - JSON with file metadata (key, size, mime, hash)
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../services/Storage.php';
require_once __DIR__ . '/../auth_middleware.php';

// Verify admin auth
$user = require_auth();
if ($user['role'] !== 'admin') {
    jsonResponse(['error' => 'Unauthorized'], 403);
}

// Get product ID
$productId = (int)($_GET['product_id'] ?? 0);
if ($productId <= 0) {
    jsonResponse(['error' => 'Invalid product_id'], 400);
}

// Verify product exists and belongs to valid admin context
$stmt = $pdo->prepare('SELECT id FROM products WHERE id = ? LIMIT 1');
$stmt->execute([$productId]);
if (!$stmt->fetch()) {
    jsonResponse(['error' => 'Product not found'], 404);
}

// Check for file
if (!isset($_FILES['file'])) {
    jsonResponse(['error' => 'No file provided'], 400);
}

$file = $_FILES['file'];

// Check for upload errors
if ($file['error'] !== UPLOAD_ERR_OK) {
    $errors = [
        UPLOAD_ERR_INI_SIZE => 'File exceeds upload_max_filesize',
        UPLOAD_ERR_FORM_SIZE => 'File exceeds MAX_FILE_SIZE',
        UPLOAD_ERR_PARTIAL => 'File partially uploaded',
        UPLOAD_ERR_NO_FILE => 'No file uploaded',
        UPLOAD_ERR_NO_TMP_DIR => 'Missing temp directory',
        UPLOAD_ERR_CANT_WRITE => 'Cannot write file',
        UPLOAD_ERR_EXTENSION => 'Upload blocked by extension'
    ];
    jsonResponse(['error' => $errors[$file['error']] ?? 'Upload failed'], 400);
}

// Upload via Storage service
try {
    $storage = new Storage();
    
    $uploadResult = $storage->upload($file, [
        'product_id' => $productId,
        'custom_name' => $file['name']
    ]);
    
    if (!$uploadResult['success']) {
        jsonResponse(['error' => $uploadResult['error']], 400);
    }
    
    // Store file metadata in database
    $stmt = $pdo->prepare('
        INSERT INTO product_files (
            product_id, 
            storage_key, 
            original_filename, 
            mime_type, 
            file_size, 
            file_hash, 
            storage_driver, 
            s3_bucket, 
            s3_region
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
    ');
    
    $storageDriver = getenv('STORAGE_DRIVER') ?: 'local';
    $bucket = $storageDriver === 's3' ? getenv('AWS_S3_BUCKET') : null;
    $region = $storageDriver === 's3' ? getenv('AWS_S3_REGION') : null;
    
    $stmt->execute([
        $productId,
        $uploadResult['key'],
        $uploadResult['original_name'],
        $uploadResult['mime'],
        $uploadResult['size'],
        $uploadResult['hash'],
        $storageDriver,
        $bucket,
        $region
    ]);
    
    $fileId = $pdo->lastInsertId();
    
    // Log success
    @file_put_contents(__DIR__ . '/../logs/storage.log',
        date('c') . " File stored in DB: product={$productId}, file_id={$fileId}, key={$uploadResult['key']}\n",
        FILE_APPEND);
    
    jsonResponse([
        'success' => true,
        'file_id' => $fileId,
        'key' => $uploadResult['key'],
        'size' => $uploadResult['size'],
        'mime' => $uploadResult['mime'],
        'hash' => $uploadResult['hash'],
        'original_name' => $uploadResult['original_name']
    ]);
    
} catch (Exception $e) {
    @file_put_contents(__DIR__ . '/../logs/storage.log',
        date('c') . " Upload endpoint error: " . $e->getMessage() . "\n",
        FILE_APPEND);
    jsonResponse(['error' => 'Upload failed: ' . $e->getMessage()], 500);
}
