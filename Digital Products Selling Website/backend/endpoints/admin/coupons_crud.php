<?php
/**
 * Admin Coupons Management Endpoint
 * 
 * GET /backend/endpoints/admin/coupons_crud.php - List coupons
 * POST /backend/endpoints/admin/coupons_crud.php - Create coupon
 * PUT /backend/endpoints/admin/coupons_crud.php - Update coupon
 * DELETE /backend/endpoints/admin/coupons_crud.php - Delete coupon
 */

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/../auth_middleware.php';

$user = require_auth();
if ($user['role'] !== 'admin') {
    jsonResponse(['error' => 'Unauthorized'], 403);
}

$method = $_SERVER['REQUEST_METHOD'];

try {
    if ($method === 'GET') {
        // List coupons
        $page = (int)($_GET['page'] ?? 1);
        $perPage = (int)($_GET['per_page'] ?? 20);
        $offset = ($page - 1) * $perPage;
        
        // Get total count
        $stmt = $pdo->prepare('SELECT COUNT(*) as count FROM coupons');
        $stmt->execute();
        $total = $stmt->fetch()['count'];
        
        // Get paginated coupons
        $stmt = $pdo->prepare('
            SELECT id, code, type, value, expires_at, created_at
            FROM coupons
            ORDER BY created_at DESC
            LIMIT ? OFFSET ?
        ');
        $stmt->execute([$perPage, $offset]);
        $coupons = $stmt->fetchAll();
        
        jsonResponse([
            'success' => true,
            'total' => (int)$total,
            'page' => $page,
            'per_page' => $perPage,
            'coupons' => $coupons
        ]);
        
    } elseif ($method === 'POST') {
        // Create coupon
        $input = json_decode(file_get_contents('php://input'), true);
        
        $code = strtoupper(trim($input['code'] ?? ''));
        $type = $input['type'] ?? 'percent'; // percent or fixed
        $value = (float)($input['value'] ?? 0);
        $expires_at = $input['expires_at'] ?? null;
        
        if (!$code || !in_array($type, ['percent', 'fixed']) || $value <= 0) {
            jsonResponse(['error' => 'Invalid coupon data'], 400);
        }
        
        // Check code uniqueness
        $stmt = $pdo->prepare('SELECT id FROM coupons WHERE code = ?');
        $stmt->execute([$code]);
        if ($stmt->fetch()) {
            jsonResponse(['error' => 'Coupon code already exists'], 400);
        }
        
        $stmt = $pdo->prepare('
            INSERT INTO coupons (code, type, value, expires_at)
            VALUES (?, ?, ?, ?)
        ');
        
        $stmt->execute([$code, $type, $value, $expires_at]);
        $couponId = $pdo->lastInsertId();
        
        jsonResponse(['success' => true, 'id' => $couponId, 'message' => 'Coupon created']);
        
    } elseif ($method === 'PUT') {
        // Update coupon
        $input = json_decode(file_get_contents('php://input'), true);
        $couponId = (int)($input['id'] ?? 0);
        
        if ($couponId <= 0) {
            jsonResponse(['error' => 'Invalid coupon ID'], 400);
        }
        
        $fields = [];
        $values = [];
        
        if (isset($input['value'])) {
            $fields[] = 'value = ?';
            $values[] = (float)$input['value'];
        }
        if (isset($input['expires_at'])) {
            $fields[] = 'expires_at = ?';
            $values[] = $input['expires_at'];
        }
        
        if (empty($fields)) {
            jsonResponse(['error' => 'No fields to update'], 400);
        }
        
        $values[] = $couponId;
        $stmt = $pdo->prepare('UPDATE coupons SET ' . implode(', ', $fields) . ' WHERE id = ?');
        $stmt->execute($values);
        
        jsonResponse(['success' => true, 'message' => 'Coupon updated']);
        
    } elseif ($method === 'DELETE') {
        // Delete coupon
        $input = json_decode(file_get_contents('php://input'), true);
        $couponId = (int)($input['id'] ?? 0);
        
        if ($couponId <= 0) {
            jsonResponse(['error' => 'Invalid coupon ID'], 400);
        }
        
        $stmt = $pdo->prepare('DELETE FROM coupons WHERE id = ?');
        $stmt->execute([$couponId]);
        
        jsonResponse(['success' => true, 'message' => 'Coupon deleted']);
        
    } else {
        jsonResponse(['error' => 'Method not allowed'], 405);
    }
    
} catch (Exception $e) {
    jsonResponse(['error' => $e->getMessage()], 500);
}
