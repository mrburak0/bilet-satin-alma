<?php
$page_title = "Trip Details";
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
  echo '<main class="container py-4"><div class="alert alert-danger">Trip not found.</div></main>';
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
    Capacity: <strong><?= (int)$trip['capacity'] ?></strong>
    • Price: <strong>₺<?= number_format((int)$trip['price'], 0, ',', '.') ?></strong>
  </p>

  <?php if ($role === 'company'): ?>
    <div class="alert alert-warning mb-3">
      Company admins cannot purchase tickets. Please log in with a user account.
    </div>
    <a class="btn btn-outline-secondary" href="index.php">Return to Home Page</a>

  <?php elseif ($role === 'admin'): ?>
    <div class="alert alert-warning mb-3">
      Tickets cannot be purchased with an admin account.
    </div>
    <a class="btn btn-outline-secondary" href="index.php">Return to Home Page</a>

  <?php elseif ($role === 'user'): ?>
    <a class="btn btn-success" href="purchase.php?trip=<?= urlencode($trip['id']) ?>">Buy Ticket</a>

  <?php else: ?>
    <div class="alert alert-info mb-3">
      Please log in or create an account to purchase a ticket.
    </div>
    <a class="btn btn-primary" href="login.php">Log In / Sign Up</a>
  <?php endif; ?>
</main>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
