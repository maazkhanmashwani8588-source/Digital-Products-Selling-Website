<?php
/**
 * Get Product Files Endpoint
 * 
 * GET /backend/endpoints/product_files_list.php?product_id=X
 * Requires admin authentication
 * 
 * Returns array of files for a product
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../auth_middleware.php';

$user = require_auth();
if ($user['role'] !== 'admin') {
    jsonResponse(['error' => 'Unauthorized'], 403);
}

$productId = (int)($_GET['product_id'] ?? 0);
if ($productId <= 0) {
    jsonResponse(['error' => 'Invalid product_id'], 400);
}

$stmt = $pdo->prepare('
    SELECT 
        id, 
        storage_key, 
        original_filename, 
        mime_type, 
        file_size, 
        file_hash, 
        storage_driver,
        uploaded_at, 
        accessed_count, 
        is_active 
    FROM product_files 
    WHERE product_id = ? 
    ORDER BY uploaded_at DESC
');
$stmt->execute([$productId]);
$files = $stmt->fetchAll();

jsonResponse([
    'success' => true,
    'files' => $files
]);
