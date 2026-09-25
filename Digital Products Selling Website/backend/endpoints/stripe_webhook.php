<?php
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../services/Email.php';
require_once __DIR__ . '/../services/Storage.php';

$payload = @file_get_contents('php://input');
$sig_header = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';
$endpoint_secret = getenv('STRIPE_WEBHOOK_SECRET');

// default: try to parse JSON
try{
  require_once __DIR__ . '/../../vendor/autoload.php';
  if (!$endpoint_secret) { throw new Exception('Missing STRIPE_WEBHOOK_SECRET'); }
  $event = \Stripe\Webhook::constructEvent($payload, $sig_header, $endpoint_secret);
}catch(Exception $e){
  // log and return 400
  @file_put_contents(__DIR__.'/../logs/stripe.log', date('c')." webhook verify failed: " . $e->getMessage() . "\n", FILE_APPEND);
  http_response_code(400); echo 'Webhook signature verification failed'; exit;
}

$type = $event->type;
// handle checkout.session.completed
if ($type === 'checkout.session.completed'){
  $session = $event->data->object;
  $sessionId = $session->id;
  $orderId = $session->metadata->order_id ?? $session->client_reference_id ?? null;
  $userId = $session->metadata->user_id ?? null;

  if (!$orderId){ @file_put_contents(__DIR__.'/../logs/stripe.log', date('c')." webhook missing order_id for session {$sessionId}\n", FILE_APPEND); http_response_code(400); echo 'missing order id'; exit; }

  // idempotency: check if payment already recorded for this session or payment_intent
  $transactionId = $session->payment_intent ?? $sessionId;
  $stmt = $pdo->prepare('SELECT id FROM payments WHERE transaction_id = ? LIMIT 1');
  $stmt->execute([$transactionId]);
  if ($stmt->fetch()){
    // already processed
    http_response_code(200); echo 'already_processed'; exit;
  }

  // verify order exists and not already paid
  $stmt = $pdo->prepare('SELECT o.*, u.name, u.email FROM orders o LEFT JOIN users u ON u.id = o.user_id WHERE o.id = ? LIMIT 1');
  $stmt->execute([$orderId]);
  $order = $stmt->fetch();
  if (!$order){ http_response_code(404); echo 'order not found'; exit; }

  if ($order['status'] !== 'paid'){
    // mark paid
    $stmt = $pdo->prepare('UPDATE orders SET status = ? WHERE id = ?');
    $stmt->execute(['paid', $orderId]);

    // record payment (use prepared statements)
    $amount = ($session->amount_total ?? 0) / 100.0;
    $stmt = $pdo->prepare('INSERT INTO payments (order_id,provider,amount,status,transaction_id,created_at) VALUES (?,?,?,?,?,NOW())');
    $stmt->execute([$orderId, 'stripe', $amount, 'paid', $transactionId]);

    // create downloads entries for each item
    $stmtItems = $pdo->prepare('SELECT oi.product_id, p.file_path FROM order_items oi JOIN products p ON p.id = oi.product_id WHERE oi.order_id = ?');
    $stmtItems->execute([$orderId]);
    $items = $stmtItems->fetchAll();
    $stmtDl = $pdo->prepare('INSERT INTO downloads (order_id, product_id, user_id, file_path, accessed_at) VALUES (?,?,?,?,NOW())');
    foreach($items as $it){ $stmtDl->execute([$orderId, $it['product_id'], $userId ?: null, $it['file_path']]); }

    // generate invoice PDF if dompdf available and save to invoices folder
    $invoicePdfPath = null;
    try{
      $invoiceDir = __DIR__ . '/../invoices';
      if (!is_dir($invoiceDir)) @mkdir($invoiceDir, 0755, true);
      // build invoice HTML (reuse logic similar to generate_invoice.php)
      $stmt = $pdo->prepare('SELECT o.*, u.name, u.email FROM orders o LEFT JOIN users u ON u.id = o.user_id WHERE o.id = ?');
      $stmt->execute([$orderId]); $orderRow = $stmt->fetch();
      $stmt = $pdo->prepare('SELECT oi.*, p.title FROM order_items oi JOIN products p ON p.id = oi.product_id WHERE oi.order_id = ?');
      $stmt->execute([$orderId]); $orderItems = $stmt->fetchAll();
      $html = '<html><head><meta charset="utf-8"><title>Invoice</title><style>body{font-family:Arial;padding:20px}table{width:100%;border-collapse:collapse}td,th{border:1px solid #ddd;padding:8px}</style></head><body>';
      $html .= '<h2>Invoice • Order #'.$orderId.'</h2>';
      $html .= '<p>Customer: '.htmlspecialchars($orderRow['name'] ?? 'Guest').' ('.htmlspecialchars($orderRow['email'] ?? '').')</p>';
      $html .= '<table><thead><tr><th>Item</th><th>Qty</th><th>Price</th></tr></thead><tbody>';
      $total = 0;
      foreach($orderItems as $it){ $html .= '<tr><td>'.htmlspecialchars($it['title']).'</td><td>'.$it['quantity'].'</td><td>$'.number_format($it['price'],2).'</td></tr>'; $total += $it['price']*$it['quantity']; }
      $html .= '</tbody><tfoot><tr><td colspan="2">Total</td><td>$'.number_format($total,2).'</td></tr></tfoot></table>';
      $html .= '</body></html>';

      if (file_exists(__DIR__ . '/../../vendor/autoload.php')){
        require_once __DIR__ . '/../../vendor/autoload.php';
        $dompdf = new \Dompdf\Dompdf();
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4','portrait');
        $dompdf->render();
        $invoicePdfPath = $invoiceDir . '/invoice_'.$orderId.'.pdf';
        file_put_contents($invoicePdfPath, $dompdf->output());
      }
    }catch(Exception $e){ @file_put_contents(__DIR__.'/../logs/stripe.log', date('c')." invoice error: " . $e->getMessage() . "\n", FILE_APPEND); }

    // SEND EMAIL with download links and invoice
    try {
      // Get product files linked to order items (from new product_files table)
      $downloadLinks = [];
      
      $stmtFiles = $pdo->prepare('
        SELECT DISTINCT pf.id, pf.storage_key, pf.original_filename, pf.storage_driver, p.title
        FROM order_items oi
        JOIN products p ON p.id = oi.product_id
        LEFT JOIN product_files pf ON pf.product_id = p.id AND pf.is_active = 1
        WHERE oi.order_id = ?
      ');
      $stmtFiles->execute([$orderId]);
      $productFiles = $stmtFiles->fetchAll();
      
      $storage = new Storage();
      foreach ($productFiles as $pf) {
        if (!$pf['storage_key']) continue; // Skip if no file
        
        // Generate signed URL (1 hour for email; user can get new ones via /order_detail.html)
        $downloadUrl = $storage->getSignedUrl($pf['storage_key'], 3600);
        
        if ($downloadUrl) {
          $downloadLinks[] = [
            'title' => $pf['title'] . ' (' . $pf['original_filename'] . ')',
            'url' => $downloadUrl
          ];
        }
      }

      // Send email
      $email = new Email();
      $recipientEmail = $order['email'] ?? '';
      $recipientName = $order['name'] ?? 'Customer';
      $orderTotal = number_format($order['total'], 2);

      if ($recipientEmail && count($downloadLinks) > 0) {
        $emailSent = $email->sendOrderConfirmation(
          $recipientEmail,
          $recipientName,
          $orderId,
          $orderTotal,
          $downloadLinks,
          $invoicePdfPath
        );

        if ($emailSent) {
          @file_put_contents(__DIR__.'/../logs/stripe.log', date('c')." email sent to {$recipientEmail} for order {$orderId}\n", FILE_APPEND);
        } else {
          @file_put_contents(__DIR__.'/../logs/stripe.log', date('c')." email failed for order {$orderId}\n", FILE_APPEND);
        }
      } elseif ($recipientEmail && count($downloadLinks) === 0) {
        @file_put_contents(__DIR__.'/../logs/stripe.log', date('c')." warning: no files to send for order {$orderId}\n", FILE_APPEND);
      }
    } catch (Exception $e) {
      @file_put_contents(__DIR__.'/../logs/stripe.log', date('c')." email service error: " . $e->getMessage() . "\n", FILE_APPEND);
    }
