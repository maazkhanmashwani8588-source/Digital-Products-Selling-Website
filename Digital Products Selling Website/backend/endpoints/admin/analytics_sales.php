<?php
/**
 * Admin Analytics Endpoints
 * 
 * GET /backend/endpoints/admin/analytics_sales.php - Sales data for charts
 * GET /backend/endpoints/admin/analytics_products.php - Product analytics
 */

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/../auth_middleware.php';

$user = require_auth();
if ($user['role'] !== 'admin') {
    jsonResponse(['error' => 'Unauthorized'], 403);
}

try {
    $days = (int)($_GET['days'] ?? 30);
    $startDate = date('Y-m-d', strtotime("-{$days} days"));
    
    // Sales by date
    $stmt = $pdo->prepare('
        SELECT DATE(created_at) as date, COUNT(*) as orders, SUM(total) as revenue
        FROM orders 
        WHERE status = "paid" AND created_at >= ?
        GROUP BY DATE(created_at)
        ORDER BY date ASC
    ');
    $stmt->execute([$startDate]);
    $salesByDate = $stmt->fetchAll();
    
    // Top 10 products
    $stmt = $pdo->prepare('
        SELECT p.id, p.title, COUNT(oi.id) as sales, SUM(oi.quantity * oi.price) as revenue
        FROM products p
        LEFT JOIN order_items oi ON oi.product_id = p.id
        LEFT JOIN orders o ON o.id = oi.order_id AND o.status = "paid"
        WHERE o.created_at >= ? OR o.id IS NULL
        GROUP BY p.id
        ORDER BY sales DESC
        LIMIT 10
    ');
    $stmt->execute([$startDate]);
    $topProducts = $stmt->fetchAll();
    
    // Category breakdown
    $stmt = $pdo->prepare('
        SELECT c.name, COUNT(DISTINCT oi.order_id) as orders, SUM(oi.quantity * oi.price) as revenue
        FROM categories c
        LEFT JOIN products p ON p.category_id = c.id
        LEFT JOIN order_items oi ON oi.product_id = p.id
        LEFT JOIN orders o ON o.id = oi.order_id AND o.status = "paid"
        WHERE o.created_at >= ? OR o.id IS NULL
        GROUP BY c.id
        ORDER BY revenue DESC
    ');
    $stmt->execute([$startDate]);
    $categoryBreakdown = $stmt->fetchAll();
    
    jsonResponse([
        'success' => true,
        'period_days' => $days,
        'sales_by_date' => $salesByDate,
        'top_products' => $topProducts,
        'category_breakdown' => $categoryBreakdown
    ]);
    
} catch (Exception $e) {
    jsonResponse(['error' => $e->getMessage()], 500);
}
