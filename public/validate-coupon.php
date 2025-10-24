<?php
if (session_status() === PHP_SESSION_NONE) session_start();

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__.'/includes/functions.php';
require_once __DIR__.'/../config/db.php';

$payload = json_decode(file_get_contents('php://input'), true);
$code = strtoupper(trim($payload['code'] ?? ''));
$trip_id = $payload['trip_id'] ?? '';

if ($code === '' || $trip_id === '') {
  echo json_encode(['valid'=>false, 'message'=>'Eksik veri']); exit;
}

$st = $db->prepare('SELECT price, company_id FROM Trips WHERE id = ? LIMIT 1');
$st->execute([$trip_id]);
$trip = $st->fetch(PDO::FETCH_ASSOC);
if (!$trip) { echo json_encode(['valid'=>false,'message'=>'Sefer bulunamadı']); exit; }

$company_id = $trip['company_id'];
$price = (int)$trip['price'];

$st = $db->prepare('
  SELECT id, code, discount, usage_limit, expire_date
  FROM Coupons
  WHERE UPPER(code) = UPPER(?) 
    AND (company_id IS NULL OR company_id = ?)
    AND (expire_date IS NULL OR DATE(expire_date) >= DATE("now"))
  LIMIT 1
');
$st->execute([$code, $company_id]);
$c = $st->fetch(PDO::FETCH_ASSOC);
if (!$c) { echo json_encode(['valid'=>false,'message'=>'Kupon bulunamadı veya süresi dolmuş.']); exit; }

if ($c['usage_limit'] !== null && (int)$c['usage_limit'] <= 0) {
  echo json_encode(['valid'=>false,'message'=>'Kupon kullanım hakkı dolmuş.']); exit;
}

$discount = (float)$c['discount']; 
$new_total = round($price * (1 - $discount/100));

echo json_encode([
  'valid' => true,
  'coupon_id' => $c['id'],
  'code' => $c['code'],
  'discount' => $discount,
  'usage_limit' => $c['usage_limit'],
  'new_total' => (int)$new_total
]);
exit;
