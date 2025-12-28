<?php

if (session_status() === PHP_SESSION_NONE)
  session_start();

$page_title = "Admin Panel";
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
        throw new RuntimeException('Company name cannot be empty.');
      $id = uniqid('cmp_', true);
      $st = $db->prepare('INSERT INTO Bus_Company (id, name) VALUES (?, ?)');
      $st->execute([$id, $name]);
      $flash = ['type' => 'success', 'msg' => 'New company has been created.'];

    } elseif ($action === 'company_update') {
      $id = $_POST['id'] ?? '';
      $name = trim($_POST['name'] ?? '');
      if (!$id || !$name)
        throw new RuntimeException('Missing data.');
      $db->prepare('UPDATE Bus_Company SET name = ? WHERE id = ?')->execute([$name, $id]);
      $flash = ['type' => 'success', 'msg' => 'Company updated.'];

    } elseif ($action === 'company_delete') {
      $id = $_POST['id'] ?? '';
      if (!$id)
        throw new RuntimeException('Missing company ID.');
      $db->prepare('DELETE FROM Bus_Company WHERE id = ?')->execute([$id]);
      $flash = ['type' => 'success', 'msg' => 'Company deleted.'];

    } elseif ($action === 'company_admin_assign') {
      $email = trim($_POST['email'] ?? '');
      $company_id = $_POST['company_id'] ?? '';
      if ($email === '' || $company_id === '')
        throw new RuntimeException('Company and email are required.');

      $st = $db->prepare('SELECT id FROM User WHERE email = ? LIMIT 1');
      $st->execute([$email]);
      $uid = $st->fetchColumn();
      if (!$uid)
        throw new RuntimeException('User not found.');

      $db->prepare('UPDATE User SET role = "company", company_id = ? WHERE id = ?')->execute([$company_id, $uid]);
      $flash = ['type' => 'success', 'msg' => 'User has been assigned as a company admin.'];

    } elseif ($action === 'coupon_create') {
      $code = strtoupper(trim($_POST['code'] ?? ''));
      $discount = (float) ($_POST['discount'] ?? 0);
      $limit = ($_POST['usage_limit'] === '' ? null : (int) $_POST['usage_limit']);
      $expire = ($_POST['expire_date'] ?: null);
      $company_id = ($_POST['company_id'] ?: null);

      if ($code === '' || $discount <= 0) {
        $flash = ['type' => 'danger', 'msg' => 'Coupon code and discount rate are required.'];
        header('Location: admin.php?m=err');
        exit;
      }

      $st = $db->prepare('SELECT 1 FROM Coupons WHERE code = ? LIMIT 1');
      $st->execute([$code]);
      if ($st->fetchColumn()) {
        $flash = ['type' => 'danger', 'msg' => 'This coupon code already exists. Please try a different code.'];
        header('Location: admin.php?m=dup');
        exit;
      }

      $id = uniqid('cpn_', true);
      $st = $db->prepare('INSERT INTO Coupons (id, code, discount, usage_limit, expire_date, company_id) VALUES (?, ?, ?, ?, ?, ?)');
      $st->execute([$id, $code, $discount, $limit, $expire, $company_id]);

      $flash = ['type' => 'success', 'msg' => 'Coupon created.'];
      header('Location: admin.php?m=ok');
      exit;

    } elseif ($action === 'coupon_update') {
      $id = $_POST['id'] ?? '';
      $discount = (float) ($_POST['discount'] ?? 0);
      $limit = ($_POST['usage_limit'] === '' ? null : (int) $_POST['usage_limit']);
      $expire = ($_POST['expire_date'] ?: null);
      if (!$id)
        throw new RuntimeException('Missing coupon ID.');

      $db->prepare('UPDATE Coupons SET discount=?, usage_limit=?, expire_date=? WHERE id=?')->execute([$discount, $limit, $expire, $id]);
      $flash = ['type' => 'success', 'msg' => 'Coupon updated.'];

    } elseif ($action === 'coupon_delete') {
      $id = $_POST['id'] ?? '';
      if (!$id)
        throw new RuntimeException('Missing coupon ID.');

      $db->prepare('UPDATE Coupons SET is_active = 0, usage_limit = COALESCE(usage_limit, 0) WHERE id = ?')->execute([$id]);
      $flash = ['type' => 'success', 'msg' => 'Coupon deleted.'];
    }
  }

  $companies = $db->query('SELECT id, name, logo_path, created_at FROM Bus_Company ORDER BY name')->fetchAll(PDO::FETCH_ASSOC);
  $admins = $db->query('SELECT id, email, full_name, role, company_id FROM User WHERE role = "company"')->fetchAll(PDO::FETCH_ASSOC);
  $coupons = $db->query('SELECT * FROM Coupons WHERE is_active = 1 ORDER BY created_at DESC')->fetchAll(PDO::FETCH_ASSOC);

} catch (Throwable $e) {
  echo '<main class="container py-4"><div class="alert alert-danger">Error: ' . $h($e->getMessage()) . '</div></main>';
  require_once __DIR__ . '/includes/footer.php';
  exit;
}
?>

<style>
  body {
    background: radial-gradient(1200px 700px at 20% 10%, rgba(13, 110, 253, .22), transparent 55%),
      radial-gradient(900px 600px at 85% 30%, rgba(0, 170, 255, .16), transparent 60%),
      linear-gradient(180deg, #07162d 0%, #050e1e 100%) !important;
  }

  .admin-shell {
    color: rgba(255, 255, 255, .92);
  }

  .admin-shell .text-muted {
    color: rgba(255, 255, 255, .70) !important;
  }

  .admin-shell .table {
    color: rgba(255, 255, 255, .92);
  }

  .admin-shell .table thead th {
    color: rgba(255, 255, 255, .78);
    border-color: rgba(255, 255, 255, .10);
  }

  .admin-shell .table td {
    border-color: rgba(255, 255, 255, .08);
  }

  .admin-shell .border,
  .admin-shell .border-top,
  .admin-shell .border-bottom {
    border-color: rgba(255, 255, 255, .10) !important;
  }

  .dark-card {
    border: 1px solid rgba(255, 255, 255, .10);
    border-radius: 16px;
    background: rgba(8, 20, 42, .72);
    box-shadow: 0 10px 30px rgba(0, 0, 0, .35);
    backdrop-filter: blur(8px);
  }

  .console-wrap {
    border: 1px solid rgba(13, 110, 253, .22);
    border-radius: 18px;
    background: linear-gradient(135deg, rgba(13, 110, 253, .10), rgba(0, 0, 0, .18));
    padding: 16px;
  }

  .console-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    flex-wrap: wrap;
    margin-bottom: 10px;
  }

  .console-head h2 {
    margin: 0;
    font-size: 1.05rem;
    font-weight: 900;
    letter-spacing: .2px;
  }

  .chip {
    border-radius: 999px;
    padding: 7px 10px;
    font-weight: 900;
    font-size: 12px;
    letter-spacing: .25px;
    border: 1px solid rgba(255, 255, 255, .14);
    background: rgba(13, 110, 253, .12);
    color: #b9d6ff;
    white-space: nowrap;
  }

  .grid {
    display: grid;
    grid-template-columns: 1.05fr .95fr;
    gap: 12px;
  }

  @media (max-width: 992px) {
    .grid {
      grid-template-columns: 1fr;
    }
  }

  .kv {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 10px 0;
    border-bottom: 1px dashed rgba(255, 255, 255, .14);
    gap: 10px;
  }

  .kv:last-child {
    border-bottom: none;
  }

  .k {
    color: rgba(255, 255, 255, .68);
    font-size: 13px;
  }

  .v {
    font-weight: 900;
    font-size: 13px;
    color: rgba(255, 255, 255, .92);
  }

  .pill {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    border-radius: 999px;
    padding: 7px 10px;
    font-weight: 900;
    font-size: 12px;
    border: 1px solid rgba(255, 255, 255, .14);
    background: rgba(255, 255, 255, .06);
    color: rgba(255, 255, 255, .92);
  }

  .dot {
    width: 10px;
    height: 10px;
    border-radius: 999px;
    background: #20c997;
    box-shadow: 0 0 0 4px rgba(32, 201, 151, .15);
  }

  .dot.warn {
    background: #ffc107;
    box-shadow: 0 0 0 4px rgba(255, 193, 7, .16);
  }

  .dot.danger {
    background: #ff4d6d;
    box-shadow: 0 0 0 4px rgba(255, 77, 109, .16);
  }

  .mini {
    font-size: 12px;
    color: rgba(255, 255, 255, .68);
    margin-top: 8px;
  }

  .log {
    max-height: 280px;
    overflow: auto;
  }

  .rowitem {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 10px;
    padding: 10px 0;
    border-bottom: 1px solid rgba(255, 255, 255, .08);
  }

  .rowitem:last-child {
    border-bottom: none;
  }

  .msg {
    font-weight: 900;
    color: rgba(255, 255, 255, .92);
  }

  .meta {
    color: rgba(255, 255, 255, .65);
    font-size: 12px;
  }

  .btn-outline-secondary,
  .btn-outline-danger {
    border-color: rgba(255, 255, 255, .22) !important;
    color: rgba(255, 255, 255, .92) !important;
  }

  .btn-outline-secondary:hover {
    background: rgba(255, 255, 255, .10) !important;
  }

  .btn-outline-danger:hover {
    background: rgba(255, 77, 109, .18) !important;
    border-color: rgba(255, 77, 109, .40) !important;
  }

  .form-control,
  .form-select {
    background: rgba(255, 255, 255, .06) !important;
    border-color: rgba(255, 255, 255, .16) !important;
    color: rgba(255, 255, 255, .92) !important;
  }

  .form-control::placeholder {
    color: rgba(255, 255, 255, .55) !important;
  }

  .form-control:focus,
  .form-select:focus {
    box-shadow: 0 0 0 .2rem rgba(13, 110, 253, .18) !important;
  }

  .alert {
    border-radius: 14px;
    border: 1px solid rgba(255, 255, 255, .12);
  }

  .section-title {
    font-weight: 900;
    letter-spacing: .2px;
  }

  /* --- FIX: tables should not look white in dark admin --- */
  .admin-shell .table {
    --bs-table-bg: transparent;
    --bs-table-color: rgba(255, 255, 255, .92);
    --bs-table-border-color: rgba(255, 255, 255, .10);
  }

  .admin-shell .table tbody tr:hover>* {
    background-color: rgba(13, 110, 253, .08) !important;
  }

  /* tighter inputs inside table */
  .admin-shell .table .form-control,
  .admin-shell .table .form-select {
    padding: .25rem .5rem;
    font-size: .875rem;
  }

  /* keep action area neat */
  .admin-shell .td-actions {
    white-space: nowrap;
  }

  .admin-shell .td-actions .form-control {
    min-width: 90px;
  }

  .admin-shell .td-actions input[type="date"] {
    min-width: 170px;
  }
</style>

<main class="container py-4 admin-shell">
  <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
    <div>
      <h1 class="h4 m-0 section-title">Admin Panel</h1>
      <div class="text-muted small">Dark mode console • private access</div>
    </div>
    <span class="chip" id="liveChip">SECURITY CONSOLE • LIVE</span>
  </div>

  <?php if (!empty($flash)): ?>
    <div class="alert alert-<?= $flash['type'] === 'success' ? 'success' : 'danger' ?>">
      <?= $h($flash['msg']) ?>
    </div>
  <?php endif; ?>

  <section class="console-wrap mb-4">
    <div class="console-head">
      <h2>🛡️ Security & Fraud Monitor</h2>
      <div class="d-flex gap-2 flex-wrap">
        <span class="chip" id="threatChip">Threat: LOW</span>
        <button class="btn btn-sm btn-outline-secondary" type="button" id="btnAddDemo">Add Demo Event</button>
        <button class="btn btn-sm btn-outline-danger" type="button" id="btnClear">Clear</button>
      </div>
    </div>

    <div class="grid">
      <div class="dark-card p-3">
        <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-2">
          <div>
            <div class="fw-bold">System Health</div>
            <div class="text-muted small">Quick indicators for admin review</div>
          </div>
          <span class="pill"><span class="dot" id="stateDot"></span><span id="stateText">Stable</span></span>
        </div>

        <div class="kv">
          <div class="k">Failed Login Attempts (last 24h)</div>
          <div class="v" id="mFailed">0</div>
        </div>
        <div class="kv">
          <div class="k">Possible DDoS Requests (last 24h)</div>
          <div class="v" id="mDdos">0</div>
        </div>
        <div class="kv">
          <div class="k">Phishing / Fake Page Detections (last 24h)</div>
          <div class="v" id="mPhish">0</div>
        </div>
        <div class="kv">
          <div class="k">Suspicious Admin Actions (last 24h)</div>
          <div class="v" id="mAdmin">0</div>
        </div>

        <div class="mini" id="secTip"></div>
      </div>

      <div class="dark-card p-3">
        <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-2">
          <div>
            <div class="fw-bold">Recent Security Events</div>
            <div class="text-muted small">Stored in your browser for demo/presentation</div>
          </div>
        </div>

        <div class="log" id="logBox"></div>
        <div class="mini">Events are generated locally. No server logging is enabled in this demo view.</div>
      </div>
    </div>
  </section>

  <section class="mb-5">
    <h2 class="h5 mb-3 section-title">Bus Companies</h2>

    <form method="post" class="row g-2 mb-3 js-audit" data-type="admin_action" data-sev="info"
      data-msg="Company create attempt">
      <input type="hidden" name="action" value="company_create">
      <div class="col-sm-6">
        <input type="text" name="name" class="form-control" placeholder="New company name..." required>
      </div>
      <div class="col-sm-2">
        <button class="btn btn-primary w-100">Add</button>
      </div>
    </form>

    <?php foreach ($companies as $c): ?>
      <div class="dark-card p-3 d-flex justify-content-between align-items-center flex-wrap mb-2">
        <div>
          <strong><?= $h($c['name']) ?></strong>
          <div class="small text-muted"><?= $h($c['created_at']) ?></div>
        </div>
        <div class="d-flex gap-2">
          <form method="post" class="d-flex m-0 gap-1 js-audit" data-type="admin_action" data-sev="info"
            data-msg="Company update attempt">
            <input type="hidden" name="action" value="company_update">
            <input type="hidden" name="id" value="<?= $h($c['id']) ?>">
            <input type="text" name="name" class="form-control form-control-sm" value="<?= $h($c['name']) ?>">
            <button class="btn btn-outline-secondary btn-sm">Save</button>
          </form>
          <form method="post" class="m-0 js-audit" data-type="admin_action" data-sev="warning"
            data-msg="Company delete attempt" onsubmit="return confirm('Delete it?');">
            <input type="hidden" name="action" value="company_delete">
            <input type="hidden" name="id" value="<?= $h($c['id']) ?>">
            <button class="btn btn-outline-danger btn-sm">Delete</button>
          </form>
        </div>
      </div>
    <?php endforeach; ?>
  </section>

  <section class="mb-5">
    <h2 class="h5 mb-3 section-title">Assign Company Admin</h2>

    <form method="post" class="row g-2 mb-3 js-audit" data-type="admin_action" data-sev="info"
      data-msg="Company admin assignment attempt">
      <input type="hidden" name="action" value="company_admin_assign">
      <div class="col-sm-5">
        <input type="email" name="email" class="form-control" placeholder="User email" required>
      </div>
      <div class="col-sm-5">
        <select name="company_id" class="form-select" required>
          <option value="">Select a company...</option>
          <?php foreach ($companies as $c): ?>
            <option value="<?= $h($c['id']) ?>"><?= $h($c['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-sm-2">
        <button class="btn btn-primary w-100">Assign</button>
      </div>
    </form>

    <div class="table-responsive dark-card p-2">
      <table class="table table-sm mb-0">
        <thead>
          <tr>
            <th>Full Name</th>
            <th>Email</th>
            <th>Company</th>
            <th>Role</th>
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
    <h2 class="h5 mb-3 section-title">Discount Coupons</h2>

    <form method="post" class="row g-2 mb-3 js-audit" data-type="admin_action" data-sev="info"
      data-msg="Coupon create attempt">
      <input type="hidden" name="action" value="coupon_create">
      <div class="col-sm-2"><input type="text" name="code" class="form-control" placeholder="CODE" required></div>
      <div class="col-sm-2"><input type="number" name="discount" class="form-control" step="0.1" placeholder="%"
          required></div>
      <div class="col-sm-2"><input type="number" name="usage_limit" class="form-control" placeholder="Limit" required>
      </div>
      <div class="col-sm-3"><input type="date" name="expire_date" class="form-control" required></div>
      <div class="col-sm-2">
        <select name="company_id" class="form-select" required>
          <option value="">All Companies</option>
          <?php foreach ($companies as $c): ?>
            <option value="<?= $h($c['id']) ?>"><?= $h($c['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-sm-1"><button class="btn btn-primary w-100">Add</button></div>
    </form>

    <div class="table-responsive dark-card p-2">
      <table class="table table-sm align-middle mb-0">
        <thead>
          <tr>
            <th>Code</th>
            <th>Rate</th>
            <th>Limit</th>
            <th>Expiry</th>
            <th>Company</th>
            <th>Action</th>
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
              <td><?= $h($firma ?: 'All') ?></td>
              <td class="td-actions">
                <div class="d-flex gap-1 flex-wrap align-items-center">
                  <form method="post" class="d-flex m-0 gap-1 js-audit" data-type="admin_action" data-sev="info"
                    data-msg="Coupon update attempt">
                    <input type="hidden" name="action" value="coupon_update">
                    <input type="hidden" name="id" value="<?= $h($cp['id']) ?>">

                    <input type="number" name="discount" value="<?= $h($cp['discount']) ?>"
                      class="form-control form-control-sm" step="0.1" style="width:90px">

                    <input type="number" name="usage_limit" value="<?= $h($cp['usage_limit']) ?>"
                      class="form-control form-control-sm" style="width:100px">

                    <input type="date" name="expire_date" value="<?= $h(substr((string) $cp['expire_date'], 0, 10)) ?>"
                      class="form-control form-control-sm">

                    <button class="btn btn-outline-secondary btn-sm">Save</button>
                  </form>

                  <form method="post" class="m-0 js-audit" data-type="admin_action" data-sev="warning"
                    data-msg="Coupon delete attempt" onsubmit="return confirm('Delete this coupon?');">
                    <input type="hidden" name="action" value="coupon_delete">
                    <input type="hidden" name="id" value="<?= $h($cp['id']) ?>">
                    <button class="btn btn-outline-danger btn-sm">Delete</button>
                  </form>
                </div>
              </td>

            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </section>

  <script>
    (function () {
      const KEY = "admin_security_events_v2";

      function iso() { return new Date().toISOString(); }
      function load() {
        try {
          const raw = localStorage.getItem(KEY);
          const arr = raw ? JSON.parse(raw) : [];
          return Array.isArray(arr) ? arr : [];
        } catch (e) { return []; }
      }
      function save(arr) {
        localStorage.setItem(KEY, JSON.stringify(arr.slice(0, 120)));
      }
      function push(ev) {
        const arr = load();
        arr.unshift(ev);
        save(arr);
      }
      function fmt(isoStr) {
        try { return new Date(isoStr).toLocaleString(); } catch (e) { return isoStr; }
      }

      function badges(sev) {
        if (sev === "danger") return `<span class="badge text-bg-danger">HIGH</span>`;
        if (sev === "warning") return `<span class="badge text-bg-warning text-dark">WARN</span>`;
        return `<span class="badge text-bg-success">OK</span>`;
      }

      function calc() {
        const arr = load();
        const since = Date.now() - 24 * 60 * 60 * 1000;
        const last24 = arr.filter(x => new Date(x.ts).getTime() >= since);

        const failed = last24.filter(x => x.type === "failed_login").length;
        const ddos = last24.filter(x => x.type === "ddos_suspected").length;
        const phish = last24.filter(x => x.type === "phishing_suspected").length;
        const adminA = last24.filter(x => x.type === "admin_action").length;

        document.getElementById("mFailed").textContent = failed;
        document.getElementById("mDdos").textContent = ddos;
        document.getElementById("mPhish").textContent = phish;
        document.getElementById("mAdmin").textContent = adminA;

        const dot = document.getElementById("stateDot");
        const st = document.getElementById("stateText");
        const chip = document.getElementById("threatChip");

        const high = last24.filter(x => x.sev === "danger").length;
        const warn = last24.filter(x => x.sev === "warning").length;

        if (high >= 2 || ddos >= 3) {
          dot.className = "dot danger";
          st.textContent = "High Risk";
          chip.textContent = "Threat: HIGH";
          chip.style.background = "rgba(255,77,109,.18)";
          chip.style.borderColor = "rgba(255,77,109,.35)";
        } else if (warn >= 2 || failed >= 3 || phish >= 1) {
          dot.className = "dot warn";
          st.textContent = "Watchlist";
          chip.textContent = "Threat: MEDIUM";
          chip.style.background = "rgba(255,193,7,.15)";
          chip.style.borderColor = "rgba(255,193,7,.35)";
          chip.style.color = "rgba(255,255,255,.92)";
        } else {
          dot.className = "dot";
          st.textContent = "Stable";
          chip.textContent = "Threat: LOW";
          chip.style.background = "rgba(13,110,253,.12)";
          chip.style.borderColor = "rgba(255,255,255,.14)";
          chip.style.color = "#b9d6ff";
        }

        const box = document.getElementById("logBox");
        if (!arr.length) {
          box.innerHTML = `<div class="text-muted small">No events recorded yet. Use the panel actions or add a demo event.</div>`;
          return;
        }
        box.innerHTML = arr.slice(0, 14).map(x => `
          <div class="rowitem">
            <div>
              <div class="msg">${escapeHtml(x.title || x.type)}</div>
              <div class="meta">${escapeHtml(x.detail || "")} • ${fmt(x.ts)}</div>
            </div>
            <div>${badges(x.sev)}</div>
          </div>
        `).join("");
      }

      function escapeHtml(s) {
        return String(s ?? "").replace(/[&<>"']/g, m => ({
          "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;"
        }[m]));
      }

      const tips = [
        "Tip: block repeated login attempts with rate limiting and temporary lockouts.",
        "Tip: show a warning when a suspicious coupon pattern is detected (many creations/edits).",
        "Tip: always verify admin actions with CSRF tokens (server-side).",
        "Tip: watch for fake cloned pages (phishing) by monitoring unusual referrers.",
        "Tip: DDoS is often visible as many requests in a short window — add throttling."
      ];
      const tipEl = document.getElementById("secTip");
      tipEl.textContent = tips[Math.floor(Math.random() * tips.length)];

      const live = document.getElementById("liveChip");
      try {
        live.textContent = "SECURITY CONSOLE • " + new Date().toLocaleTimeString();
      } catch (e) { }

      document.querySelectorAll("form.js-audit").forEach(form => {
        form.addEventListener("submit", () => {
          const type = form.dataset.type || "admin_action";
          const sev = form.dataset.sev || "info";
          const msg = form.dataset.msg || "Admin action";

          push({
            ts: iso(),
            type: type,
            sev: sev,
            title: type.replaceAll("_", " ").toUpperCase(),
            detail: msg
          });
          calc();
        }, { capture: true });
      });

      document.getElementById("btnClear").addEventListener("click", () => {
        localStorage.removeItem(KEY);
        calc();
      });

      document.getElementById("btnAddDemo").addEventListener("click", () => {
        const demo = [
          { type: "failed_login", sev: "warning", title: "FAILED LOGIN", detail: "3 incorrect password attempts from same device" },
          { type: "ddos_suspected", sev: "danger", title: "DDoS SUSPECTED", detail: "High request burst detected (simulation)" },
          { type: "phishing_suspected", sev: "warning", title: "PHISHING ALERT", detail: "Possible cloned login page report (simulation)" },
          { type: "admin_action", sev: "info", title: "ADMIN ACTION", detail: "Sensitive change performed in admin panel" }
        ];
        const pick = demo[Math.floor(Math.random() * demo.length)];
        push({ ts: iso(), ...pick });
        calc();
      });

      calc();
    })();
  </script>
</main>

<?php require_once __DIR__ . '/includes/footer.php'; ?>