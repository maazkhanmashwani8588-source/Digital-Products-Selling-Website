<?php
// Start session for PHP session-based auth
if (session_status() === PHP_SESSION_NONE) session_start();

// Simple config loader (reads environment variables or defaults)
$config = [
  'db_host' => getenv('DB_HOST') ?: '127.0.0.1',
  'db_name' => getenv('DB_NAME') ?: 'digital_hub',
  'db_user' => getenv('DB_USER') ?: 'root',
  'db_pass' => getenv('DB_PASS') ?: '',
  'app_secret' => getenv('APP_SECRET') ?: 'change_this_secret'
];

function jsonResponse($data, $code = 200) {
  header('Content-Type: application/json');
  http_response_code($code);
  echo json_encode($data);
  exit;
}
