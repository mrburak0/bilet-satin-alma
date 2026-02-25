<?php

if (session_status() === PHP_SESSION_NONE)
  session_start();

require_once __DIR__ . '/includes/functions.php';
require_login();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/navbar.php';

$trip_id = $_GET['trip'] ?? '';
$st = $db->prepare('
  SELECT t.*, bc.name AS company
  FROM Trips t
  JOIN Bus_Company bc ON bc.id = t.company_id
  WHERE t.id = ? LIMIT 1
');
$st->execute([$trip_id]);
$trip = $st->fetch(PDO::FETCH_ASSOC);
if (!$trip) {
  echo '<main class="container py-4"><div class="alert alert-danger">Trip not found.</div></main>';
  require_once __DIR__ . '/includes/footer.php';
  exit;
}


$occ = $db->prepare('
  SELECT bs.seat_number
  FROM Booked_Seats bs
  JOIN Tickets tk ON tk.id = bs.ticket_id
  WHERE tk.trip_id = ? AND tk.status = "active"
');
$occ->execute([$trip_id]);
$occupiedRows = $occ->fetchAll(PDO::FETCH_ASSOC);
$occupied = array_map(fn($r) => (int) $r['seat_number'], $occupiedRows);

$u = $_SESSION['user'] ?? null;
$role = $u['role'] ?? null;
if ($role !== 'user') {
  echo '<main class="container py-4"><div class="alert alert-warning">Only regular users can purchase tickets.</div><a class="btn btn-primary" href="index.php">Back to home</a></main>';
  require_once __DIR__ . '/includes/footer.php';
  exit;
}

$capacity = (int) $trip['capacity'];
$price = (int) $trip['price'];
$prettyPrice = '₺' . number_format($price, 0, ',', '.');
?>

<main class="container py-4">
  <h1 class="h4">Buy Ticket</h1>

  <p class="mb-1"><strong><?= htmlspecialchars($trip['departure_city']) ?> →
      <?= htmlspecialchars($trip['destination_city']) ?></strong></p>
  <p class="text-muted mb-3"><?= htmlspecialchars($trip['company']) ?> •
    <?= date('Y-m-d H:i', strtotime($trip['departure_time'])) ?></p>
  <p>Price: <strong><?= $prettyPrice ?></strong></p>

  <div class="row">
    <div class="col-md-6">
      <label class="form-label">Select a Seat (click to select)</label>
      <div id="seatMap" class="d-flex flex-wrap gap-2" style="max-width:520px;">
        <?php for ($n = 1; $n <= $capacity; $n++):
          $isOccupied = in_array($n, $occupied, true);
          $btnClass = $isOccupied ? 'btn-secondary disabled' : 'btn-outline-primary';
          ?>
          <button type="button" class="btn <?= $btnClass ?> seat-btn" data-seat="<?= $n ?>" <?= $isOccupied ? 'aria-disabled="true" disabled' : '' ?> style="min-width:64px;"><?= $n ?></button>
        <?php endfor; ?>
      </div>
      <div class="form-text mt-2">Occupied seats are disabled. After selecting a seat, continue with “Purchase”.</div>
    </div>

    <div class="col-md-6">
      <form id="purchaseForm" method="post" action="purchase-action.php">
        <input type="hidden" name="trip_id" value="<?= htmlspecialchars($trip['id']) ?>">
        <input type="hidden" id="seatInput" name="seat_number" value="">

        <div class="mb-3">
          <label class="form-label">Selected Seat</label>
          <input type="text" id="selectedSeat" class="form-control" readonly placeholder="No seat selected yet">
        </div>

        <div class="mb-3">
          <label class="form-label">Coupon Code (optional)</label>
          <div class="input-group">
            <input type="text" id="couponCode" name="coupon_code" class="form-control" placeholder="Coupon code">
            <button type="button" id="checkCouponBtn" class="btn btn-outline-secondary">Check</button>
          </div>
          <div id="couponFeedback" class="form-text mt-1"></div>
        </div>

        <div class="mb-3">
          <div class="fw-semibold">Total to Pay: <span id="finalPrice"><?= $prettyPrice ?></span></div>
        </div>

        <button type="submit" class="btn btn-success w-100">Purchase</button>
      </form>
    </div>
  </div>
</main>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

<script>
  (function () {
    const seatButtons = document.querySelectorAll('.seat-btn');
    const seatInput = document.getElementById('seatInput');
    const selectedSeat = document.getElementById('selectedSeat');

    seatButtons.forEach(btn => {
      if (btn.disabled) return;
      btn.addEventListener('click', () => {
        document.querySelectorAll('.seat-btn.selected').forEach(s => s.classList.remove('selected', 'btn-primary'));
        btn.classList.add('selected', 'btn-primary');
        seatInput.value = btn.dataset.seat;
        selectedSeat.value = btn.dataset.seat;
      });
    });

    const checkBtn = document.getElementById('checkCouponBtn');
    const couponInput = document.getElementById('couponCode');
    const feedback = document.getElementById('couponFeedback');
    const finalPriceEl = document.getElementById('finalPrice');

    const tripId = <?= json_encode($trip['id']) ?>;
    const basePrice = <?= json_encode($price) ?>;
    function formatPrice(v) {
      return '₺' + v.toLocaleString('tr-TR', { minimumFractionDigits: 0, maximumFractionDigits: 0 });
    }

    checkBtn.addEventListener('click', () => {
      const code = couponInput.value.trim();
      feedback.textContent = 'Checking...';
      feedback.className = 'form-text text-muted';
      if (!code) {
        feedback.textContent = 'Please enter a coupon code.';
        feedback.className = 'form-text text-danger';
        finalPriceEl.textContent = formatPrice(basePrice);
        return;
      }

      fetch('validate-coupon.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ code: code, trip_id: tripId })
      }).then(r => r.json()).then(j => {
        if (j && j.valid) {
          feedback.textContent = 'Coupon is valid. Discount: ' + j.discount + '%.';
          feedback.className = 'form-text text-success';
          finalPriceEl.textContent = formatPrice(j.new_total);
        } else {
          feedback.textContent = j?.message ?? 'Invalid coupon.';
          feedback.className = 'form-text text-danger';
          finalPriceEl.textContent = formatPrice(basePrice);
        }
      }).catch(err => {
        feedback.textContent = 'Server error.';
        feedback.className = 'form-text text-danger';
        finalPriceEl.textContent = formatPrice(basePrice);
      });
    });

    document.getElementById('purchaseForm').addEventListener('submit', function (e) {
      if (!seatInput.value) {
        e.preventDefault();
        alert('Please select a seat first.');
        return;
      }
    });
  })();
</script>