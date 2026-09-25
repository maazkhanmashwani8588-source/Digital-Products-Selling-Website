<?php
require_once __DIR__ . '/../db.php';
// Create order from session cart and return order_id + total
if (empty($_SESSION['cart']) || !is_array($_SESSION['cart'])) jsonResponse(['error'=>'cart_empty'],400);
$userId = $_SESSION['user_id'] ?? null; // allow guest orders (null) if desired

$pdo->beginTransaction();
try{
  // compute total by querying products
  $total = 0.0;
  $items = [];
  $stmtProd = $pdo->prepare('SELECT id,price,sale_price FROM products WHERE id = ? LIMIT 1');
  foreach($_SESSION['cart'] as $it){
    $stmtProd->execute([(int)$it['product_id']]);
    $p = $stmtProd->fetch();
    if (!$p) continue;
    $price = $p['sale_price'] ? $p['sale_price'] : $p['price'];
    $qty = max(1,(int)$it['quantity']);
    $total += $price * $qty;
    $items[] = ['product_id'=>$p['id'],'price'=>$price,'quantity'=>$qty];
  }

  $stmt = $pdo->prepare('INSERT INTO orders (user_id,total,status) VALUES (?,?,?)');
  $stmt->execute([$userId, $total, 'pending']);
  $orderId = $pdo->lastInsertId();

  $stmtItem = $pdo->prepare('INSERT INTO order_items (order_id,product_id,price,quantity) VALUES (?,?,?,?)');
  foreach($items as $it){ $stmtItem->execute([$orderId,$it['product_id'],$it['price'],$it['quantity']]); }

  $pdo->commit();
  // return order for payment processing (Stripe integration will use this order)
  jsonResponse(['success'=>true,'order_id'=>$orderId,'total'=>number_format($total,2,'.','')]);
}catch(Exception $e){ $pdo->rollBack(); jsonResponse(['error'=>'checkout_failed','detail'=>$e->getMessage()],500); }
