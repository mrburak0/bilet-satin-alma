<?php
$page_title = "Sefer Detayı";
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/navbar.php';
require_once __DIR__ . '/../config/db.php';

$u    = $_SESSION['user'] ?? null;
$role = $u['role'] ?? null;

$id = $_GET['id'] ?? '';

$st = $db->prepare("
  SELECT t.*, bc.name AS company
  FROM Trips t
  JOIN Bus_Company bc ON bc.id = t.company_id
  WHERE t.id = ?
  LIMIT 1
");
$st->execute([$id]);
$trip = $st->fetch(PDO::FETCH_ASSOC);

if (!$trip) {
  echo '<main class="container py-4"><div class="alert alert-danger">Sefer yok.</div></main>';
  require_once __DIR__ . '/includes/footer.php';
  exit;
}

$occ = $db->prepare("
  SELECT bs.seat_number
  FROM Booked_Seats bs
  JOIN Tickets tk ON tk.id = bs.ticket_id
  WHERE tk.trip_id = ? AND tk.status = 'active'
");
$occ->execute([$id]);
$occupied = array_map(fn($r) => (int)$r['seat_number'], $occ->fetchAll(PDO::FETCH_ASSOC));
?>
<main class="container py-4">
  <h1 class="h4">
    <?= htmlspecialchars($trip['departure_city']) ?> → <?= htmlspecialchars($trip['destination_city']) ?>
  </h1>
  <p class="text-muted">
    <?= htmlspecialchars($trip['company']) ?> • <?= date('Y-m-d H:i', strtotime($trip['departure_time'])) ?>
  </p>
  <p>
    Kapasite: <strong><?= (int)$trip['capacity'] ?></strong>
    • Fiyat: <strong>₺<?= number_format((int)$trip['price'], 0, ',', '.') ?></strong>
  </p>

  <?php if ($role === 'company'): ?>
    <div class="alert alert-warning mb-3">
      Firma yetkilileri bilet satın alamaz. Lütfen kullanıcı hesabıyla giriş yapın.
    </div>
    <a class="btn btn-outline-secondary" href="index.php">Ana Sayfaya Dön</a>

  <?php elseif ($role === 'admin'): ?>
    <div class="alert alert-warning mb-3">
      Admin hesabıyla bilet satın alınamaz.
    </div>
    <a class="btn btn-outline-secondary" href="index.php">Ana Sayfaya Dön</a>

  <?php elseif ($role === 'user'): ?>
    <a class="btn btn-success" href="purchase.php?trip=<?= urlencode($trip['id']) ?>">Bilet Satın Al</a>

  <?php else: ?>
    <div class="alert alert-info mb-3">
      Bilet satın almak için lütfen giriş yapın veya hesap oluşturun.
    </div>
    <a class="btn btn-primary" href="login.php">Giriş Yap / Kayıt Ol</a>
  <?php endif; ?>
</main>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
