<?php
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/auth_middleware.php';
$user = require_auth();
$stmt = $pdo->prepare('SELECT * FROM orders WHERE user_id = ? ORDER BY created_at DESC');
$stmt->execute([$user['id']]);
$orders = $stmt->fetchAll();
jsonResponse(['orders'=>$orders]);
