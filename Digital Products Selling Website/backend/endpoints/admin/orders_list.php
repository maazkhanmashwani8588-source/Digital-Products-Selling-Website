<?php
/**
 * Admin Orders Management Endpoint
 * 
 * GET /backend/endpoints/admin/orders_list.php - List all orders
 * GET /backend/endpoints/admin/order_detail.php - Get single order
 * POST /backend/endpoints/admin/update_order_status.php - Update order status
 */

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/../auth_middleware.php';

$user = require_auth();
if ($user['role'] !== 'admin') {
    jsonResponse(['error' => 'Unauthorized'], 403);
}

try {
    $page = (int)($_GET['page'] ?? 1);
    $perPage = (int)($_GET['per_page'] ?? 20);
    $status = trim($_GET['status'] ?? '');
    $search = trim($_GET['search'] ?? '');
    $offset = ($page - 1) * $perPage;
    
    $where = '1=1';
    $params = [];
    
    if ($status) {
        $where .= ' AND o.status = ?';
        $params[] = $status;
    }
    
    if ($search) {
        $where .= ' AND (u.email LIKE ? OR u.name LIKE ? OR o.id LIKE ?)';
        $params[] = "%$search%";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }
    
    // Get total count
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM orders o LEFT JOIN users u ON u.id = o.user_id WHERE $where");
    $stmt->execute($params);
    $total = $stmt->fetch()['count'];
    
    // Get paginated orders
    $stmt = $pdo->prepare("
        SELECT o.id, o.total, o.status, o.created_at, 
               u.name, u.email, COUNT(oi.id) as item_count
        FROM orders o
        LEFT JOIN users u ON u.id = o.user_id
        LEFT JOIN order_items oi ON oi.order_id = o.id
        WHERE $where
        GROUP BY o.id
        ORDER BY o.created_at DESC
        LIMIT ? OFFSET ?
    ");
    
    $executeParams = array_merge($params, [$perPage, $offset]);
    $stmt->execute($executeParams);
    $orders = $stmt->fetchAll();
    
    jsonResponse([
        'success' => true,
        'total' => (int)$total,
        'page' => $page,
        'per_page' => $perPage,
        'orders' => $orders
    ]);
    
} catch (Exception $e) {
    jsonResponse(['error' => $e->getMessage()], 500);
}
