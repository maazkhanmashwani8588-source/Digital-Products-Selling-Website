<?php
require_once __DIR__ . '/../config.php';
$input = json_decode(file_get_contents('php://input'), true);
$productId = (int)($input['product_id'] ?? 0);
if (!$productId) jsonResponse(['error'=>'invalid_product'],400);
if (empty($_SESSION['cart'])) jsonResponse(['cart'=>[]]);
$_SESSION['cart'] = array_values(array_filter($_SESSION['cart'], function($it) use ($productId){ return $it['product_id'] != $productId; }));
jsonResponse(['success'=>true,'cart'=>$_SESSION['cart']]);
