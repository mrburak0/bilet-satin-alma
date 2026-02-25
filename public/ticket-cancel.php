<?php
if (session_status() === PHP_SESSION_NONE)
  session_start();

require_once __DIR__ . '/includes/functions.php';
require_login();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/navbar.php';

$role = $_SESSION['user']['role'] ?? null;
if ($role !== 'user') {
  echo '<main class="container py-4"><div class="alert alert-warning">Only regular users can cancel tickets.</div></main>';
  require_once __DIR__ . '/includes/footer.php';
  exit;
}

$user = $_SESSION['user'];
$ticket_id = $_POST['ticket_id'] ?? '';

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || $ticket_id === '') {
  echo '<main class="container py-4"><div class="alert alert-danger">Invalid request.</div></main>';
  require_once __DIR__ . '/includes/footer.php';
  exit;
}

try {
  $db->exec('PRAGMA foreign_keys = ON');

  $st = $db->prepare('
    SELECT tk.id, tk.user_id, tk.status, tk.total_price,
           t.price AS trip_price, t.departure_time
    FROM Tickets tk
    JOIN Trips   t ON t.id = tk.trip_id
    WHERE tk.id = ? AND tk.user_id = ?
    LIMIT 1
  ');
  $st->execute([$ticket_id, $user['id']]);
  $row = $st->fetch(PDO::FETCH_ASSOC);

  if (!$row) {
    echo '<main class="container py-4"><div class="alert alert-danger">Ticket not found.</div></main>';
    require_once __DIR__ . '/includes/footer.php';
    exit;
  }
  if ($row['status'] !== 'active') {
    echo '<main class="container py-4"><div class="alert alert-warning">Only active tickets can be cancelled.</div></main>';
    require_once __DIR__ . '/includes/footer.php';
    exit;
  }

  $departTs = strtotime($row['departure_time']);
  if ($departTs === false) {
    echo '<main class="container py-4"><div class="alert alert-danger">Unable to read the trip date.</div></main>';
    require_once __DIR__ . '/includes/footer.php';
    exit;
  }

  if ($departTs - time() < 3600) {
    echo '<main class="container py-4">
            <div class="alert alert-warning">
              Cancellation is not allowed because there is less than 1 hour until departure.
            </div>
            <a class="btn btn-outline-secondary" href="tickets.php">Back to My Tickets</a>
          </main>';
    require_once __DIR__ . '/includes/footer.php';
    exit;
  }

  $hasTotalPrice = (bool) $db->query("SELECT 1 FROM pragma_table_info('Tickets') WHERE name='total_price'")->fetchColumn();
  if ($hasTotalPrice && $row['total_price'] !== null) {
    $refund = (int) $row['total_price'];
  } else {
    $cnt = $db->prepare('SELECT COUNT(*) FROM Booked_Seats WHERE ticket_id = ?');
    $cnt->execute([$ticket_id]);
    $refund = ((int) $cnt->fetchColumn()) * (int) $row['trip_price'];
  }

  $db->beginTransaction();
  $up = $db->prepare('UPDATE Tickets SET status = "cancelled" WHERE id = ? AND status = "active"');
  $up->execute([$ticket_id]);
  if ($up->rowCount() === 0) {
    $db->rollBack();
    echo '<main class="container py-4"><div class="alert alert-warning">The ticket could not be cancelled. Please try again.</div></main>';
    require_once __DIR__ . '/includes/footer.php';
    exit;
  }

  $db->prepare('UPDATE "User" SET balance = balance + ? WHERE id = ?')->execute([$refund, $user['id']]);
  $db->commit();

  $pretty = '₺' . number_format($refund, 0, ',', '.');
  echo '<main class="container py-4">
          <div class="alert alert-success">Ticket cancellation successful. <strong>' . $pretty . '</strong> has been refunded.</div>
          <a class="btn btn-primary" href="tickets.php">Back to My Tickets</a>
        </main>';
  require_once __DIR__ . '/includes/footer.php';
  exit;

} catch (Throwable $e) {
  try {
    if ($db->inTransaction())
      $db->rollBack();
  } catch (Throwable $e2) {
  }
  echo '<main class="container py-4"><div class="alert alert-danger">An error occurred during cancellation.</div>
        <pre class="small text-muted">' . htmlspecialchars($e->getMessage()) . '</pre></main>';
  require_once __DIR__ . '/includes/footer.php';
  exit;
}
