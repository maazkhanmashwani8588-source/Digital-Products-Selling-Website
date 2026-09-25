<?php

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/../auth_middleware.php';

header('Content-Type: application/json');

function response($data, $code = 200) {
    http_response_code($code);
    echo json_encode($data);
    exit;
}

$user = require_auth();
if (!$user || $user['role'] !== 'admin') {
    response(['success' => false, 'error' => 'Unauthorized'], 403);
}

$method = $_SERVER['REQUEST_METHOD'];

try {

    /* ===================== GET PRODUCTS ===================== */
    if ($method === 'GET') {

        $page = max(1, (int)($_GET['page'] ?? 1));
        $perPage = max(1, (int)($_GET['per_page'] ?? 10));
        $search = trim($_GET['search'] ?? '');
        $offset = ($page - 1) * $perPage;

        $where = "1=1";
        $params = [];

        if ($search !== '') {
            $where .= " AND (title LIKE ? OR sku LIKE ? OR description LIKE ?)";
            $like = "%$search%";
            $params = [$like, $like, $like];
        }

        // TOTAL
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM products WHERE $where");
        $stmt->execute($params);
        $total = (int)$stmt->fetchColumn();

        // DATA (IMPORTANT FIX: COALESCE prevents NULL crashes)
        $stmt = $pdo->prepare("
            SELECT 
                p.*,
                COALESCE(c.name, '') AS category_name,
                COUNT(oi.id) AS sales_count
            FROM products p
            LEFT JOIN categories c ON c.id = p.category_id
            LEFT JOIN order_items oi ON oi.product_id = p.id
            WHERE $where
            GROUP BY p.id
            ORDER BY p.created_at DESC
            LIMIT $perPage OFFSET $offset
        ");

        $stmt->execute($params);
        $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

        response([
            'success' => true,
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'products' => $products
        ]);
    }

    /* ===================== CREATE PRODUCT ===================== */
    if ($method === 'POST') {

        $input = json_decode(file_get_contents("php://input"), true);

        $title = trim($input['title'] ?? '');
        $price = (float)($input['price'] ?? 0);
        $description = $input['description'] ?? '';
        $status = $input['status'] ?? 'active';

        if ($title === '' || $price < 0) {
            response(['success' => false, 'error' => 'Invalid input'], 400);
        }

        $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $title), '-'));

        $stmt = $pdo->prepare("INSERT INTO products (title, slug, price, description, status, created_at, updated_at)
                               VALUES (?, ?, ?, ?, ?, NOW(), NOW())");

        $stmt->execute([$title, $slug, $price, $description, $status]);

        response([
            'success' => true,
            'id' => $pdo->lastInsertId()
        ]);
    }

    /* ===================== UPDATE PRODUCT ===================== */
    if ($method === 'PUT') {

        $input = json_decode(file_get_contents("php://input"), true);
        $id = (int)($input['id'] ?? 0);

        if ($id <= 0) {
            response(['success' => false, 'error' => 'Invalid ID'], 400);
        }

        $stmt = $pdo->prepare("
            UPDATE products 
            SET title=?, price=?, description=?, status=?, updated_at=NOW()
            WHERE id=?
        ");

        $stmt->execute([
            $input['title'] ?? '',
            (float)($input['price'] ?? 0),
            $input['description'] ?? '',
            $input['status'] ?? 'active',
            $id
        ]);

        response(['success' => true]);
    }

    /* ===================== DELETE PRODUCT ===================== */
    if ($method === 'DELETE') {

        $input = json_decode(file_get_contents("php://input"), true);
        $id = (int)($input['id'] ?? 0);

        if ($id <= 0) {
            response(['success' => false, 'error' => 'Invalid ID'], 400);
        }

        $stmt = $pdo->prepare("DELETE FROM products WHERE id=?");
        $stmt->execute([$id]);

        response(['success' => true]);
    }

    response(['success' => false, 'error' => 'Method not allowed'], 405);

} catch (Exception $e) {
    response(['success' => false, 'error' => $e->getMessage()], 500);
}