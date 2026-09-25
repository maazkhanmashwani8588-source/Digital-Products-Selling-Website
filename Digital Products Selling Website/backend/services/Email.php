<?php
require_once __DIR__ . '/../config.php';

class Email {
  private $mailer;
  private $config;

  public function __construct(){
    $this->config = [
      'host' => getenv('MAIL_HOST') ?: 'smtp.gmail.com',
      'port' => (int)(getenv('MAIL_PORT') ?: 587),
      'username' => getenv('MAIL_USERNAME'),
      'password' => getenv('MAIL_PASSWORD'),
      'from_address' => getenv('MAIL_FROM_ADDRESS') ?: 'noreply@digitalhub.local',
      'from_name' => getenv('MAIL_FROM_NAME') ?: 'Digital Hub'
    ];

    if (!$this->config['username'] || !$this->config['password']) {
      throw new Exception('Email not configured. Set MAIL_USERNAME and MAIL_PASSWORD.');
    }

    try {
      require_once __DIR__ . '/../../vendor/autoload.php';
      $this->mailer = new \PHPMailer\PHPMailer\PHPMailer(true);
      $this->mailer->isSMTP();
      $this->mailer->Host = $this->config['host'];
      $this->mailer->Port = $this->config['port'];
      $this->mailer->SMTPAuth = true;
      $this->mailer->Username = $this->config['username'];
      $this->mailer->Password = $this->config['password'];
      $this->mailer->SMTPSecure = $this->config['port'] == 587 ? \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS : \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
      $this->mailer->setFrom($this->config['from_address'], $this->config['from_name']);
    } catch (Exception $e) {
      throw new Exception('PHPMailer init error: ' . $e->getMessage());
    }
  }

  public function sendOrderConfirmation($recipientEmail, $recipientName, $orderId, $orderTotal, $downloadLinks, $invoicePdfPath = null){
    $html = $this->loadTemplate('order_confirmation', [
      'customer_name' => $recipientName,
      'order_id' => $orderId,
      'order_total' => $orderTotal,
      'download_links' => $downloadLinks,
      'year' => date('Y')
    ]);

    try {
      $this->mailer->clearAddresses();
      $this->mailer->addAddress($recipientEmail, $recipientName);
      $this->mailer->Subject = 'Order Confirmation #' . $orderId . ' - Digital Hub';
      $this->mailer->isHTML(true);
      $this->mailer->Body = $html;

      if ($invoicePdfPath && file_exists($invoicePdfPath)) {
        $this->mailer->addAttachment($invoicePdfPath, 'invoice_' . $orderId . '.pdf');
      }

      $this->mailer->send();
      return true;
    } catch (Exception $e) {
      $this->logError('sendOrderConfirmation', $e->getMessage());
      return false;
    }
  }

  public function sendPasswordResetLink($recipientEmail, $recipientName, $resetLink){
    $html = $this->loadTemplate('password_reset', [
      'customer_name' => $recipientName,
      'reset_link' => $resetLink,
      'year' => date('Y')
    ]);

    try {
      $this->mailer->clearAddresses();
      $this->mailer->addAddress($recipientEmail, $recipientName);
      $this->mailer->Subject = 'Reset Your Password - Digital Hub';
      $this->mailer->isHTML(true);
      $this->mailer->Body = $html;
      $this->mailer->send();
      return true;
    } catch (Exception $e) {
      $this->logError('sendPasswordResetLink', $e->getMessage());
      return false;
    }
  }

  private function loadTemplate($templateName, $data = []){
    $templatePath = __DIR__ . '/../templates/' . $templateName . '.html';
    if (!file_exists($templatePath)) {
      throw new Exception('Email template not found: ' . $templatePath);
    }
    ob_start();
    extract($data);
    include $templatePath;
    return ob_get_clean();
  }

  private function logError($method, $error){
    $log = date('c') . " [{$method}] {$error}\n";
    @file_put_contents(__DIR__ . '/../logs/email.log', $log, FILE_APPEND);
  }
}
