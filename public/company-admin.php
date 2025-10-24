<?php

if (session_status() === PHP_SESSION_NONE)
    session_start();

$page_title = "Firma Paneli";
require_once __DIR__ . '/includes/functions.php';
require_role(['company']);
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/navbar.php';


if (!function_exists('set_flash')) {
  function set_flash(string $type, string $msg){ $_SESSION['flash'] = ['type'=>$type,'msg'=>$msg]; }
}
$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);


$h = fn($s) => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
$u = current_user();

$u = current_user(true); 
$company_id = $u['company_id'] ?? null;

try {
    $db->exec('PRAGMA foreign_keys = ON');

  
    try {
        $cols = $db->query("PRAGMA table_info(Coupons)")->fetchAll(PDO::FETCH_ASSOC);
        $hasActive = false;
        foreach ($cols as $c) {
            if (strcasecmp($c['name'], 'is_active') === 0) {
                $hasActive = true;
                break;
            }
        }
        if (!$hasActive)
            $db->exec("ALTER TABLE Coupons ADD COLUMN is_active INTEGER DEFAULT 1");
    } catch (Throwable $e) {
    }

    
    $company_id = $u['company_id'] ?? null;
    if (!$company_id) {
        echo '<main class="container py-4"><div class="alert alert-warning">Bu kullanıcı bir firmaya bağlı değil. Lütfen sistem yöneticisine başvurun.</div></main>';
        require_once __DIR__ . '/includes/footer.php';
        exit;
    }


    $st = $db->prepare('SELECT name FROM Bus_Company WHERE id = ? LIMIT 1');
    $st->execute([$company_id]);
    $company_name = $st->fetchColumn() ?: 'Firma';

    $flash = null;

   
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $action = $_POST['action'] ?? '';

        /* --- Sefer Oluştur (Trips) --- */
        if ($action === 'trip_create') {
            $departure_city = trim($_POST['departure_city'] ?? '');
            $destination_city = trim($_POST['destination_city'] ?? '');
            $departure_time = trim($_POST['departure_time'] ?? '');  
            $arrival_time = trim($_POST['arrival_time'] ?? '');  
            $price = (int) ($_POST['price'] ?? 0);
            $capacity = (int) ($_POST['capacity'] ?? 0);

            if ($departure_city === '' || $destination_city === '' || $departure_time === '' || $price <= 0 || $capacity <= 0) {
                throw new RuntimeException('Sefer bilgileri eksik veya hatalı.');
            }

            $st = $db->prepare('
        INSERT INTO Trips (id, company_id, destination_city, arrival_time, departure_time, departure_city, price, capacity)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
      ');
            $id = uniqid('trip_', true);
            $st->execute([
                $id,
                $company_id,
                $destination_city ?: null,
                $arrival_time ?: null,
                $departure_time,
                $departure_city,
                $price,
                $capacity
            ]);
            $flash = ['type' => 'success', 'msg' => 'Sefer oluşturuldu.'];

          
        } elseif ($action === 'trip_update') {
            $id = $_POST['id'] ?? '';
            if (!$id)
                throw new RuntimeException('Sefer ID eksik.');
           
            $own = $db->prepare('SELECT 1 FROM Trips WHERE id=? AND company_id=?');
            $own->execute([$id, $company_id]);
            if (!$own->fetch())
                throw new RuntimeException('Bu sefer sizin firmanıza ait değil.');

            $departure_city = trim($_POST['departure_city'] ?? '');
            $destination_city = trim($_POST['destination_city'] ?? '');
            $departure_time = trim($_POST['departure_time'] ?? '');
            $arrival_time = trim($_POST['arrival_time'] ?? '');
            $price = (int) ($_POST['price'] ?? 0);
            $capacity = (int) ($_POST['capacity'] ?? 0);
            if ($departure_city === '' || $destination_city === '' || $departure_time === '' || $price <= 0 || $capacity <= 0) {
                throw new RuntimeException('Güncelleme verileri eksik/hatalı.');
            }

            $db->prepare('
        UPDATE Trips
        SET destination_city=?, arrival_time=?, departure_time=?, departure_city=?, price=?, capacity=?
        WHERE id=? AND company_id=?
      ')->execute([
                        $destination_city ?: null,
                        $arrival_time ?: null,
                        $departure_time,
                        $departure_city,
                        $price,
                        $capacity,
                        $id,
                        $company_id
                    ]);
            $flash = ['type' => 'success', 'msg' => 'Sefer güncellendi.'];
 

} elseif ($action === 'trip_delete') {
  $id = $_POST['id'] ?? '';
  if (!$id) throw new RuntimeException('Sefer ID eksik.');

  $db->exec('PRAGMA foreign_keys = ON');
  $db->exec('PRAGMA busy_timeout = 5000');


  $q = $db->prepare('SELECT company_id FROM Trips WHERE id = ?');
  $q->execute([$id]);
  $ownerId = $q->fetchColumn();

  if ($ownerId !== false && $ownerId !== $company_id) {
    throw new RuntimeException('Bu sefer sizin firmanıza ait değil.');
  }

  try {
    $db->beginTransaction();

 
    $db->prepare('
      DELETE FROM Booked_Seats
      WHERE ticket_id IN (SELECT id FROM Tickets WHERE trip_id = ?)
    ')->execute([$id]);


    $db->prepare('DELETE FROM Tickets WHERE trip_id = ?')->execute([$id]);


    $db->prepare('DELETE FROM Trips WHERE id = ?')->execute([$id]);

    $db->commit();
    set_flash('success', 'Sefer ve bağlı kayıtlar silindi (veya zaten yoktu).');
  } catch (Throwable $e) {
    try { if ($db->inTransaction()) $db->rollBack(); } catch (Throwable $e2) {}
    set_flash('danger', 'Sefer silinirken hata: '.$e->getMessage());
  }


  header('Location: company-admin.php');
  exit;

           
        } elseif ($action === 'coupon_create') {
            $code = strtoupper(trim($_POST['code'] ?? ''));
            $discount = (float) ($_POST['discount'] ?? 0);
            $usage_limit = $_POST['usage_limit'] === '' ? null : (int) $_POST['usage_limit'];
            $expire_date = $_POST['expire_date'] ?: null;
            if ($code === '' || $discount <= 0)
                throw new RuntimeException('Kupon kodu ve indirim oranı zorunludur.');

            $st = $db->prepare('
        INSERT INTO Coupons (id, code, discount, usage_limit, expire_date, company_id)
        VALUES (?, ?, ?, ?, ?, ?)
      ');
            $id = uniqid('cpn_', true);
            $st->execute([$id, $code, $discount, $usage_limit, $expire_date, $company_id]);
            $flash = ['type' => 'success', 'msg' => 'Kupon oluşturuldu.'];

          
        } elseif ($action === 'coupon_update') {
            $id = $_POST['id'] ?? '';
            if (!$id)
                throw new RuntimeException('Kupon ID eksik.');
           
            $own = $db->prepare('SELECT 1 FROM Coupons WHERE id=? AND company_id=?');
            $own->execute([$id, $company_id]);
            if (!$own->fetch())
                throw new RuntimeException('Bu kupon sizin firmanıza ait değil.');

            $discount = (float) ($_POST['discount'] ?? 0);
            $usage_limit = $_POST['usage_limit'] === '' ? null : (int) $_POST['usage_limit'];
            $expire_date = $_POST['expire_date'] ?: null;

            $db->prepare('UPDATE Coupons SET discount=?, usage_limit=?, expire_date=? WHERE id=? AND company_id=?')
                ->execute([$discount, $usage_limit, $expire_date, $id, $company_id]);
            $flash = ['type' => 'success', 'msg' => 'Kupon güncellendi.'];

           
        } elseif ($action === 'coupon_delete') {
            $id = $_POST['id'] ?? '';
            if (!$id)
                throw new RuntimeException('Kupon ID eksik.');

            $own = $db->prepare('SELECT 1 FROM Coupons WHERE id=? AND company_id=?');
            $own->execute([$id, $company_id]);
            if (!$own->fetch())
                throw new RuntimeException('Bu kupon sizin firmanıza ait değil.');

            $db->prepare('UPDATE Coupons SET is_active = 0 WHERE id = ? AND company_id = ?')
                ->execute([$id, $company_id]);
            $flash = ['type' => 'success', 'msg' => 'Kupon pasif hale getirildi.'];
        }
    }


    $trips = $db->prepare('
    SELECT id, departure_city, destination_city, departure_time, arrival_time, price, capacity, created_date
    FROM Trips
    WHERE company_id = ?
    ORDER BY departure_time DESC
  ');
    $trips->execute([$company_id]);
    $trips = $trips->fetchAll(PDO::FETCH_ASSOC);

 
    $coupons = $db->prepare('
  SELECT id, code, discount, usage_limit, expire_date, created_at, is_active
  FROM Coupons
  WHERE company_id = ? AND is_active = 1
  ORDER BY created_at DESC
');
    $coupons->execute([$company_id]);

} catch (Throwable $e) {
    echo '<main class="container py-4"><div class="alert alert-danger">Firma paneli hatası: ' . $h($e->getMessage()) . '</div></main>';
    require_once __DIR__ . '/includes/footer.php';
    exit;
}
?>

<main class="container py-4">
    <h1 class="h4 mb-3"><?= $h($company_name) ?> – Firma Paneli</h1>

    <?php if (!empty($flash)): ?>
        <div class="alert alert-<?= $flash['type'] === 'success' ? 'success' : 'danger' ?>"><?= $h($flash['msg']) ?></div>
    <?php endif; ?>

    <!-- SEFERLER (TRIPS) -->
    <section class="mb-5">
        <h2 class="h5 mb-3">Sefer Yönetimi</h2>

        <!-- Oluştur -->
        <div class="border rounded p-3 mb-3">
            <form method="post" class="row g-2 align-items-end">
                <input type="hidden" name="action" value="trip_create">
                <div class="col-sm-3">
                    <label class="form-label">Kalkış Şehri</label>
                    <input type="text" name="departure_city" class="form-control" required>
                </div>
                <div class="col-sm-3">
                    <label class="form-label">Varış Şehri</label>
                    <input type="text" name="destination_city" class="form-control" required>
                </div>
                <div class="col-sm-3">
                    <label class="form-label">Kalkış (YYYY-MM-DD HH:MM)</label>
                    <input type="text" name="departure_time" class="form-control" placeholder="2025-11-05 13:30"
                        required>
                </div>
                <div class="col-sm-3">
                    <label class="form-label">Varış </label>
                    <input type="text" name="arrival_time" class="form-control" placeholder="2025-11-05 18:10" required>
                </div>
                <div class="col-sm-2">
                    <label class="form-label">Fiyat</label>
                    <input type="number" name="price" class="form-control" min="1" required>
                </div>
                <div class="col-sm-2">
                    <label class="form-label">Kapasite</label>
                    <input type="number" name="capacity" class="form-control" min="1" required>
                </div>
                <div class="col-sm-2">
                    <button class="btn btn-primary w-100">Oluştur</button>
                </div>
            </form>
        </div>

  
        <?php if (!$trips): ?>
            <div class="alert alert-info">Bu firmaya ait sefer yok.</div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-sm align-middle">
                    <thead>
                        <tr>
                            <th>Kalkış</th>
                            <th>Varış</th>
                            <th>Kalkış Zamanı</th>
                            <th>Varış</th>
                            <th>Fiyat</th>
                            <th>Kapasite</th>
                            <th>İşlem</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($trips as $t): ?>
                            <tr>
                                <form method="post" class="m-0 d-flex align-items-center">
                                    <input type="hidden" name="action" value="trip_update">
                                    <input type="hidden" name="id" value="<?= $h($t['id']) ?>">
                                    <td><input name="departure_city" class="form-control form-control-sm"
                                            value="<?= $h($t['departure_city']) ?>"></td>
                                    <td><input name="destination_city" class="form-control form-control-sm"
                                            value="<?= $h($t['destination_city']) ?>"></td>
                                    <td><input name="departure_time" class="form-control form-control-sm"
                                            value="<?= $h($t['departure_time']) ?>"></td>
                                    <td><input name="arrival_time" class="form-control form-control-sm"
                                            value="<?= $h($t['arrival_time']) ?>"></td>
                                    <td style="width:100px"><input type="number" name="price"
                                            class="form-control form-control-sm" value="<?= (int) $t['price'] ?>"></td>
                                    <td style="width:120px"><input type="number" name="capacity"
                                            class="form-control form-control-sm" value="<?= (int) $t['capacity'] ?>"></td>
                                    <td class="d-flex gap-1">
                                        <button class="btn btn-outline-secondary btn-sm">Kaydet</button>
                                </form>
                                <form method="post" class="m-0" onsubmit="return confirm('Sefer silinsin mi?');">
                                    <input type="hidden" name="action" value="trip_delete">
                                    <input type="hidden" name="id" value="<?= $h($t['id']) ?>">
                                    <button class="btn btn-outline-danger btn-sm">Sil</button>
                                </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>


    <section>
        <h2 class="h5 mb-3">Firma Kuponları</h2>


        <div class="border rounded p-3 mb-3">
            <form method="post" class="row g-2 align-items-end">
                <input type="hidden" name="action" value="coupon_create">
                <div class="col-sm-3">
                    <label class="form-label">Kod</label>
                    <input type="text" name="code" class="form-control" placeholder="Örn: BUS10" required>
                </div>
                <div class="col-sm-2">
                    <label class="form-label">Oran (%)</label>
                    <input type="number" name="discount" class="form-control" step="0.1" min="1" required>
                </div>
                <div class="col-sm-3">
                    <label class="form-label">Kullanım Limiti</label>
                    <input type="number" name="usage_limit" class="form-control" required>
                </div>
                <div class="col-sm-3">
                    <label class="form-label">Son Kullanma</label>
                    <input type="date" name="expire_date" class="form-control">
                </div>
                <div class="col-sm-1">
                    <button class="btn btn-primary w-100">Ekle</button>
                </div>
            </form>
        </div>


        <?php if (!$coupons): ?>
            <div class="alert alert-info">Bu firmaya ait kupon yok.</div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-sm align-middle">
                    <thead>
                        <tr>
                            <th>Kod</th>
                            <th>Oran</th>
                            <th>Limit</th>
                            <th>Son Kullanma</th>
                            <th>İşlem</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($coupons as $cp): ?>
                            <tr>
                                <form method="post" class="m-0 d-flex align-items-center gap-1">
                                    <input type="hidden" name="action" value="coupon_update">
                                    <input type="hidden" name="id" value="<?= $h($cp['id']) ?>">
                                    <td class="fw-semibold"><?= $h($cp['code']) ?></td>
                                    <td style="width:110px"><input type="number" step="0.1" min="1" name="discount"
                                            class="form-control form-control-sm" value="<?= $h($cp['discount']) ?>"></td>
                                    <td style="width:110px"><input type="number" name="usage_limit"
                                            class="form-control form-control-sm" value="<?= $h($cp['usage_limit']) ?>"></td>
                                    <td><input type="date" name="expire_date" class="form-control form-control-sm"
                                            value="<?= $h(substr((string) $cp['expire_date'], 0, 10)) ?>"></td>
                                    <td class="d-flex gap-1">
                                        <button class="btn btn-outline-secondary btn-sm">Kaydet</button>
                                </form>
                                <form method="post" class="m-0" onsubmit="return confirm('Kupon silinsin mi?');">
                                    <input type="hidden" name="action" value="coupon_delete">
                                    <input type="hidden" name="id" value="<?= $h($cp['id']) ?>">
                                    <button class="btn btn-outline-danger btn-sm">Sil</button>
                                </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>
</main>

<?php require_once __DIR__ . '/includes/footer.php'; ?>