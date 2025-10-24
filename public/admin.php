<?php

if (session_status() === PHP_SESSION_NONE)
  session_start();

$page_title = "Yönetim Paneli";
require_once __DIR__ . '/includes/functions.php';
require_role(['admin']);
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/navbar.php';

$h = fn($s) => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');

try {
  $db->exec('PRAGMA foreign_keys = ON');
  $flash = null;


  if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'company_create') {
      $name = trim($_POST['name'] ?? '');
      if ($name === '')
        throw new RuntimeException('Firma adı boş olamaz.');
      $id = uniqid('cmp_', true);
      $st = $db->prepare('INSERT INTO Bus_Company (id, name) VALUES (?, ?)');
      $st->execute([$id, $name]);
      $flash = ['type' => 'success', 'msg' => 'Yeni firma oluşturuldu.'];

    } elseif ($action === 'company_update') {
      $id = $_POST['id'] ?? '';
      $name = trim($_POST['name'] ?? '');
      if (!$id || !$name)
        throw new RuntimeException('Veriler eksik.');
      $db->prepare('UPDATE Bus_Company SET name = ? WHERE id = ?')->execute([$name, $id]);
      $flash = ['type' => 'success', 'msg' => 'Firma güncellendi.'];


    } elseif ($action === 'company_delete') {
      $id = $_POST['id'] ?? '';
      if (!$id)
        throw new RuntimeException('Firma ID eksik.');
      $db->prepare('DELETE FROM Bus_Company WHERE id = ?')->execute([$id]);
      $flash = ['type' => 'success', 'msg' => 'Firma silindi.'];


    } elseif ($action === 'company_admin_assign') {
      $email = trim($_POST['email'] ?? '');
      $company_id = $_POST['company_id'] ?? '';
      if ($email === '' || $company_id === '')
        throw new RuntimeException('Firma ve e-posta gerekli.');

 
      $st = $db->prepare('SELECT id FROM User WHERE email = ? LIMIT 1');
      $st->execute([$email]);
      $uid = $st->fetchColumn();

      if (!$uid) {
        throw new RuntimeException('Kullanıcı bulunamadı.');
      }

      $db->prepare('UPDATE User SET role = "company", company_id = ? WHERE id = ?')
        ->execute([$company_id, $uid]);
      $flash = ['type' => 'success', 'msg' => 'Kullanıcı firma admini olarak atandı.'];

    } elseif ($action === 'coupon_create') {
      $code = strtoupper(trim($_POST['code'] ?? ''));
      $discount = (float) ($_POST['discount'] ?? 0);
      $limit = $_POST['usage_limit'] === '' ? null : (int) $_POST['usage_limit'];
      $expire = $_POST['expire_date'] ?: null;
      $company_id = $_POST['company_id'] ?: null; 

      if ($code === '' || $discount <= 0) {
        $flash = ['type' => 'danger', 'msg' => 'Kupon kodu ve indirim oranı zorunludur.'];
        header('Location: admin.php?m=err');
        exit;
      }


      $st = $db->prepare('SELECT 1 FROM Coupons WHERE code = ? LIMIT 1');
      $st->execute([$code]);
      if ($st->fetchColumn()) {
        $flash = ['type' => 'danger', 'msg' => 'Bu kupon kodu zaten kayıtlı. Farklı bir kod deneyin.'];
        header('Location: admin.php?m=dup');
        exit;
      }

      $id = uniqid('cpn_', true);
      $st = $db->prepare('INSERT INTO Coupons (id, code, discount, usage_limit, expire_date, company_id)
                      VALUES (?, ?, ?, ?, ?, ?)');
      $st->execute([$id, $code, $discount, $limit, $expire, $company_id]);

      $flash = ['type' => 'success', 'msg' => 'Kupon oluşturuldu.'];
      header('Location: admin.php?m=ok');
      exit;



    } elseif ($action === 'coupon_update') {
      $id = $_POST['id'] ?? '';
      $discount = (float) ($_POST['discount'] ?? 0);
      $limit = $_POST['usage_limit'] === '' ? null : (int) $_POST['usage_limit'];
      $expire = $_POST['expire_date'] ?: null;
      if (!$id)
        throw new RuntimeException('Kupon ID eksik.');
      $db->prepare('UPDATE Coupons SET discount=?, usage_limit=?, expire_date=? WHERE id=?')
        ->execute([$discount, $limit, $expire, $id]);
      $flash = ['type' => 'success', 'msg' => 'Kupon güncellendi.'];

     
    } elseif ($action === 'coupon_delete') {
      $id = $_POST['id'] ?? '';
      if (!$id)
        throw new RuntimeException('Kupon ID eksik.');

    
      $db->prepare('UPDATE Coupons
                SET is_active = 0,
                    usage_limit = COALESCE(usage_limit, 0)
                WHERE id = ?')
        ->execute([$id]);

      $flash = ['type' => 'success', 'msg' => 'Kupon silindi.'];
    }

  }


  $companies = $db->query('SELECT id, name, logo_path, created_at FROM Bus_Company ORDER BY name')->fetchAll(PDO::FETCH_ASSOC);
  $admins = $db->query('SELECT id, email, full_name, role, company_id FROM User WHERE role = "company"')->fetchAll(PDO::FETCH_ASSOC);
  $coupons = $db->query('SELECT * FROM Coupons WHERE is_active = 1 ORDER BY created_at DESC')
    ->fetchAll(PDO::FETCH_ASSOC);


} catch (Throwable $e) {
  echo '<main class="container py-4"><div class="alert alert-danger">Hata: ' . $h($e->getMessage()) . '</div></main>';
  require_once __DIR__ . '/includes/footer.php';
  exit;
}
?>

<main class="container py-4">
  <h1 class="h4 mb-3">Yönetim Paneli</h1>

  <?php if (!empty($flash)): ?>
    <div class="alert alert-<?= $flash['type'] === 'success' ? 'success' : 'danger' ?>">
      <?= $h($flash['msg']) ?>
    </div>
  <?php endif; ?>

 
  <section class="mb-5">
    <h2 class="h5 mb-3">Otobüs Firmaları</h2>

    <form method="post" class="row g-2 mb-3">
      <input type="hidden" name="action" value="company_create">
      <div class="col-sm-6">
        <input type="text" name="name" class="form-control" placeholder="Yeni firma adı..." required>
      </div>
      <div class="col-sm-2">
        <button class="btn btn-primary w-100">Ekle</button>
      </div>
    </form>

    <?php foreach ($companies as $c): ?>
      <div class="border rounded p-3 d-flex justify-content-between align-items-center flex-wrap mb-2">
        <div>
          <strong><?= $h($c['name']) ?></strong>
          <div class="small text-muted"><?= $h($c['created_at']) ?></div>
        </div>
        <div class="d-flex gap-2">
          <form method="post" class="d-flex m-0 gap-1">
            <input type="hidden" name="action" value="company_update">
            <input type="hidden" name="id" value="<?= $h($c['id']) ?>">
            <input type="text" name="name" class="form-control form-control-sm" value="<?= $h($c['name']) ?>">
            <button class="btn btn-outline-secondary btn-sm">Kaydet</button>
          </form>
          <form method="post" class="m-0" onsubmit="return confirm('Silinsin mi?');">
            <input type="hidden" name="action" value="company_delete">
            <input type="hidden" name="id" value="<?= $h($c['id']) ?>">
            <button class="btn btn-outline-danger btn-sm">Sil</button>
          </form>
        </div>
      </div>
    <?php endforeach; ?>
  </section>


  <section class="mb-5">
    <h2 class="h5 mb-3">Firma Admin Atama</h2>

    <form method="post" class="row g-2 mb-3">
      <input type="hidden" name="action" value="company_admin_assign">
      <div class="col-sm-5">
        <input type="email" name="email" class="form-control" placeholder="Kullanıcı e-postası" required>
      </div>
      <div class="col-sm-5">
        <select name="company_id" class="form-select" required>
          <option value="">Firma Seç...</option>
          <?php foreach ($companies as $c): ?>
            <option value="<?= $h($c['id']) ?>"><?= $h($c['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-sm-2">
        <button class="btn btn-primary w-100">Ata</button>
      </div>
    </form>

    <div class="table-responsive">
      <table class="table table-sm">
        <thead>
          <tr>
            <th>Ad Soyad</th>
            <th>E-posta</th>
            <th>Firma</th>
            <th>Rol</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($admins as $a):
            $firma = '';
            foreach ($companies as $c)
              if ($c['id'] === $a['company_id'])
                $firma = $c['name'];
            ?>
            <tr>
              <td><?= $h($a['full_name'] ?? '-') ?></td>
              <td><?= $h($a['email']) ?></td>
              <td><?= $h($firma ?: '-') ?></td>
              <td><?= $h($a['role']) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </section>


  <section>
    <h2 class="h5 mb-3">İndirim Kuponları</h2>

    <form method="post" class="row g-2 mb-3">
      <input type="hidden" name="action" value="coupon_create">
      <div class="col-sm-2"><input type="text" name="code" class="form-control" placeholder="KOD" required></div>
      <div class="col-sm-2"><input type="number" name="discount" class="form-control" step="0.1" placeholder="%"
          required></div>
      <div class="col-sm-2"><input type="number" name="usage_limit" class="form-control" placeholder="Limit" required>
      </div>
      <div class="col-sm-3"><input type="date" name="expire_date" class="form-control" required></div>
      <div class="col-sm-2">
        <select name="company_id" class="form-select" required>
          <option value="">Tüm Firmalar</option>
          <?php foreach ($companies as $c): ?>
            <option value="<?= $h($c['id']) ?>"><?= $h($c['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-sm-1"><button class="btn btn-primary w-100">Ekle</button></div>
    </form>

    <div class="table-responsive">
      <table class="table table-sm align-middle">
        <thead>
          <tr>
            <th>Kod</th>
            <th>Oran</th>
            <th>Limit</th>
            <th>Son Kullanma</th>
            <th>Firma</th>
            <th>İşlem</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($coupons as $cp):
            $firma = '';
            foreach ($companies as $c)
              if ($c['id'] === $cp['company_id'])
                $firma = $c['name'];
            ?>
            <tr>
              <td><?= $h($cp['code']) ?></td>
              <td>%<?= $h($cp['discount']) ?></td>
              <td><?= $h($cp['usage_limit'] ?: '∞') ?></td>
              <td><?= $h($cp['expire_date'] ?? '-') ?></td>
              <td><?= $h($firma ?: 'Tümü') ?></td>
              <td class="d-flex gap-1">
                <form method="post" class="d-flex m-0 gap-1">
                  <input type="hidden" name="action" value="coupon_update">
                  <input type="hidden" name="id" value="<?= $h($cp['id']) ?>">
                  <input type="number" name="discount" value="<?= $h($cp['discount']) ?>"
                    class="form-control form-control-sm" step="0.1" style="width:80px">
                  <input type="number" name="usage_limit" value="<?= $h($cp['usage_limit']) ?>"
                    class="form-control form-control-sm" style="width:80px">
                  <input type="date" name="expire_date" value="<?= $h(substr($cp['expire_date'], 0, 10)) ?>"
                    class="form-control form-control-sm">
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
  </section>
</main>

<?php require_once __DIR__ . '/includes/footer.php'; ?>