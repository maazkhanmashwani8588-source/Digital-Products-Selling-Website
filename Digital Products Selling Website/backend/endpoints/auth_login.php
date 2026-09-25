<?php
require_once __DIR__ . '/../db.php';
$input = json_decode(file_get_contents('php://input'), true);
if (!$input) jsonResponse(['error'=>'invalid_payload'],400);
$email = trim($input['email'] ?? '');
$password = $input['password'] ?? '';
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) jsonResponse(['error'=>'invalid_email'],400);

$stmt = $pdo->prepare('SELECT id,password,name,email,role FROM users WHERE email = ?');
$stmt->execute([$email]);
$user = $stmt->fetch();
if (!$user || !password_verify($password, $user['password'])) jsonResponse(['error'=>'invalid_credentials'],401);

// Minimal session response — in production return JWT
// Set session
$_SESSION['user_id'] = $user['id'];
jsonResponse(['success'=>true, 'user' => ['id'=>$user['id'],'name'=>$user['name'],'email'=>$user['email'],'role'=>$user['role']]]);
