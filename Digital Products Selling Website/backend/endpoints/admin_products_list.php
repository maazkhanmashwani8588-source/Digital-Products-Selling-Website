<?php
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/auth_middleware.php';
$user = require_auth();
if ($user['role'] !== 'admin') jsonResponse(['error'=>'forbidden'],403);
$stmt = $pdo->query('SELECT * FROM products ORDER BY created_at DESC');
$products = $stmt->fetchAll();
jsonResponse(['products'=>$products]);
