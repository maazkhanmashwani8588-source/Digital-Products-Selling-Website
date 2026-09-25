<?php
require_once __DIR__ . '/../config.php';
if (empty($_SESSION['cart'])) jsonResponse(['cart'=>[]]);
jsonResponse(['cart'=>$_SESSION['cart']]);
