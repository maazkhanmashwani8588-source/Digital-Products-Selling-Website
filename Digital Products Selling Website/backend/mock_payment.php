<?php
// Mock payment landing page — toggles order to paid for demo
require_once __DIR__ . '/db.php';
$orderId = $_GET['order_id'] ?? null;
if (!$orderId) { echo "Missing order_id"; exit; }
$stmt = $pdo->prepare('UPDATE orders SET status = ? WHERE id = ?');
$stmt->execute(['paid', $orderId]);
echo "Payment simulated. Order {$orderId} marked as paid. Return to frontend to access downloads.";
