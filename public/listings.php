<?php

if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__.'/includes/functions.php';
require_once __DIR__.'/../config/db.php';
require_once __DIR__.'/includes/header.php';
require_once __DIR__.'/includes/navbar.php';

$from = trim($_GET['from'] ?? '');
$to   = trim($_GET['to'] ?? '');
$date = trim($_GET['date'] ?? ''); 

$sql = "
  SELECT t.*, bc.name AS company
  FROM Trips t
  JOIN Bus_Company bc ON bc.id = t.company_id
  WHERE 1=1
";
$params = [];


if ($from !== '') {
  $sql .= " AND t.departure_city LIKE :from";
  $params[':from'] = "%$from%";
}
if ($to !== '') {
  $sql .= " AND t.destination_city LIKE :to";
  $params[':to'] = "%$to%";
}
if ($date !== '') {
  $sql .= " AND DATE(t.departure_time) = :date";
  $params[':date'] = $date;
}


if ($from === '' && $to === '' && $date === '') {
  $sql .= " AND datetime(t.departure_time) >= datetime('now')";
}

$sql .= " ORDER BY t.departure_time ASC";

$st = $db->prepare($sql);
$st->execute($params);
$trips = $st->fetchAll(PDO::FETCH_ASSOC);
?>

<main class="container py-4">
  <h1 class="h4 mb-3">Seferler</h1>

  <?php if (!$trips): ?>
    <div class="alert alert-warning">Sefer bulunamadı.</div>
  <?php else: ?>
    <div class="vstack gap-3">
      <?php foreach ($trips as $t): ?>
        <div class="border rounded p-3 d-flex justify-content-between align-items-center flex-wrap">
          <div class="me-3">
            <div class="small text-muted">
              <?= htmlspecialchars(date('Y-m-d • H:i', strtotime($t['departure_time']))) ?>
            </div>
            <div class="fw-semibold">
              <?= htmlspecialchars($t['departure_city']) ?> → <?= htmlspecialchars($t['destination_city']) ?>
            </div>
            <div class="small text-muted"><?= htmlspecialchars($t['company']) ?></div>
          </div>
          <div class="text-end">
            <div class="fw-bold">₺<?= number_format((int)$t['price'], 0, ',', '.') ?></div>
            <a class="btn btn-outline-primary btn-sm mt-2" href="trip-details.php?id=<?= htmlspecialchars($t['id']) ?>">Detay</a>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</main>

<?php require_once __DIR__.'/includes/footer.php'; ?>
