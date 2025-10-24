<?php
if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__.'/includes/functions.php';
require_role(['user']);               
require_once __DIR__.'/../config/db.php';

$ticketId = $_GET['id'] ?? '';
$u = $_SESSION['user'] ?? null;
if (!$ticketId || !$u) {
  http_response_code(400);
  exit('Geçersiz istek.');
}

try {
  $db->exec('PRAGMA foreign_keys = ON');

  
  $st = $db->prepare("
    SELECT
      tk.id              AS ticket_id,
      tk.user_id         AS user_id,
      tk.status          AS status,
      tk.total_price     AS total_price,
      tk.created_at      AS created_at,
      tr.departure_city,
      tr.destination_city,
      tr.departure_time,
      tr.price           AS trip_price,
      COALESCE(GROUP_CONCAT(bs.seat_number, ', '), '') AS seats
    FROM Tickets tk
    JOIN Trips tr         ON tr.id = tk.trip_id
    LEFT JOIN Booked_Seats bs ON bs.ticket_id = tk.id
    WHERE tk.id = ? AND tk.user_id = ?
    GROUP BY tk.id
    LIMIT 1
  ");
  $st->execute([$ticketId, $u['id']]);
  $row = $st->fetch(PDO::FETCH_ASSOC);
  if (!$row) {
    http_response_code(404);
    exit('Bilet bulunamadı veya yetkiniz yok.');
  }

  $departTs   = strtotime($row['departure_time']);
  $datePretty = $departTs ? date('Y-m-d H:i', $departTs) : htmlspecialchars($row['departure_time']);
  $priceInt   = is_null($row['total_price']) ? (int)$row['trip_price'] : (int)$row['total_price'];
  $priceTry   = '₺' . number_format($priceInt, 0, ',', '.');
  $seatsStr   = $row['seats'] ?: '—';

  $h = static function($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); };
} catch (Throwable $e) {
  http_response_code(500);
  exit('Bilet görüntüleme hatası: '.$e->getMessage());
}
?>
<!doctype html>
<html lang="tr">
<head>
  <meta charset="utf-8">
  <title>Bilet Yazdır – <?= $h($row['ticket_id']) ?></title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <style>
    :root {
      --text:#111; --muted:#666; --line:#e5e7eb; --primary:#0d6efd;
    }
    *{ box-sizing:border-box; }
    body{
      margin:0; color:var(--text); font:14px/1.45 -apple-system,BlinkMacSystemFont,Segoe UI,Roboto,Helvetica,Arial,"Apple Color Emoji","Segoe UI Emoji";
      background:#f6f8fb;
    }
    .toolbar{
      position:sticky; top:0; background:#fff; border-bottom:1px solid var(--line);
      padding:10px; display:flex; gap:8px; align-items:center; z-index:10;
    }
    .toolbar .btn{
      appearance:none; border:1px solid var(--line); background:#fff; padding:8px 12px; border-radius:8px; cursor:pointer;
    }
    .toolbar .btn.primary{ background:var(--primary); border-color:var(--primary); color:#fff; }
    .container{ max-width:900px; margin:24px auto; padding:0 16px; }
    .sheet{
      background:#fff; border:1px solid var(--line); border-radius:12px; padding:20mm; 
      box-shadow:0 10px 30px rgba(0,0,0,.06);
    }
    h1{ font-size:22px; margin:0 0 12px; }
    .muted{ color:var(--muted); }
    .row{ display:flex; gap:24px; }
    .col{ flex:1; }
    .kv{ margin:8px 0; }
    .k{ width:130px; display:inline-block; color:var(--muted); }
    .strong{ font-weight:700; }
    .hr{ border-top:1px dashed var(--line); margin:14px 0; }
    .footer{ margin-top:12px; font-size:12px; color:var(--muted); }
    .badge{ display:inline-block; padding:2px 8px; border-radius:999px; background:#eef; font-size:11px; margin-left:8px; }

    /* Yazdırma stilleri */
    @media print {
      body{ background:#fff; }
      .toolbar{ display:none; }
      .container{ margin:0; padding:0; }
      .sheet{ border:none; box-shadow:none; border-radius:0; padding:18mm; }
      @page { size: A4 portrait; margin: 12mm 14mm; }
    }
  </style>
</head>
<body>
  <div class="toolbar">
    <button class="btn primary" onclick="window.print()">Yazdır / PDF Olarak Kaydet</button>
    <a class="btn" href="tickets.php">Biletlerime Dön</a>
  </div>

  <div class="container">
    <div class="sheet">
      <h1>Bilet Özeti <span class="badge"><?= $h($row['status']) ?></span></h1>
      <div class="row">
        <div class="col">
          <div class="kv"><span class="k">Bilet No</span> <span class="strong"><?= $h($row['ticket_id']) ?></span></div>
          <div class="kv"><span class="k">Sefer</span> <?= $h($row['departure_city']) ?> → <?= $h($row['destination_city']) ?></div>
          <div class="kv"><span class="k">Tarih/Saat</span> <?= $h($datePretty) ?></div>
          <div class="kv"><span class="k">Koltuk(lar)</span> <?= $h($seatsStr) ?></div>
        </div>
        <div class="col" style="text-align:right">
          <div class="kv"><span class="k">Tutar</span> <span class="strong"><?= $h($priceTry) ?></span></div>
          <div class="kv muted">Fiyat sunucu tarafındaki sefer kaydından hesaplanır.</div>
          <div class="kv"><span class="k">Oluşturulma</span> <?= $h($row['created_at']) ?></div>
        </div>
      </div>
      <div class="hr"></div>
      <div class="muted">Bu sayfa yazdırmaya uygundur. Yazdırırken hedeften <strong>“PDF olarak kaydet”</strong> seçebilirsiniz.</div>

      <div class="footer">
        © <?= date('Y') ?> Bilet Platformu — Bu çıktı bilgilendirme amaçlıdır.
      </div>
    </div>
  </div>
</body>
</html>
