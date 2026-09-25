<?php
/**
 * Admin Customers Management Endpoint
 * 
 * GET /backend/endpoints/admin/customers_list.php - List all customers
 * GET /backend/endpoints/admin/customer_detail.php - Get single customer
 * POST /backend/endpoints/admin/customer_block.php - Block/unblock customer
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
    $search = trim($_GET['search'] ?? '');
    $offset = ($page - 1) * $perPage;
    
    $where = 'role = "user"';
    $params = [];
    
    if ($search) {
        $where .= ' AND (email LIKE ? OR name LIKE ?)';
        $params[] = "%$search%";
        $params[] = "%$search%";
    }
    
    // Get total count
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM users WHERE $where");
    $stmt->execute($params);
    $total = $stmt->fetch()['count'];
    
    // Get paginated customers
    $stmt = $pdo->prepare("
        SELECT u.id, u.name, u.email, u.is_verified, u.created_at,
               COUNT(o.id) as order_count, SUM(o.total) as total_spent
        FROM users u
        LEFT JOIN orders o ON o.user_id = u.id AND o.status = 'paid'
        WHERE $where
        GROUP BY u.id
        ORDER BY u.created_at DESC
        LIMIT ? OFFSET ?
    ");
    
    $executeParams = array_merge($params, [$perPage, $offset]);
    $stmt->execute($executeParams);
    $customers = $stmt->fetchAll();
    
    jsonResponse([
        'success' => true,
        'total' => (int)$total,
        'page' => $page,
        'per_page' => $perPage,
        'customers' => $customers
    ]);
    
} catch (Exception $e) {
    jsonResponse(['error' => $e->getMessage()], 500);
}
