<?php
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/auth_middleware.php';
$user = require_auth();
if ($user['role'] !== 'admin') jsonResponse(['error'=>'forbidden'],403);
$input = json_decode(file_get_contents('php://input'), true);
if (!$input) jsonResponse(['error'=>'invalid_payload'],400);

$id = $input['id'] ?? null;
$fields = ['sku','title','slug','short_description','description','thumbnail','preview_images','file_path','file_size','file_type','file_hash','version','price','sale_price','category_id','tags','featured','status','download_limit'];
if ($id){
  $sets = [];$params = [];
  foreach($fields as $f){ if (isset($input[$f])){ $sets[] = "$f = ?"; $params[] = $input[$f]; }}
  if ($sets){ $params[] = $id; $stmt = $pdo->prepare('UPDATE products SET '.implode(',',$sets).' WHERE id = ?'); $stmt->execute($params); }
  jsonResponse(['success'=>true]);
} else {
  $cols = [];$place = [];$params = [];
  foreach($fields as $f){ if (isset($input[$f])){ $cols[] = $f; $place[] = '?'; $params[] = $input[$f]; }}
  $sql = 'INSERT INTO products ('.implode(',',$cols).') VALUES ('.implode(',',$place).')';
  $stmt = $pdo->prepare($sql); $stmt->execute($params);
  jsonResponse(['success'=>true,'id'=>$pdo->lastInsertId()]);
}
