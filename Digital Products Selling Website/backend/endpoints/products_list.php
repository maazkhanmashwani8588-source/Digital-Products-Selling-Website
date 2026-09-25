<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../db.php';

header('Content-Type: application/json');

try {

    $page = max(1, (int)($_GET['page'] ?? 1));
    $perPage = 12;
    $offset = ($page - 1) * $perPage;

    $q = trim($_GET['q'] ?? '');
    $category = (int)($_GET['category'] ?? 0);

    $where = "WHERE 1=1";
    $params = [];

    if ($q !== '') {
        $where .= " AND (title LIKE ? OR description LIKE ?)";
        $params[] = "%$q%";
        $params[] = "%$q%";
    }

    if ($category > 0) {
        $where .= " AND category_id = ?";
        $params[] = $category;
    }

    // total
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM products $where");
    $stmt->execute($params);
    $total = $stmt->fetchColumn();

    // products
    $stmt = $pdo->prepare("
        SELECT *
        FROM products
        $where
        ORDER BY created_at DESC
        LIMIT $perPage OFFSET $offset
    ");

    $stmt->execute($params);
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'products' => $products,
        'total' => (int)$total,
        'page' => $page,
        'per_page' => $perPage
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}