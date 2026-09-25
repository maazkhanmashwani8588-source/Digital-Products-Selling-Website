<?php
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../config.php';

$orderId = $_GET['order_id'] ?? null;
if (!$orderId) jsonResponse(['error'=>'missing_order_id'],400);

$stmt = $pdo->prepare('SELECT * FROM orders WHERE id = ? LIMIT 1');
$stmt->execute([$orderId]);
$order = $stmt->fetch();
if (!$order) jsonResponse(['error'=>'order_not_found'],404);

$stripeSecret = getenv('STRIPE_SECRET_KEY');
if (!$stripeSecret){ // fallback to mock url for demo
  jsonResponse(['payment_url'=>'/backend/mock_payment.php?order_id='.$orderId]);
}

// Build line items from order_items
$stmtItems = $pdo->prepare('SELECT oi.*, p.title FROM order_items oi JOIN products p ON p.id = oi.product_id WHERE oi.order_id = ?');
$stmtItems->execute([$orderId]);
$items = $stmtItems->fetchAll();
$line_items = [];
foreach($items as $it){
  $name = $it['title'];
  $unit_amount = intval(round($it['price'] * 100));
  $qty = (int)$it['quantity'];
  $line_items[] = [
    'price_data' => [
      'currency' => 'usd',
      'product_data' => ['name' => $name],
      'unit_amount' => $unit_amount
    ],
    'quantity' => $qty
  ];
}

try{
  require_once __DIR__ . '/../../vendor/autoload.php';
  \Stripe\Stripe::setApiKey($stripeSecret);

  $userId = $order['user_id'] ?? null;
  $successUrl = (getenv('BASE_URL')?:'http://localhost') . '/frontend/order_detail.html?order_id='.$orderId;
  $cancelUrl = (getenv('BASE_URL')?:'http://localhost') . '/frontend/checkout.html';

  // idempotency key per order to avoid duplicate sessions
  $idempotencyKey = 'checkout_order_'.$orderId;

  $session = \Stripe\Checkout\Session::create([
    'payment_method_types' => ['card'],
    'line_items' => $line_items,
    'mode' => 'payment',
    'metadata' => ['order_id' => (string)$orderId, 'user_id' => (string)$userId],
    'client_reference_id' => (string)$orderId,
    'success_url' => $successUrl . '&session_id={CHECKOUT_SESSION_ID}',
    'cancel_url' => $cancelUrl
  ], ['idempotency_key' => $idempotencyKey]);

  jsonResponse(['payment_url'=>$session->url]);
}catch(Exception $e){
  // log error
  @file_put_contents(__DIR__.'/../logs/stripe.log', date('c')." stripe create error: ". $e->getMessage()."\n", FILE_APPEND);
  jsonResponse(['error'=>'stripe_error','detail'=>$e->getMessage()],500);
}
