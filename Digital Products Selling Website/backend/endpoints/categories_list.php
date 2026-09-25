<?php
require_once __DIR__ . '/../db.php';
$stmt = $pdo->query('SELECT id,name,slug FROM categories ORDER BY name');
$cats = $stmt->fetchAll();
jsonResponse(['categories'=>$cats]);
