<?php
$page_title = "My Account";
require_once __DIR__ . '/includes/functions.php';
require_login();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/navbar.php';

$h = fn($s) => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');

$role = $_SESSION['user']['role'] ?? null;
if ($role === 'admin') {
  header('Location: index.php');
  exit;
}


$u = current_user(true);

$companyName = null;
if (!empty($u['company_id'])) {
  $st = $db->prepare('SELECT name FROM Bus_Company WHERE id = ? LIMIT 1');
  $st->execute([$u['company_id']]);
  $companyName = $st->fetchColumn();
}

try {
  $cols = $db->query('PRAGMA table_info("User")')->fetchAll(PDO::FETCH_ASSOC);
  $hasAvatar = false;
  foreach ($cols as $c) {
    if (strcasecmp($c['name'], 'avatar_path') === 0) {
      $hasAvatar = true;
      break;
    }
  }
  if (!$hasAvatar) {
    $db->exec('ALTER TABLE "User" ADD COLUMN avatar_path TEXT');
  }
} catch (Throwable $e) {
}

$flashSuccess = get_flash('success');
$flashError = get_flash('error');

function password_policy_validate(string $new, array $userRow): array
{
  $errors = [];

  if (mb_strlen($new) < 10)
    $errors[] = 'Password must be at least 10 characters.';
  if (!preg_match('/[A-Z]/', $new))
    $errors[] = 'Must include at least 1 uppercase letter.';
  if (!preg_match('/[a-z]/', $new))
    $errors[] = 'Must include at least 1 lowercase letter.';
  if (!preg_match('/[0-9]/', $new))
    $errors[] = 'Must include at least 1 number.';
  if (!preg_match('/[^A-Za-z0-9]/', $new))
    $errors[] = 'Must include at least 1 special character.';

  $email = (string) ($userRow['email'] ?? '');
  $full = (string) ($userRow['full_name'] ?? '');

  $local = '';
  if ($email && strpos($email, '@') !== false)
    $local = explode('@', $email)[0];

  $lowNew = mb_strtolower($new);
  $lowLoc = mb_strtolower($local);
  $lowFull = mb_strtolower($full);

  if ($lowLoc && mb_strlen($lowLoc) >= 3 && mb_strpos($lowNew, $lowLoc) !== false) {
    $errors[] = 'Password should not contain your email name.';
  }

  $tokens = preg_split('/\s+/u', trim($lowFull)) ?: [];
  foreach ($tokens as $t) {
    $t = trim($t);
    if (mb_strlen($t) >= 3 && mb_strpos($lowNew, $t) !== false) {
      $errors[] = 'Password should not contain your name.';
      break;
    }
  }

  return $errors;
}

function handle_avatar_upload(PDO $db, string $userId, array $file): array
{
  if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
    return [false, 'No file uploaded.'];
  }
  if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
    return [false, 'Upload error occurred.'];
  }

  $maxBytes = 2 * 1024 * 1024;
  if (($file['size'] ?? 0) <= 0 || ($file['size'] ?? 0) > $maxBytes) {
    return [false, 'File size must be under 2 MB.'];
  }

  $tmp = $file['tmp_name'];

  $finfo = new finfo(FILEINFO_MIME_TYPE);
  $mime = $finfo->file($tmp);

  $allowedMimes = [
    'image/png' => IMAGETYPE_PNG,
    'image/jpeg' => IMAGETYPE_JPEG,
  ];
  if (!isset($allowedMimes[$mime])) {
    return [false, 'Only PNG or JPG/JPEG images are allowed.'];
  }

  $imgInfo = @getimagesize($tmp);
  if (!$imgInfo || empty($imgInfo[2])) {
    return [false, 'Invalid image file.'];
  }
  $imgType = (int) $imgInfo[2];
  if ($imgType !== IMAGETYPE_PNG && $imgType !== IMAGETYPE_JPEG) {
    return [false, 'Only PNG or JPG/JPEG images are allowed.'];
  }

  if (!function_exists('imagecreatefrompng') || !function_exists('imagecreatefromjpeg')) {
    return [false, 'Server image library (GD) is not available.'];
  }

  if ($imgType === IMAGETYPE_PNG) {
    $src = @imagecreatefrompng($tmp);
  } else {
    $src = @imagecreatefromjpeg($tmp);
  }
  if (!$src) {
    return [false, 'Could not process the image.'];
  }

  $maxW = 512;
  $maxH = 512;
  $w = imagesx($src);
  $h = imagesy($src);
  if ($w <= 0 || $h <= 0) {
    imagedestroy($src);
    return [false, 'Invalid image dimensions.'];
  }

  $scale = min($maxW / $w, $maxH / $h, 1);
  $newW = (int) floor($w * $scale);
  $newH = (int) floor($h * $scale);

  $dst = imagecreatetruecolor($newW, $newH);

  if ($imgType === IMAGETYPE_PNG) {
    imagealphablending($dst, false);
    imagesavealpha($dst, true);
    $transparent = imagecolorallocatealpha($dst, 0, 0, 0, 127);
    imagefilledrectangle($dst, 0, 0, $newW, $newH, $transparent);
  } else {
    $white = imagecolorallocate($dst, 255, 255, 255);
    imagefilledrectangle($dst, 0, 0, $newW, $newH, $white);
  }

  imagecopyresampled($dst, $src, 0, 0, 0, 0, $newW, $newH, $w, $h);
  imagedestroy($src);

  $uploadDir = __DIR__ . '/uploads/avatars';
  if (!is_dir($uploadDir)) {
    if (!@mkdir($uploadDir, 0755, true)) {
      imagedestroy($dst);
      return [false, 'Upload folder could not be created.'];
    }
  }

  $base = bin2hex(random_bytes(16));
  $ext = ($imgType === IMAGETYPE_PNG) ? 'png' : 'jpg';
  $name = $base . '.' . $ext;

  $absPath = $uploadDir . '/' . $name;

  $ok = false;
  if ($imgType === IMAGETYPE_PNG) {
    $ok = @imagepng($dst, $absPath, 6);
  } else {
    $ok = @imagejpeg($dst, $absPath, 85);
  }
  imagedestroy($dst);

  if (!$ok) {
    return [false, 'Failed to save image.'];
  }

  $relPath = 'uploads/avatars/' . $name;

  $st = $db->prepare('UPDATE "User" SET avatar_path = ? WHERE id = ?');
  $st->execute([$relPath, $userId]);

  return [true, $relPath];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $action = $_POST['action'] ?? '';
  $u = current_user(true);

  if (!$u || empty($u['id'])) {
    set_flash('error', 'Session not found. Please log in again.');
    header('Location: login.php');
    exit;
  }

  if ($action === 'change_password') {
    $old = (string) ($_POST['old_password'] ?? '');
    $new1 = (string) ($_POST['new_password'] ?? '');
    $new2 = (string) ($_POST['new_password2'] ?? '');

    if ($old === '' || $new1 === '' || $new2 === '') {
      set_flash('error', 'Please fill in all password fields.');
      header('Location: tickets.php');
      exit;
    }
    if ($new1 !== $new2) {
      set_flash('error', 'New passwords do not match.');
      header('Location: tickets.php');
      exit;
    }

    try {
      $stPw = $db->prepare('SELECT password, email, full_name FROM "User" WHERE id = ? LIMIT 1');
      $stPw->execute([$u['id']]);
      $row = $stPw->fetch(PDO::FETCH_ASSOC);

      if (!$row || empty($row['password']) || !password_verify($old, $row['password'])) {
        set_flash('error', 'Old password is incorrect.');
        header('Location: tickets.php');
        exit;
      }

      $policyErrors = password_policy_validate($new1, $row);
      if ($policyErrors) {
        set_flash('error', implode(' ', $policyErrors));
        header('Location: tickets.php');
        exit;
      }

      $newHash = password_hash($new1, PASSWORD_DEFAULT);
      $up = $db->prepare('UPDATE "User" SET password = ? WHERE id = ?');
      $up->execute([$newHash, $u['id']]);

      set_flash('success', 'Password updated successfully.');
      header('Location: tickets.php');
      exit;

    } catch (Throwable $e) {
      set_flash('error', 'Password update failed.');
      header('Location: tickets.php');
      exit;
    }
  }

  if ($action === 'upload_avatar') {
    try {
      [$ok, $msg] = handle_avatar_upload($db, $u['id'], $_FILES['avatar'] ?? []);
      if ($ok) {
        current_user(true);
        set_flash('success', 'Profile photo updated.');
      } else {
        set_flash('error', $msg);
      }
      header('Location: tickets.php');
      exit;
    } catch (Throwable $e) {
      set_flash('error', 'Profile photo upload failed.');
      header('Location: tickets.php');
      exit;
    }
  }
}

$u = current_user(true);

$stBal = $db->prepare('SELECT balance FROM "User" WHERE id = ? LIMIT 1');
$stBal->execute([$u['id']]);
$balance = (int) ($stBal->fetchColumn() ?? 0);

$st = $db->prepare("
SELECT 
  tk.id,
  (COUNT(bs.seat_number) * t.price) AS total_price,
  tk.status,
  tk.created_at,
  t.departure_city,
  t.destination_city,
  t.departure_time,
  GROUP_CONCAT(bs.seat_number, ',') AS seats
FROM Tickets tk
JOIN Trips t              ON t.id = tk.trip_id
LEFT JOIN Booked_Seats bs ON bs.ticket_id = tk.id
WHERE tk.user_id = ?
GROUP BY 
  tk.id, tk.status, tk.created_at,
  t.departure_city, t.destination_city, t.departure_time, t.price
ORDER BY t.departure_time DESC
");
$st->execute([$u['id']]);
$rows = $st->fetchAll(PDO::FETCH_ASSOC);

$avatarPath = trim((string) ($u['avatar_path'] ?? ''));
$avatarUrl = $avatarPath !== '' ? $h($avatarPath) : '';
$initials = '';
if (!empty($u['full_name'])) {
  $parts = preg_split('/\s+/u', trim((string) $u['full_name'])) ?: [];
  $initials = mb_strtoupper(mb_substr($parts[0] ?? 'U', 0, 1) . mb_substr($parts[1] ?? '', 0, 1));
} else {
  $initials = 'U';
}
?>

<style>
  .acc-hero {
    background: linear-gradient(135deg, rgba(13, 110, 253, .12), rgba(13, 110, 253, .04));
    border: 1px solid rgba(13, 110, 253, .18);
    border-radius: 16px;
  }

  .acc-card {
    border-radius: 16px;
    border: 1px solid rgba(0, 0, 0, .08);
    box-shadow: 0 8px 24px rgba(0, 0, 0, .04);
  }

  .avatar-wrap {
    width: 92px;
    height: 92px;
    border-radius: 999px;
    border: 3px solid rgba(13, 110, 253, .35);
    overflow: hidden;
    background: #fff;
    display: flex;
    align-items: center;
    justify-content: center;
    flex: 0 0 auto;
  }

  .avatar-img {
    width: 100%;
    height: 100%;
    object-fit: cover;
  }

  .avatar-fallback {
    width: 100%;
    height: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 800;
    color: #0d6efd;
    background: rgba(13, 110, 253, .08);
    font-size: 26px;
  }

  .acc-pill {
    border-radius: 999px;
    padding: 6px 10px;
    background: rgba(13, 110, 253, .08);
    border: 1px solid rgba(13, 110, 253, .18);
    font-weight: 700;
    color: #0d6efd;
    font-size: 12px;
    display: inline-flex;
    align-items: center;
    gap: 8px;
  }

  .pw-rule {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 13px;
  }

  .pw-dot {
    width: 10px;
    height: 10px;
    border-radius: 999px;
    background: #adb5bd;
    flex: 0 0 auto;
  }

  .pw-ok .pw-dot {
    background: #198754;
  }

  .pw-bad .pw-dot {
    background: #dc3545;
  }

  .ticket-row:hover {
    border-color: rgba(13, 110, 253, .35) !important;
    box-shadow: 0 10px 26px rgba(13, 110, 253, .08);
  }
</style>

<main class="container py-4">
  <div class="acc-hero p-3 p-md-4 mb-3">
    <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
      <div>
        <div class="text-muted small">Account</div>
        <h1 class="h4 mb-1">My Account</h1>
        <div class="text-muted">Manage your profile and security settings.</div>
      </div>
    </div>
  </div>

  <?php if ($flashSuccess): ?>
    <div class="alert alert-success acc-card p-3"><?= $h($flashSuccess) ?></div>
  <?php endif; ?>

  <?php if ($flashError): ?>
    <div class="alert alert-danger acc-card p-3"><?= $h($flashError) ?></div>
  <?php endif; ?>

  <div class="row g-3">
    <div class="col-12 col-lg-7">
      <div class="acc-card p-3 p-md-4 h-100">
        <div class="d-flex gap-3 align-items-start">
          <div class="avatar-wrap">
            <?php if ($avatarUrl): ?>
              <img class="avatar-img" src="<?= $avatarUrl ?>" alt="Profile photo">
            <?php else: ?>
              <div class="avatar-fallback"><?= $h($initials) ?></div>
            <?php endif; ?>
          </div>

          <div class="flex-grow-1">
            <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
              <div>
                <div class="text-muted small">Full Name</div>
                <div class="fw-semibold fs-5"><?= $h($u['full_name'] ?? '-') ?></div>

                <div class="text-muted small mt-2">Email</div>
                <div><?= $h($u['email'] ?? '-') ?></div>

                <?php if (($u['role'] ?? null) === 'company' && !empty($companyName)): ?>
                  <div class="text-muted small mt-3">Company</div>
                  <div class="d-flex align-items-center gap-2 mt-1 flex-wrap">
                    <span class="badge bg-info text-dark px-3 py-2">
                      🚌 <?= $h($companyName) ?>
                    </span>
                    <span class="badge bg-primary-subtle text-primary px-3 py-2">
                      Company Admin
                    </span>
                  </div>
                <?php endif; ?>
              </div>

              <div class="text-end">
                <div class="text-muted small">Current Balance</div>
                <div class="fw-bold fs-4 text-primary">
                  ₺<?= number_format($balance, 2, ',', '.') ?>
                </div>
              </div>
            </div>

            <hr class="my-3">


            <div class="fw-semibold mb-2">Profile Photo (PNG / JPG / JPEG)</div>
            <form method="post" enctype="multipart/form-data" class="row g-2 align-items-end">
              <input type="hidden" name="action" value="upload_avatar">
              <div class="col-12 col-md-8">
                <label class="form-label">Upload Image</label>
                <input type="file" name="avatar" class="form-control" accept="image/png,image/jpeg" required>
                <div class="form-text">
                  Allowed: <b>PNG, JPG, JPEG</b>. Max 2 MB. Image is sanitized server-side.
                </div>
              </div>
              <div class="col-12 col-md-4">
                <button class="btn btn-outline-primary w-100">Update Photo</button>
              </div>
            </form>
          </div>
        </div>
      </div>
    </div>



    <div class="col-12 col-lg-5">
      <div class="acc-card p-3 p-md-4 h-100">
        <div>
          <div class="text-muted small">Security</div>
          <div class="fw-semibold fs-5">Change Password</div>
        </div>

        <hr class="my-3">

        <form method="post" id="pwForm" class="row g-2">
          <input type="hidden" name="action" value="change_password">

          <div class="col-12">
            <label class="form-label">Old Password</label>
            <input type="password" name="old_password" class="form-control" required autocomplete="current-password">
          </div>

          <div class="col-12">
            <label class="form-label">New Password</label>
            <input type="password" id="newPw" name="new_password" class="form-control" required
              autocomplete="new-password">
          </div>

          <div class="col-12">
            <label class="form-label">New Password (Again)</label>
            <input type="password" id="newPw2" name="new_password2" class="form-control" required
              autocomplete="new-password">
          </div>

          <div class="col-12 mt-2">
            <div class="text-muted small mb-2">Password strength</div>
            <div class="progress" style="height:10px;">
              <div id="pwBar" class="progress-bar" role="progressbar" style="width: 0%"></div>
            </div>
            <div id="pwLabel" class="small text-muted mt-2">Start typing a new password…</div>
          </div>

          <div class="col-12 mt-2">
            <div class="text-muted small mb-2">Requirements</div>
            <div class="vstack gap-1">
              <div class="pw-rule" id="rLen"><span class="pw-dot"></span> At least 10 characters</div>
              <div class="pw-rule" id="rUp"><span class="pw-dot"></span> 1 uppercase letter</div>
              <div class="pw-rule" id="rLow"><span class="pw-dot"></span> 1 lowercase letter</div>
              <div class="pw-rule" id="rNum"><span class="pw-dot"></span> 1 number</div>
              <div class="pw-rule" id="rSp"><span class="pw-dot"></span> 1 special character</div>
              <div class="pw-rule" id="rMatch"><span class="pw-dot"></span> New passwords match</div>
            </div>
          </div>

          <div class="col-12 mt-3">
            <button id="pwBtn" class="btn btn-primary w-100" disabled>Update Password</button>
          </div>

          <div class="col-12">
            <div class="form-text">
              Tip: Avoid using your name or email in the password.
            </div>
          </div>
        </form>
      </div>
    </div>
  </div>

  <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mt-4 mb-2">
    <h2 class="h5 m-0">My Tickets</h2>
    <span class="text-muted small"><?= count($rows) ?> ticket(s)</span>
  </div>

  <div class="vstack gap-3">
    <?php if (!$rows): ?>
      <div class="alert alert-info acc-card p-3">No tickets found.</div>
    <?php else:
      foreach ($rows as $r):
        $depart = strtotime($r['departure_time']);
        $canCancel = ($depart - time()) >= 3600 && $r['status'] === 'active';
        ?>
        <div
          class="border rounded-4 p-3 acc-card ticket-row d-flex justify-content-between flex-wrap gap-2 align-items-center">
          <div>
            <div class="fw-semibold fs-6">
              <?= $h($r['departure_city'] . ' → ' . $r['destination_city']) ?>
            </div>
            <div class="small text-muted">
              <?= date('Y-m-d H:i', $depart) ?>
              • Seats: <?= $h($r['seats'] ?? '-') ?>
            </div>
            <div class="small mt-1">
              Status: <span
                class="badge text-bg-<?= $r['status'] === 'active' ? 'success' : 'secondary' ?>"><?= $h($r['status']) ?></span>
              <span class="text-muted">•</span>
              Total: <strong>₺<?= number_format((int) $r['total_price'], 0, ',', '.') ?></strong>
            </div>
          </div>

          <div class="d-flex gap-2">
            <a class="btn btn-outline-primary btn-sm" target="_blank" href="ticket-print.php?id=<?= $h($r['id']) ?>">
              Ticket / Print
            </a>

            <form method="post" action="ticket-cancel.php" class="m-0">
              <input type="hidden" name="ticket_id" value="<?= $h($r['id']) ?>">
              <button class="btn btn-outline-danger btn-sm" <?= $canCancel ? '' : 'disabled' ?>>
                Cancel
              </button>
            </form>
          </div>
        </div>


      <?php endforeach; endif; ?>
  </div>
</main>

<script>
  (function () {
    const newPw = document.getElementById('newPw');
    const newPw2 = document.getElementById('newPw2');
    const btn = document.getElementById('pwBtn');
    const bar = document.getElementById('pwBar');
    const label = document.getElementById('pwLabel');

    const rLen = document.getElementById('rLen');
    const rUp = document.getElementById('rUp');
    const rLow = document.getElementById('rLow');
    const rNum = document.getElementById('rNum');
    const rSp = document.getElementById('rSp');
    const rMatch = document.getElementById('rMatch');

    function setRule(el, ok) {
      el.classList.remove('pw-ok', 'pw-bad');
      el.classList.add(ok ? 'pw-ok' : 'pw-bad');
    }

    function scorePw(pw) {
      let score = 0;
      if (pw.length >= 10) score += 20;
      if (/[A-Z]/.test(pw)) score += 20;
      if (/[a-z]/.test(pw)) score += 20;
      if (/[0-9]/.test(pw)) score += 20;
      if (/[^A-Za-z0-9]/.test(pw)) score += 20;
      if (pw.length >= 14) score += 5;
      if (pw.length >= 18) score += 5;
      return Math.min(score, 100);
    }

    function update() {
      const pw = newPw.value || '';
      const pw2 = newPw2.value || '';

      const okLen = pw.length >= 10;
      const okUp = /[A-Z]/.test(pw);
      const okLow = /[a-z]/.test(pw);
      const okNum = /[0-9]/.test(pw);
      const okSp = /[^A-Za-z0-9]/.test(pw);
      const okMatch = pw.length > 0 && pw === pw2;

      setRule(rLen, okLen);
      setRule(rUp, okUp);
      setRule(rLow, okLow);
      setRule(rNum, okNum);
      setRule(rSp, okSp);
      setRule(rMatch, okMatch);

      const sc = scorePw(pw);
      bar.style.width = sc + '%';

      bar.className = 'progress-bar';
      if (sc >= 80) bar.classList.add('bg-success');
      else if (sc >= 60) bar.classList.add('bg-primary');
      else if (sc >= 40) bar.classList.add('bg-warning');
      else bar.classList.add('bg-danger');

      if (!pw) label.textContent = 'Start typing a new password…';
      else if (sc >= 80) label.textContent = 'Strong ✅';
      else if (sc >= 60) label.textContent = 'Good 👍';
      else if (sc >= 40) label.textContent = 'Weak — improve it';
      else label.textContent = 'Very weak';

      const allOk = okLen && okUp && okLow && okNum && okSp && okMatch;
      btn.disabled = !allOk;
    }

    newPw.addEventListener('input', update);
    newPw2.addEventListener('input', update);
    update();
  })();
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>