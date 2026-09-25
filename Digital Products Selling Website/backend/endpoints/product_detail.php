<?php
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../models/Product.php';

$id = $_GET['id'] ?? null;
$slug = $_GET['slug'] ?? null;
$productModel = new Product($pdo);
$p = null;
if ($id){ $p = $productModel->findById((int)$id); }
elseif ($slug){ $p = $productModel->findBySlug($slug); }
else { jsonResponse(['error'=>'missing_identifier'],400); }

if (!$p) jsonResponse(['error'=>'not_found'],404);

// decode preview_images and tags
if (!empty($p['preview_images'])){
  $p['preview_images'] = json_decode($p['preview_images'], true) ?: [$p['preview_images']];
} else { $p['preview_images'] = []; }
if (!empty($p['tags'])){
  $p['tags'] = json_decode($p['tags'], true) ?: explode(',', $p['tags']);
} else { $p['tags'] = []; }

$p['related'] = $productModel->related($p['id'],4);

jsonResponse(['product'=>$p]);
