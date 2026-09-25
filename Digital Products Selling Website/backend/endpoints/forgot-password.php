<?php
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../services/Email.php';
$input = json_decode(file_get_contents('php://input'), true);
if (!$input) jsonResponse(['error'=>'invalid_payload'],400);
$email = trim($input['email'] ?? '');
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) jsonResponse(['error'=>'invalid_email'],400);

$stmt = $pdo->prepare('SELECT id, name FROM users WHERE email = ?');
$stmt->execute([$email]);
$user = $stmt->fetch();
if (!$user) {
  // Don't reveal whether email exists
  jsonResponse(['success'=>true]);
}

$token = bin2hex(random_bytes(20));
$expires = date('Y-m-d H:i:s', time() + 3600); // 1 hour
$stmt = $pdo->prepare('UPDATE users SET reset_token = ?, reset_expires = ? WHERE id = ?');
$stmt->execute([$token, $expires, $user['id']]);

$resetLink = ($_SERVER['REQUEST_SCHEME'] ?? 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . 
  '/frontend/auth/reset-password.html?token=' . $token;

// Try to send email; if fails, still return success but log error
try {
  $mailer = new Email();
  $emailSent = $mailer->sendPasswordResetLink($email, $user['name'], $resetLink);
  if ($emailSent) {
    // Email sent successfully
    jsonResponse(['success'=>true]);
  } else {
    // Email failed but we'll still return success and provide the link in response (for demo)
    jsonResponse(['success'=>true, 'reset_link'=>$resetLink]);
  }
} catch (Exception $e) {
  // Email service not configured, return demo link
  @file_put_contents(__DIR__.'/../logs/email.log', date('c')." Password reset email config error: " . $e->getMessage() . "\n", FILE_APPEND);
  jsonResponse(['success'=>true, 'reset_link'=>$resetLink]);
}
