<?php
require_once __DIR__ . '/../db.php';
// Session-based registration
$input = json_decode(file_get_contents('php://input'), true);
if (!$input) jsonResponse(['error'=>'invalid_payload'],400);
$email = trim($input['email'] ?? '');
$password = $input['password'] ?? '';
$name = trim($input['name'] ?? '');
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) jsonResponse(['error'=>'invalid_email'],400);
if (strlen($password) < 8) jsonResponse(['error'=>'weak_password'],400);

$stmt = $pdo->prepare('SELECT id FROM users WHERE email = ?');
$stmt->execute([$email]);
if ($stmt->fetch()) jsonResponse(['error'=>'email_exists'],409);

$hash = password_hash($password, PASSWORD_DEFAULT);
$stmt = $pdo->prepare('INSERT INTO users (name,email,password) VALUES(?,?,?)');
$stmt->execute([$name, $email, $hash]);
$userId = $pdo->lastInsertId();

// Log the user in via session
$_SESSION['user_id'] = $userId;
jsonResponse(['success'=>true, 'user_id'=>$userId]);
