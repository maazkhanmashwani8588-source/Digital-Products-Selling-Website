<?php
/**
 * Admin Dashboard Statistics Endpoint
 * 
 * GET /backend/endpoints/admin/dashboard_stats.php
 * Returns: sales, users, orders, revenue stats for last 30 days
 */

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/../auth_middleware.php';

$user = require_auth();
if ($user['role'] !== 'admin') {
    jsonResponse(['error' => 'Unauthorized'], 403);
}

try {
    $thirtyDaysAgo = date('Y-m-d', strtotime('-30 days'));
    
    // Total revenue (last 30 days)
    $stmt = $pdo->prepare('
        SELECT SUM(total) as total_revenue, COUNT(*) as total_orders
        FROM orders 
        WHERE status = "paid" AND created_at >= ?
    ');
    $stmt->execute([$thirtyDaysAgo]);
    $revenue = $stmt->fetch();
    
    // Revenue breakdown by date (for chart)
    $stmt = $pdo->prepare('
        SELECT DATE(created_at) as date, SUM(total) as revenue, COUNT(*) as orders
        FROM orders 
        WHERE status = "paid" AND created_at >= ?
        GROUP BY DATE(created_at)
        ORDER BY date ASC
    ');
    $stmt->execute([$thirtyDaysAgo]);
    $revenueByDate = $stmt->fetchAll();
    
    // Total users
    $stmt = $pdo->prepare('SELECT COUNT(*) as count FROM users WHERE role = "user"');
    $stmt->execute();
    $totalUsers = $stmt->fetch()['count'];
    
    // New users (last 30 days)
    $stmt = $pdo->prepare('
        SELECT COUNT(*) as count FROM users 
        WHERE role = "user" AND created_at >= ?
    ');
    $stmt->execute([$thirtyDaysAgo]);
    $newUsers = $stmt->fetch()['count'];
    
    // Total orders
    $stmt = $pdo->prepare('SELECT COUNT(*) as count FROM orders');
    $stmt->execute();
    $totalOrders = $stmt->fetch()['count'];
    
    // Paid orders
    $stmt = $pdo->prepare('SELECT COUNT(*) as count FROM orders WHERE status = "paid"');
    $stmt->execute();
    $paidOrders = $stmt->fetch()['count'];
    
    // Pending orders
    $stmt = $pdo->prepare('SELECT COUNT(*) as count FROM orders WHERE status = "pending"');
    $stmt->execute();
    $pendingOrders = $stmt->fetch()['count'];
    
    // Total products
    $stmt = $pdo->prepare('SELECT COUNT(*) as count FROM products');
    $stmt->execute();
    $totalProducts = $stmt->fetch()['count'];
    
    // Top 5 products by sales
    $stmt = $pdo->prepare('
        SELECT p.id, p.title, COUNT(oi.id) as sales_count, SUM(oi.quantity * oi.price) as revenue
        FROM products p
        LEFT JOIN order_items oi ON oi.product_id = p.id
        LEFT JOIN orders o ON o.id = oi.order_id AND o.status = "paid"
        WHERE p.created_at >= ?
        GROUP BY p.id
        ORDER BY sales_count DESC
        LIMIT 5
    ');
    $stmt->execute([$thirtyDaysAgo]);
    $topProducts = $stmt->fetchAll();
    
    // Recent orders
    $stmt = $pdo->prepare('
        SELECT o.id, o.total, o.status, o.created_at, u.name, u.email
        FROM orders o
        LEFT JOIN users u ON u.id = o.user_id
        ORDER BY o.created_at DESC
        LIMIT 10
    ');
    $stmt->execute();
    $recentOrders = $stmt->fetchAll();
    
    jsonResponse([
        'success' => true,
        'revenue' => [
            'total' => (float)($revenue['total_revenue'] ?? 0),
            'orders_count' => (int)($revenue['total_orders'] ?? 0),
            'by_date' => $revenueByDate
        ],
        'users' => [
            'total' => (int)$totalUsers,
            'new_last_30' => (int)$newUsers
        ],
        'orders' => [
            'total' => (int)$totalOrders,
            'paid' => (int)$paidOrders,
            'pending' => (int)$pendingOrders
        ],
        'products' => [
            'total' => (int)$totalProducts,
            'top_5' => $topProducts
        ],
        'recent_orders' => $recentOrders
    ]);
    
} catch (Exception $e) {
    jsonResponse(['error' => $e->getMessage()], 500);
}
