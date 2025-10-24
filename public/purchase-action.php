<?php

if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/includes/functions.php';
require_login();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/navbar.php';


$role = $_SESSION['user']['role'] ?? null;
if ($role !== 'user') {
  $msg = ($role === 'company') ? 'Firma yetkilileri bilet satın alamaz.' : 'Admin hesabıyla bilet satın alınamaz.';
  echo '<main class="container py-4"><div class="alert alert-warning">'.$msg.'</div><a class="btn btn-primary" href="index.php">Ana sayfaya dön</a></main>';
  require_once __DIR__ . '/includes/footer.php'; exit;
}


if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  echo '<main class="container py-4"><div class="alert alert-danger">Geçersiz istek yöntemi.</div></main>';
  require_once __DIR__ . '/includes/footer.php'; exit;
}
$u = $_SESSION['user'] ?? null;
if (!$u) { echo '<main class="container py-4"><div class="alert alert-warning">Oturum bulunamadı.</div></main>'; require_once __DIR__ . '/includes/footer.php'; exit; }

$trip_id     = $_POST['trip_id']     ?? '';
$seat_number = $_POST['seat_number'] ?? '';
$coupon_code = trim($_POST['coupon_code'] ?? '');
if (!$trip_id || $seat_number === '' || !ctype_digit((string)$seat_number)) {
  echo '<main class="container py-4"><div class="alert alert-danger">Sefer veya koltuk seçimi eksik/geçersiz.</div></main>';
  require_once __DIR__ . '/includes/footer.php'; exit;
}
$seat_number = (int)$seat_number;


$uuidv4 = static function(): string {
  return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
    mt_rand(0,0xffff),mt_rand(0,0xffff), mt_rand(0,0xffff),
    (mt_rand(0,0x0fff)|0x4000), (mt_rand(0,0x3fff)|0x8000),
    mt_rand(0,0xffff),mt_rand(0,0xffff),mt_rand(0,0xffff));
};

try {
  $db->exec('PRAGMA foreign_keys = ON');

  
  $st = $db->prepare('SELECT departure_city, destination_city, departure_time, capacity, price, company_id FROM Trips WHERE id=? LIMIT 1');
  $st->execute([$trip_id]);
  $trip = $st->fetch(PDO::FETCH_ASSOC);
  if (!$trip) { echo '<main class="container py-4"><div class="alert alert-danger">Sefer bulunamadı.</div></main>'; require_once __DIR__ . '/includes/footer.php'; exit; }


  $departTs = strtotime($trip['departure_time']);
  if ($departTs === false || $departTs <= time()) {
    echo '<main class="container py-4"><div class="alert alert-warning">Sefer zamanı geçtiği için satın alma yapılamaz.</div></main>';
    require_once __DIR__ . '/includes/footer.php'; exit;
  }
  $capacity = (int)$trip['capacity'];
  if ($seat_number < 1 || $seat_number > $capacity) {
    echo '<main class="container py-4"><div class="alert alert-danger">Koltuk numarası kapasite dışında.</div></main>';
    require_once __DIR__ . '/includes/footer.php'; exit;
  }


  $db->beginTransaction();

  
  $occ = $db->prepare('
    SELECT 1
    FROM Booked_Seats bs
    JOIN Tickets tk ON tk.id = bs.ticket_id
    WHERE tk.trip_id = ? AND tk.status = "active" AND bs.seat_number = ? LIMIT 1
  ');
  $occ->execute([$trip_id, $seat_number]);
  if ($occ->fetch()) {
    $db->rollBack();
    echo '<main class="container py-4"><div class="alert alert-danger">Seçtiğiniz koltuk az önce alınmış. Lütfen başka koltuk seçin.</div></main>';
    require_once __DIR__ . '/includes/footer.php'; exit;
  }

  $basePrice = (int)$trip['price'];
  $total = $basePrice;
  $appliedCouponId = null;

  if ($coupon_code !== '') {
    $stC = $db->prepare('
      SELECT id, discount, usage_limit, expire_date
      FROM Coupons
      WHERE UPPER(code)=UPPER(?) AND (company_id IS NULL OR company_id = ?)
        AND (expire_date IS NULL OR DATE(expire_date) >= DATE("now"))
      LIMIT 1
    ');
    $stC->execute([strtoupper($coupon_code), $trip['company_id']]);
    $coupon = $stC->fetch(PDO::FETCH_ASSOC);

    if (!$coupon) { $db->rollBack(); echo '<main class="container py-4"><div class="alert alert-danger">Kupon geçersiz veya süresi dolmuş.</div></main>'; require_once __DIR__ . '/includes/footer.php'; exit; }
    if ($coupon['usage_limit'] !== null && (int)$coupon['usage_limit'] <= 0) {
      $db->rollBack(); echo '<main class="container py-4"><div class="alert alert-warning">Kupon kullanım hakkı kalmamış.</div></main>'; require_once __DIR__ . '/includes/footer.php'; exit;
    }

    $discount = (float)$coupon['discount'];
    $total = (int)round($basePrice * (1 - $discount/100));
    $appliedCouponId = $coupon['id'];
  }

 
  $updBal = $db->prepare('UPDATE "User" SET balance = balance - :amt WHERE id = :uid AND balance >= :amt');
  $updBal->execute([':amt'=>$total, ':uid'=>$u['id']]);
  if ($updBal->rowCount() === 0) {
    $db->rollBack();
    $pretty = '₺'.number_format($total,0,',','.');
    echo '<main class="container py-4">
            <div class="alert alert-danger">Bakiye yetersiz. Bu işlem için en az <strong>'.$pretty.'</strong> bakiyeye ihtiyaç var.</div>
            <a class="btn btn-outline-secondary" href="tickets.php">Biletlerim</a>
            <a class="btn btn-primary ms-2" href="index.php">Ana sayfa</a>
          </main>';
    require_once __DIR__ . '/includes/footer.php'; exit;
  }

  
  $ticket_id = $uuidv4();
  $hasTotalPrice = (bool)$db->query("SELECT 1 FROM pragma_table_info('Tickets') WHERE name='total_price'")->fetchColumn();

  if ($hasTotalPrice) {
    $insT = $db->prepare('INSERT INTO Tickets (id, trip_id, user_id, status, total_price, created_at)
                          VALUES (?, ?, ?, "active", ?, datetime("now"))');
    $insT->execute([$ticket_id, $trip_id, $u['id'], $total]);
  } else {
    $insT = $db->prepare('INSERT INTO Tickets (id, trip_id, user_id, status, created_at)
                          VALUES (?, ?, ?, "active", datetime("now"))');
    $insT->execute([$ticket_id, $trip_id, $u['id']]);
  }


  $bs_id = $uuidv4();
  $db->prepare('INSERT INTO Booked_Seats (id, ticket_id, seat_number, created_at)
                VALUES (?, ?, ?, datetime("now"))')
     ->execute([$bs_id, $ticket_id, $seat_number]);


  if ($appliedCouponId) {
    $db->prepare('UPDATE Coupons SET usage_limit = usage_limit - 1
                  WHERE id = ? AND usage_limit IS NOT NULL AND usage_limit > 0')
       ->execute([$appliedCouponId]);
    $uc_id = $uuidv4();
    $db->prepare('INSERT INTO User_Coupons (id, coupon_id, user_id, created_at)
                  VALUES (?, ?, ?, datetime("now"))')
       ->execute([$uc_id, $appliedCouponId, $u['id']]);
  }


  $db->commit();


  $stBal = $db->prepare('SELECT balance FROM "User" WHERE id = ?');
  $stBal->execute([$u['id']]);
  $_SESSION['user']['balance'] = (float)$stBal->fetchColumn();

  
  $prettyPrice = '₺' . number_format($total, 0, ',', '.');
  ?>
  <main class="container py-4">
    <div class="alert alert-success mb-4"><strong>Bileti aldınız!</strong> Satın alma işlemi tamamlandı.</div>
    <div class="card mb-4"><div class="card-body">
      <div class="row">
        <div class="col-sm-6">
          <div class="mb-2"><span class="text-muted">Sefer:</span>
            <strong><?= htmlspecialchars($trip['departure_city'].' → '.$trip['destination_city']) ?></strong></div>
          <div class="mb-2"><span class="text-muted">Tarih/Saat:</span> <?= date('Y-m-d H:i', $departTs) ?></div>
          <div class="mb-2"><span class="text-muted">Koltuk:</span> <strong><?= htmlspecialchars($seat_number) ?></strong></div>
          <div class="mb-2"><span class="text-muted">Bilet No:</span> <code><?= htmlspecialchars($ticket_id) ?></code></div>
        </div>
        <div class="col-sm-6">
          <div class="mb-2"><span class="text-muted">Ödenen Tutar:</span> <strong><?= $prettyPrice ?></strong></div>
          <div class="small text-muted">Tutar sadece sefer kaydına göre hesaplandı. Bakiye düşümü gerçekleştirildi.</div>
        </div>
      </div>
    </div></div>

    <div class="d-flex gap-2">
      <a class="btn btn-primary" target="_blank" href="ticket-print.php?id=<?= htmlspecialchars($ticket_id) ?>">Yazdır / PDF Görüntüle</a>
      <a class="btn btn-outline-secondary" href="tickets.php">Biletlerime Dön</a>
    </div>
  </main>
  <?php
  require_once __DIR__ . '/includes/footer.php'; exit;

} catch (Throwable $e) {
  try { if ($db->inTransaction()) $db->rollBack(); } catch (Throwable $e2) {}
  echo '<main class="container py-4"><div class="alert alert-danger">Satın alma sırasında bir hata oluştu.</div>
        <pre class="small text-muted">'.htmlspecialchars($e->getMessage()).'</pre></main>';
  require_once __DIR__ . '/includes/footer.php'; exit;
}
