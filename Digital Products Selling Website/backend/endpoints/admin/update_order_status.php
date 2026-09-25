<?php
/**
 * Admin Update Order Status Endpoint
 * 
 * POST /backend/endpoints/admin/update_order_status.php
 * Body: { "id": X, "status": "paid|pending|failed" }
 */

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/../auth_middleware.php';

$user = require_auth();
if ($user['role'] !== 'admin') {
    jsonResponse(['error' => 'Unauthorized'], 403);
}

try {
    $input = json_decode(file_get_contents('php://input'), true);
    $orderId = (int)($input['id'] ?? 0);
    $status = trim($input['status'] ?? '');
    
    if ($orderId <= 0 || !in_array($status, ['pending', 'paid', 'failed'])) {
        jsonResponse(['error' => 'Invalid order ID or status'], 400);
    }
    
    // Verify order exists
    $stmt = $pdo->prepare('SELECT id FROM orders WHERE id = ?');
    $stmt->execute([$orderId]);
    if (!$stmt->fetch()) {
        jsonResponse(['error' => 'Order not found'], 404);
    }
    
    // Update order status
    $stmt = $pdo->prepare('UPDATE orders SET status = ? WHERE id = ?');
    $stmt->execute([$status, $orderId]);
    
    // If marking as paid and wasn't before, create downloads
    if ($status === 'paid') {
        $stmt = $pdo->prepare('SELECT status FROM orders WHERE id = ?');
        $stmt->execute([$orderId]);
        $oldStatus = $stmt->fetchColumn();
        
        if ($oldStatus !== 'paid') {
            // Create downloads for items
            $stmt = $pdo->prepare('
                SELECT DISTINCT product_id FROM order_items WHERE order_id = ?
            ');
            $stmt->execute([$orderId]);
            $items = $stmt->fetchAll();
            
            $stmtDl = $pdo->prepare('
                INSERT IGNORE INTO downloads (order_id, product_id, user_id, file_path, accessed_at) 
                VALUES (?, ?, ?, NULL, NOW())
            ');
            
            $stmt2 = $pdo->prepare('SELECT user_id FROM orders WHERE id = ?');
            $stmt2->execute([$orderId]);
            $userId = $stmt2->fetchColumn();
            
            foreach ($items as $item) {
                $stmtDl->execute([$orderId, $item['product_id'], $userId]);
            }
        }
    }
    
    jsonResponse(['success' => true, 'message' => 'Order status updated']);
    
} catch (Exception $e) {
    jsonResponse(['error' => $e->getMessage()], 500);
}
