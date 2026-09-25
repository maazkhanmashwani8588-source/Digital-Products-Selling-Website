<?php
require_once __DIR__ . '/../db.php';
// Simple invoice HTML generator. If dompdf is installed, it can return a PDF.
$orderId = $_GET['order_id'] ?? null;
if (!$orderId) { echo 'Missing order_id'; exit; }
$stmt = $pdo->prepare('SELECT o.*, u.name, u.email FROM orders o LEFT JOIN users u ON u.id = o.user_id WHERE o.id = ?');
$stmt->execute([$orderId]);
$order = $stmt->fetch();
if (!$order) { echo 'Order not found'; exit; }

$stmt = $pdo->prepare('SELECT oi.*, p.title FROM order_items oi JOIN products p ON p.id = oi.product_id WHERE oi.order_id = ?');
$stmt->execute([$orderId]);
$items = $stmt->fetchAll();

$html = '<html><head><meta charset="utf-8"><title>Invoice</title><style>body{font-family:Arial;padding:20px}table{width:100%;border-collapse:collapse}td,th{border:1px solid #ddd;padding:8px}</style></head><body>';
$html .= '<h2>Invoice • Order #'.$orderId.'</h2>';
$html .= '<p>Customer: '.htmlspecialchars($order['name'] ?? 'Guest').' ('.htmlspecialchars($order['email'] ?? '').')</p>';
$html .= '<table><thead><tr><th>Item</th><th>Qty</th><th>Price</th></tr></thead><tbody>';
$total = 0;
foreach($items as $it){ $html .= '<tr><td>'.htmlspecialchars($it['title']).'</td><td>'.$it['quantity'].'</td><td>$'.number_format($it['price'],2).'</td></tr>'; $total += $it['price']*$it['quantity']; }
$html .= '</tbody><tfoot><tr><td colspan="2">Total</td><td>$'.number_format($total,2).'</td></tr></tfoot></table>';
$html .= '</body></html>';

// If dompdf is installed, render PDF
if (file_exists(__DIR__ . '/../../vendor/autoload.php')){
  try{
    require_once __DIR__ . '/../../vendor/autoload.php';
    $dompdf = new \Dompdf\Dompdf();
    $dompdf->loadHtml($html);
    $dompdf->setPaper('A4','portrait');
    $dompdf->render();
    $dompdf->stream('invoice_'.$orderId.'.pdf');
    exit;
  }catch(Exception $e){ /* fallback to html */ }
}

echo $html;
