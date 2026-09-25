<?php
require_once __DIR__ . '/../db.php';
$input = json_decode(file_get_contents('php://input'), true);
if (!$input) jsonResponse(['error'=>'invalid_payload'],400);
$token = $input['token'] ?? '';
$newPassword = $input['password'] ?? '';
if (strlen($newPassword) < 8) jsonResponse(['error'=>'weak_password'],400);

$stmt = $pdo->prepare('SELECT id, reset_expires FROM users WHERE reset_token = ? LIMIT 1');
$stmt->execute([$token]);
$user = $stmt->fetch();
if (!$user) jsonResponse(['error'=>'invalid_token'],400);
if (strtotime($user['reset_expires']) < time()) jsonResponse(['error'=>'token_expired'],400);

$hash = password_hash($newPassword, PASSWORD_DEFAULT);
$stmt = $pdo->prepare('UPDATE users SET password = ?, reset_token = NULL, reset_expires = NULL WHERE id = ?');
$stmt->execute([$hash, $user['id']]);

jsonResponse(['success'=>true]);
