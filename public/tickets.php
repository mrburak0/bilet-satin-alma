<?php
$page_title = "Biletlerim";
require_once __DIR__ . '/includes/functions.php';
require_login();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/navbar.php';


$role = $_SESSION['user']['role'] ?? null;
if ($role === 'admin') {
  header('Location: index.php');
  exit;
}


$u = current_user();

$stBal = $db->prepare('SELECT balance FROM "User" WHERE id = ? LIMIT 1');
$stBal->execute([$u['id']]);
$balance = (int)($stBal->fetchColumn() ?? 0);

$st = $db->prepare("
SELECT 
  tk.id,
  /* Koltuk sayısı * sefer fiyatı => toplam tutar */
  (COUNT(bs.seat_number) * t.price) AS total_price,
  tk.status,
  tk.created_at,
  t.departure_city,
  t.destination_city,
  t.departure_time,
  GROUP_CONCAT(bs.seat_number, ',') AS seats
FROM Tickets tk
JOIN Trips t         ON t.id = tk.trip_id
LEFT JOIN Booked_Seats bs ON bs.ticket_id = tk.id
WHERE tk.user_id = ?
GROUP BY 
  tk.id, tk.status, tk.created_at,
  t.departure_city, t.destination_city, t.departure_time, t.price
ORDER BY t.departure_time DESC
");
$st->execute([$u['id']]);
$rows = $st->fetchAll(PDO::FETCH_ASSOC);
?>

<main class="container py-4">
  <h1 class="h4 mb-3">Biletlerim</h1>

  <div class="border rounded p-3 mb-3 d-flex justify-content-between align-items-center">
    <div>
      <div class="small text-muted">Mevcut Bakiye</div>
      <div class="fs-4 fw-semibold">₺<?= number_format($balance, 2, ',', '.') ?></div>
    </div>
  </div>

  <div class="vstack gap-3">
    <?php if (!$rows): ?>
      <div class="alert alert-info">Bilet bulunamadı.</div>
    <?php else:
      foreach ($rows as $r):
        $depart = strtotime($r['departure_time']);
        $canCancel = ($depart - time()) >= 3600 && $r['status'] === 'active';
        ?>
        <div class="border rounded p-3 d-flex justify-content-between flex-wrap gap-2 align-items-center">
          <div>
            <div class="fw-semibold">
              <?= htmlspecialchars($r['departure_city'] . ' → ' . $r['destination_city']) ?>
            </div>
            <div class="small text-muted">
              <?= date('Y-m-d H:i', $depart) ?> • Koltuk(lar):
              <?= htmlspecialchars($r['seats'] ?? '-') ?>
            </div>
            <div class="small">
              Durum: <strong><?= htmlspecialchars($r['status']) ?></strong> • 
              Tutar: ₺<?= number_format($r['total_price'], 0, ',', '.') ?>
            </div>
          </div>

          <div class="d-flex gap-2">
            <a class="btn btn-outline-primary btn-sm"
               target="_blank"
               href="ticket-print.php?id=<?= htmlspecialchars($r['id']) ?>">
              Bilet Detayı / Yazdır
            </a>

            <form method="post" action="ticket-cancel.php" class="m-0">
              <input type="hidden" name="ticket_id" value="<?= htmlspecialchars($r['id']) ?>">
              <button class="btn btn-outline-danger btn-sm" <?= $canCancel ? '' : 'disabled' ?>>
                İptal Et
              </button>
            </form>
          </div>
        </div>
      <?php endforeach; endif; ?>
  </div>
</main>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
