<?php
$page_title = "Kayıt Ol";
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/navbar.php';
require_once __DIR__ . '/includes/functions.php';

function make_uuid()
{
  return bin2hex(random_bytes(16));
} 

$err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  require_once __DIR__ . '/../config/db.php';
  $name = trim($_POST['full_name'] ?? '');
  $email = trim($_POST['email'] ?? '');
  $pass = $_POST['password'] ?? '';
  if ($name === '' || $email === '' || $pass === '') {
    $err = 'Tüm alanlar zorunlu.';
  } else {
    $q = $db->prepare("SELECT 1 FROM User WHERE email=?");
    $q->execute([$email]);
    if ($q->fetch()) {
      $err = 'Bu e-posta zaten kayıtlı.';
    } else {
      $hash = password_hash($pass, PASSWORD_DEFAULT);
      $ins = $db->prepare("INSERT INTO User (id, full_name, email, role, password, balance)
                     VALUES (?, ?, ?, 'user', ?, 800)");
      $ins->execute([make_uuid(), $name, $email, $hash]);
      set_flash('success', 'Kayıt başarılı. Giriş yapabilirsiniz.');
      header('Location: login.php');
      exit;
    }
  }
}
?>
<main class="container py-5" style="max-width:520px;">
  <div class="card">
    <div class="card-body">
      <h1 class="h4 mb-3">Kayıt Ol</h1>
      <?php if ($m = get_flash('success')): ?>
        <div class="alert alert-success"><?= $m ?></div><?php endif; ?>
      <?php if ($err): ?>
        <div class="alert alert-danger"><?= $err ?></div><?php endif; ?>
      <form method="post">
        <div class="mb-3"><label class="form-label">Ad Soyad</label><input name="full_name" class="form-control"
            required></div>
        <div class="mb-3"><label class="form-label">E-posta</label><input type="email" name="email" class="form-control"
            required></div>
        <div class="mb-3"><label class="form-label">Şifre</label><input type="password" name="password"
            class="form-control" required></div>
        <button class="btn btn-primary w-100">Kayıt Ol</button>
      </form>
      <p class="mt-3">Hesabın var mı? <a href="login.php">Giriş yap</a></p>
    </div>
  </div>
</main>
<?php require_once __DIR__ . '/includes/footer.php'; ?>