<?php
require_once __DIR__ . '/../config.php';
$input = json_decode(file_get_contents('php://input'), true);
if (!$input) jsonResponse(['error'=>'invalid_payload'],400);
$productId = (int)($input['product_id'] ?? 0);
$quantity = max(1, (int)($input['quantity'] ?? 1));
if (!$productId) jsonResponse(['error'=>'invalid_product'],400);

if (!isset($_SESSION['cart'])) $_SESSION['cart'] = [];
$found = false;
foreach($_SESSION['cart'] as &$it){ if ($it['product_id']==$productId){ $it['quantity'] += $quantity; $found = true; break; } }
if (!$found){ $_SESSION['cart'][] = ['product_id'=>$productId,'quantity'=>$quantity]; }

jsonResponse(['success'=>true,'cart'=>$_SESSION['cart']]);
