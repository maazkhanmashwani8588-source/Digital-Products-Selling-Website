<?php
/**
 * Admin Customer Detail Endpoint
 * 
 * GET /backend/endpoints/admin/customer_detail.php?id=X
 */

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/../auth_middleware.php';

$user = require_auth();
if ($user['role'] !== 'admin') {
    jsonResponse(['error' => 'Unauthorized'], 403);
}

try {
    $customerId = (int)($_GET['id'] ?? 0);
    if ($customerId <= 0) {
        jsonResponse(['error' => 'Invalid customer ID'], 400);
    }
    
    // Get customer info
    $stmt = $pdo->prepare('
        SELECT * FROM users WHERE id = ? AND role = "user"
    ');
    $stmt->execute([$customerId]);
    $customer = $stmt->fetch();
    
    if (!$customer) {
        jsonResponse(['error' => 'Customer not found'], 404);
    }
    
    // Get customer orders
    $stmt = $pdo->prepare('
        SELECT id, total, status, created_at FROM orders WHERE user_id = ? ORDER BY created_at DESC
    ');
    $stmt->execute([$customerId]);
    $orders = $stmt->fetchAll();
    
    // Get customer downloads
    $stmt = $pdo->prepare('
        SELECT d.id, d.accessed_at, p.title, p.thumbnail
        FROM downloads d
        LEFT JOIN products p ON p.id = d.product_id
        WHERE d.user_id = ?
        ORDER BY d.accessed_at DESC
        LIMIT 20
    ');
    $stmt->execute([$customerId]);
    $downloads = $stmt->fetchAll();
    
    jsonResponse([
        'success' => true,
        'customer' => $customer,
        'orders' => $orders,
        'downloads' => $downloads
    ]);
    
} catch (Exception $e) {
    jsonResponse(['error' => $e->getMessage()], 500);
}
