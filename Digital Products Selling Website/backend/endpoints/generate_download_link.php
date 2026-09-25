<?php
/**
 * Generate Download Link Endpoint (Updated for Product Files Storage)
 * 
 * GET /backend/endpoints/generate_download_link.php?file_id=X&order_id=Y
 * Alternative (legacy): ?product_id=X&order_id=Y (uses file_path from products table)
 * Requires authentication
 * 
 * Returns signed download URL (valid 1 hour)
 */

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../services/Storage.php';
require_once __DIR__ . '/auth_middleware.php';

$user = require_auth();
$fileId = $_GET['file_id'] ?? null;
$orderId = $_GET['order_id'] ?? null;
$productId = $_GET['product_id'] ?? null; // Legacy support

if (!$orderId) {
    jsonResponse(['error' => 'missing_order_id'], 400);
}

// Verify order belongs to user and is paid
$stmt = $pdo->prepare('SELECT status FROM orders WHERE id = ? AND user_id = ? LIMIT 1');
$stmt->execute([$orderId, $user['id']]);
$status = $stmt->fetchColumn();
if (!$status || $status !== 'paid') {
    jsonResponse(['error' => 'order_not_paid_or_found'], 403);
}

// NEW MODE: Generate via product_files table
if ($fileId) {
    $stmt = $pdo->prepare('
        SELECT pf.* FROM product_files pf
        JOIN products p ON p.id = pf.product_id
        JOIN order_items oi ON oi.product_id = p.id
        JOIN orders o ON o.id = oi.order_id
        WHERE pf.id = ? AND o.id = ? AND pf.is_active = 1
        LIMIT 1
    ');
    $stmt->execute([$fileId, $orderId]);
    $file = $stmt->fetch();
    
    if (!$file) {
        jsonResponse(['error' => 'file_not_found'], 404);
    }
    
    try {
        $storage = new Storage();
        $downloadUrl = $storage->getSignedUrl($file['storage_key'], 3600);
        
        // Update access tracking
        $stmt = $pdo->prepare('
            UPDATE product_files 
            SET accessed_count = accessed_count + 1, last_accessed = NOW()
            WHERE id = ?
        ');
        $stmt->execute([$fileId]);
        
        jsonResponse([
            'success' => true,
            'download_url' => $downloadUrl,
            'expires_in_seconds' => 3600,
            'file_name' => $file['original_filename']
        ]);
    } catch (Exception $e) {
        jsonResponse(['error' => 'Failed to generate download link: ' . $e->getMessage()], 500);
    }

// LEGACY MODE: Use file_path from products table (backward compatibility)
} elseif ($productId) {
    // Verify order contains this product
    $stmt = $pdo->prepare('
        SELECT COUNT(*) FROM order_items 
        WHERE order_id = ? AND product_id = ?
    ');
    $stmt->execute([$orderId, $productId]);
    if ($stmt->fetchColumn() == 0) {
        jsonResponse(['error' => 'product_not_in_order'], 403);
    }
    
    $stmt = $pdo->prepare('SELECT file_path FROM products WHERE id = ? LIMIT 1');
    $stmt->execute([$productId]);
    $filePath = $stmt->fetchColumn();
    
    if (!$filePath) {
        jsonResponse(['error' => 'product_file_not_found'], 404);
    }
    
    $expires = time() + 3600; // 1 hour
    $data = $filePath . '|' . $orderId . '|' . $expires;
    $sig = hash_hmac('sha256', $data, $config['app_secret']);
    $link = (getenv('BASE_URL') ?: 'http://localhost') . '/backend/endpoints/download.php?file=' . urlencode($filePath) . '&order=' . urlencode($orderId) . '&expires=' . $expires . '&sig=' . $sig;
    
    jsonResponse([
        'download_url' => $link,
        'expires_in_seconds' => 3600
    ]);

} else {
    jsonResponse(['error' => 'missing_file_id_or_product_id'], 400);
}
