<?php
/**
 * Admin Delete Product File Endpoint
 * 
 * DELETE /backend/endpoints/admin_delete_product_file.php?file_id=X
 * Requires admin authentication
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../services/Storage.php';
require_once __DIR__ . '/../auth_middleware.php';

$user = require_auth();
if ($user['role'] !== 'admin') {
    jsonResponse(['error' => 'Unauthorized'], 403);
}

$fileId = (int)($_GET['file_id'] ?? 0);
if ($fileId <= 0) {
    jsonResponse(['error' => 'Invalid file_id'], 400);
}

// Get file info
$stmt = $pdo->prepare('SELECT storage_key, product_id FROM product_files WHERE id = ?');
$stmt->execute([$fileId]);
$file = $stmt->fetch();

if (!$file) {
    jsonResponse(['error' => 'File not found'], 404);
}

try {
    // Delete from storage backend
    $storage = new Storage();
    $storage->delete($file['storage_key']);
    
    // Mark as inactive in database (soft delete for audit trail)
    $stmt = $pdo->prepare('UPDATE product_files SET is_active = 0 WHERE id = ?');
    $stmt->execute([$fileId]);
    
    @file_put_contents(__DIR__ . '/../logs/storage.log',
        date('c') . " File deleted: file_id={$fileId}, key={$file['storage_key']}\n",
        FILE_APPEND);
    
    jsonResponse(['success' => true, 'message' => 'File deleted']);
    
} catch (Exception $e) {
    @file_put_contents(__DIR__ . '/../logs/storage.log',
        date('c') . " Delete error: " . $e->getMessage() . "\n",
        FILE_APPEND);
    jsonResponse(['error' => 'Delete failed: ' . $e->getMessage()], 500);
}
