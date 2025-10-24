<?php
// public/login.php  —  UTF-8 (BOM'suz), en başta boşluk yok!
if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/../config/db.php';

$err = get_flash('error') ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $email = trim($_POST['email'] ?? '');
  $pass  = $_POST['password'] ?? '';

  $st = $db->prepare("SELECT id, full_name, email, role, password, balance FROM User WHERE email = ? LIMIT 1");
  $st->execute([$email]);
  $u = $st->fetch(PDO::FETCH_ASSOC);

  if ($u && password_verify($pass, $u['password'])) {
    $_SESSION['user'] = [
      'id'      => $u['id'],
      'name'    => $u['full_name'],
      'email'   => $u['email'],
      'role'    => $u['role'],
      'balance' => (float) ($u['balance'] ?? 0),
    ];
    header('Location: /index.php'); // veya sadece 'Location: /'
    exit;
  } else {
    $err = 'E-posta veya şifre hatalı.';
  }
}

// >>> Yalnızca buradan sonra sayfa çıktısı
$page_title = "Giriş";
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/navbar.php';
?>
<main class="container py-5" style="max-width:480px;">
  <div class="card">
    <div class="card-body">
      <h1 class="h4 mb-3">Giriş</h1>

      <?php if ($err): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($err, ENT_QUOTES, 'UTF-8') ?></div>
      <?php endif; ?>

      <form method="post" novalidate>
        <div class="mb-3">
          <label class="form-label">E-posta</label>
          <input type="email" name="email" class="form-control" required>
        </div>
        <div class="mb-3">
          <label class="form-label">Şifre</label>
          <input type="password" name="password" class="form-control" required>
        </div>
        <button class="btn btn-primary w-100">Giriş</button>
      </form>
    </div>
  </div>
</main>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
