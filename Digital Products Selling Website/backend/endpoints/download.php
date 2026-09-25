<?php
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../services/Storage.php';

/**
 * Secure Download Endpoint
 * 
 * Supports two modes:
 * 1. New: ?key=storage_key&expires=ts&sig=hmac (for local storage signed URLs)
 * 2. Legacy: ?file=path&order=ID&expires=ts&sig=hmac (for backward compatibility)
 */

// Detect request mode
$storageKey = $_GET['key'] ?? null;
$legacyFile = $_GET['file'] ?? null;

if ($storageKey) {
    // NEW MODE: Signed storage URL
    $expires = $_GET['expires'] ?? null;
    $sig = $_GET['sig'] ?? null;
    
    if (!$storageKey || !$expires || !$sig) {
        http_response_code(400);
        echo 'Invalid download link';
        exit;
    }
    
    if (time() > (int)$expires) {
        http_response_code(403);
        echo 'Download link expired';
        exit;
    }
    
    // Verify signature
    $data = $storageKey . '|' . $expires;
    $expected = hash_hmac('sha256', $data, getenv('APP_SECRET') ?: 'change_this_secret');
    if (!hash_equals($expected, $sig)) {
        http_response_code(403);
        echo 'Invalid signature';
        exit;
    }
    
    // Verify user has access to at least one order containing this file
    // (File is linked to product_files, which is linked to products, which is in orders)
    $stmt = $pdo->prepare('
        SELECT u.id FROM product_files pf
        JOIN order_items oi ON oi.product_id = pf.product_id
        JOIN orders o ON o.id = oi.order_id
        JOIN users u ON u.id = o.user_id
        WHERE pf.storage_key = ? AND o.status = "paid" AND u.id = ?
        LIMIT 1
    ');
    $stmt->execute([$storageKey, $_SESSION['user_id'] ?? null]);
    
    if (!$stmt->fetch()) {
        http_response_code(403);
        echo 'Unauthorized';
        exit;
    }
    
    // Serve file
    try {
        $storage = new Storage();
        
        if (getenv('STORAGE_DRIVER') === 's3') {
            // For S3, redirect to signed URL
            $url = $storage->getSignedUrl($storageKey, 3600);
            header('Location: ' . $url);
            exit;
        } else {
            // For local storage, stream file
            $stream = $storage->getFileStream($storageKey);
            if (!$stream) {
                http_response_code(404);
                echo 'File not found';
                exit;
            }
            
            // Get file size
            $size = $storage->getFileSize($storageKey);
            
            // Get original filename from product_files
            $stmt = $pdo->prepare('SELECT original_filename FROM product_files WHERE storage_key = ? LIMIT 1');
            $stmt->execute([$storageKey]);
            $row = $stmt->fetch();
            $filename = $row['original_filename'] ?? 'download';
            
            // Record download
            $stmt = $pdo->prepare('
                INSERT INTO downloads (user_id, file_path, accessed_at) 
                VALUES (?, ?, NOW())
            ');
            $stmt->execute([$_SESSION['user_id'] ?? null, $storageKey]);
            
            // Update access count
            $stmt = $pdo->prepare('
                UPDATE product_files 
                SET accessed_count = accessed_count + 1, last_accessed = NOW()
                WHERE storage_key = ?
            ');
            $stmt->execute([$storageKey]);
            
            // Stream file
            header('Content-Description: File Transfer');
            header('Content-Type: application/octet-stream');
            header('Content-Disposition: attachment; filename="' . basename($filename) . '"');
            header('Expires: 0');
            header('Cache-Control: must-revalidate');
            header('Pragma: public');
            if ($size) {
                header('Content-Length: ' . $size);
            }
            
            fpassthru($stream);
            fclose($stream);
            exit;
        }
    } catch (Exception $e) {
        http_response_code(500);
        echo 'Download failed: ' . $e->getMessage();
        exit;
    }

} else if ($legacyFile) {
    // LEGACY MODE: Old HMAC-signed download (backward compatibility)
    $orderId = $_GET['order'] ?? null;
    $expires = $_GET['expires'] ?? null;
    $sig = $_GET['sig'] ?? null;
    
    if (!$legacyFile || !$orderId || !$expires || !$sig) {
        http_response_code(400);
        echo 'Invalid link';
        exit;
    }
    
    if (time() > (int)$expires) {
        http_response_code(403);
        echo 'Link expired';
        exit;
    }
    
    $data = $legacyFile . '|' . $orderId . '|' . $expires;
    $expected = hash_hmac('sha256', $data, getenv('APP_SECRET') ?: 'change_this_secret');
    if (!hash_equals($expected, $sig)) {
        http_response_code(403);
        echo 'Invalid signature';
        exit;
    }
    
    // Verify order is paid
    $stmt = $pdo->prepare('SELECT status FROM orders WHERE id = ? LIMIT 1');
    $stmt->execute([$orderId]);
    $status = $stmt->fetchColumn();
    if ($status !== 'paid') {
        http_response_code(403);
        echo 'Order not paid';
        exit;
    }
    
    $fullPath = realpath(__DIR__ . '/..' . $legacyFile);
    if (!$fullPath || !file_exists($fullPath)) {
        http_response_code(404);
        echo 'File not found';
        exit;
    }
    
    // Record download
    $stmt = $pdo->prepare('INSERT INTO downloads (order_id, user_id, file_path) VALUES (?,?,?)');
    $stmt->execute([$orderId, $_SESSION['user_id'] ?? null, $legacyFile]);
    
    header('Content-Description: File Transfer');
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="' . basename($legacyFile) . '"');
    header('Expires: 0');
    header('Cache-Control: must-revalidate');
    header('Pragma: public');
    header('Content-Length: ' . filesize($fullPath));
    readfile($fullPath);
    exit;

} else {
    http_response_code(400);
    echo 'Invalid download request';
    exit;
}
