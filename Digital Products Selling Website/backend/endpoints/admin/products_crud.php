<?php

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/../auth_middleware.php';

$user = require_auth();

if (!$user || !isset($user['role']) || $user['role'] !== 'admin') {
    jsonResponse(['error' => 'Unauthorized'], 403);
}

$method = $_SERVER['REQUEST_METHOD'];

try {

    // =========================
    // GET PRODUCTS
    // =========================
    if ($method === 'GET') {

        $page = (int)($_GET['page'] ?? 1);
        $perPage = (int)($_GET['per_page'] ?? 20);
        $search = trim($_GET['search'] ?? '');

        $offset = ($page - 1) * $perPage;

        $where = '1=1';
        $params = [];

        if (!empty($search)) {
            $where = "(p.title LIKE ? OR p.sku LIKE ? OR p.description LIKE ?)";
            $params = ["%$search%", "%$search%", "%$search%"];
        }

        // =========================
        // TOTAL COUNT
        // =========================
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as count
            FROM products p
            WHERE $where
        ");
        $stmt->execute($params);
        $total = (int)$stmt->fetch()['count'];

        // =========================
        // PRODUCTS LIST
        // (NO GROUP BY BUG VERSION)
        // =========================
        $sql = "
            SELECT 
                p.*,
                COALESCE(c.name, '-') as category_name,
                (
                    SELECT COUNT(*)
                    FROM order_items oi
                    WHERE oi.product_id = p.id
                ) as sales_count
            FROM products p
            LEFT JOIN categories c ON c.id = p.category_id
            WHERE $where
            ORDER BY p.created_at DESC
            LIMIT ? OFFSET ?
        ";

        $stmt = $pdo->prepare($sql);

        $params[] = $perPage;
        $params[] = $offset;

        $stmt->execute($params);
        $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

        jsonResponse([
            'success' => true,
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'products' => $products
        ]);
    }

    // =========================
    // CREATE PRODUCT
    // =========================
    elseif ($method === 'POST') {

        $input = json_decode(file_get_contents('php://input'), true);

        $title = trim($input['title'] ?? '');
        $price = (float)($input['price'] ?? 0);
        $category_id = !empty($input['category_id']) ? (int)$input['category_id'] : null;
        $description = $input['description'] ?? '';
        $short_description = $input['short_description'] ?? '';
        $sku = trim($input['sku'] ?? '');
        $featured = (int)($input['featured'] ?? 0);
        $status = $input['status'] ?? 'active';

        if ($title === '' || $price < 0) {
            jsonResponse(['error' => 'Title and valid price required'], 400);
        }

        // slug
        $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $title), '-'));

        $stmt = $pdo->prepare("SELECT id FROM products WHERE slug = ?");
        $stmt->execute([$slug]);

        if ($stmt->fetch()) {
            $slug .= '-' . time();
        }

        $stmt = $pdo->prepare("
            INSERT INTO products 
            (title, slug, price, category_id, description, short_description, sku, featured, status)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $stmt->execute([
            $title,
            $slug,
            $price,
            $category_id,
            $description,
            $short_description,
            $sku,
            $featured,
            $status
        ]);

        jsonResponse([
            'success' => true,
            'id' => $pdo->lastInsertId(),
            'message' => 'Product created'
        ]);
    }

    // =========================
    // UPDATE PRODUCT
    // =========================
    elseif ($method === 'PUT') {

        $input = json_decode(file_get_contents('php://input'), true);
        $id = (int)($input['id'] ?? 0);

        if ($id <= 0) {
            jsonResponse(['error' => 'Invalid product ID'], 400);
        }

        $fields = [];
        $values = [];

        foreach (['title','price','category_id','description','short_description','sku','featured','status','sale_price'] as $field) {
            if (isset($input[$field])) {
                $fields[] = "$field = ?";

                if (in_array($field, ['price','sale_price'])) {
                    $values[] = (float)$input[$field];
                } elseif ($field === 'featured') {
                    $values[] = (int)$input[$field];
                } elseif ($field === 'category_id') {
                    $values[] = !empty($input[$field]) ? (int)$input[$field] : null;
                } else {
                    $values[] = $input[$field];
                }
            }
        }

        if (empty($fields)) {
            jsonResponse(['error' => 'No fields to update'], 400);
        }

        $values[] = $id;

        $stmt = $pdo->prepare("
            UPDATE products 
            SET " . implode(',', $fields) . " 
            WHERE id = ?
        ");

        $stmt->execute($values);

        jsonResponse(['success' => true, 'message' => 'Product updated']);
    }

    // =========================
    // DELETE PRODUCT
    // =========================
    elseif ($method === 'DELETE') {

        $input = json_decode(file_get_contents('php://input'), true);
        $id = (int)($input['id'] ?? 0);

        if ($id <= 0) {
            jsonResponse(['error' => 'Invalid product ID'], 400);
        }

        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM order_items WHERE product_id = ?");
        $stmt->execute([$id]);
        $count = (int)$stmt->fetch()['count'];

        if ($count > 0) {
            jsonResponse(['error' => 'Cannot delete product with orders'], 400);
        }

        $stmt = $pdo->prepare("DELETE FROM products WHERE id = ?");
        $stmt->execute([$id]);

        jsonResponse(['success' => true, 'message' => 'Product deleted']);
    }

    else {
        jsonResponse(['error' => 'Method not allowed'], 405);
    }

} catch (Exception $e) {
    jsonResponse([
        'error' => $e->getMessage()
    ], 500);
}