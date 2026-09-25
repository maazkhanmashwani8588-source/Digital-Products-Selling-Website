<?php
/**
 * Admin Order Detail Endpoint
 * 
 * GET /backend/endpoints/admin/order_detail.php?id=X
 */

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/../auth_middleware.php';

$user = require_auth();
if ($user['role'] !== 'admin') {
    jsonResponse(['error' => 'Unauthorized'], 403);
}

try {
    $orderId = (int)($_GET['id'] ?? 0);
    if ($orderId <= 0) {
        jsonResponse(['error' => 'Invalid order ID'], 400);
    }
    
    // Get order
    $stmt = $pdo->prepare('
        SELECT o.*, u.name, u.email
        FROM orders o
        LEFT JOIN users u ON u.id = o.user_id
        WHERE o.id = ?
    ');
    $stmt->execute([$orderId]);
    $order = $stmt->fetch();
    
    if (!$order) {
        jsonResponse(['error' => 'Order not found'], 404);
    }
    
    // Get order items
    $stmt = $pdo->prepare('
        SELECT oi.*, p.title, p.thumbnail
        FROM order_items oi
        LEFT JOIN products p ON p.id = oi.product_id
        WHERE oi.order_id = ?
    ');
    $stmt->execute([$orderId]);
    $items = $stmt->fetchAll();
    
    // Get payment info
    $stmt = $pdo->prepare('
        SELECT * FROM payments WHERE order_id = ?
    ');
    $stmt->execute([$orderId]);
    $payment = $stmt->fetch();
    
    jsonResponse([
        'success' => true,
        'order' => $order,
        'items' => $items,
        'payment' => $payment
    ]);
    
} catch (Exception $e) {
    jsonResponse(['error' => $e->getMessage()], 500);
}
