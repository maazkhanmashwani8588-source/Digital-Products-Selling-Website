<?php
require_once __DIR__ . '/../db.php';
function require_auth(){
  if (empty($_SESSION['user_id'])){
    jsonResponse(['error'=>'unauthorized'],401);
  }
  global $pdo;
  $stmt = $pdo->prepare('SELECT id,name,email,role FROM users WHERE id = ?');
  $stmt->execute([$_SESSION['user_id']]);
  $user = $stmt->fetch();
  if (!$user) jsonResponse(['error'=>'unauthorized'],401);
  return $user;
}
