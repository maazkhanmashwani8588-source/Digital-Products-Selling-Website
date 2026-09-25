<?php
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/auth_middleware.php';
$user = require_auth();
$orderId = $_GET['order_id'] ?? null;
if (!$orderId) jsonResponse(['error'=>'missing_order_id'],400);
$stmt = $pdo->prepare('SELECT * FROM orders WHERE id = ? AND user_id = ? LIMIT 1');
$stmt->execute([$orderId, $user['id']]);
$order = $stmt->fetch();
if (!$order) jsonResponse(['error'=>'not_found'],404);
$stmt = $pdo->prepare('SELECT oi.*, p.title FROM order_items oi JOIN products p ON p.id = oi.product_id WHERE oi.order_id = ?');
$stmt->execute([$orderId]);
$items = $stmt->fetchAll();
jsonResponse(['order'=>$order,'items'=>$items]);
