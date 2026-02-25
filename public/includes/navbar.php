<?php $u = current_user(); ?>
<nav class="navbar navbar-expand-lg navbar-dark bg-primary shadow-sm">
  <div class="container">
    <a class="navbar-brand fw-bold" href="index.php">Ticket Platform</a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#nav">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div id="nav" class="collapse navbar-collapse">
      <ul class="navbar-nav me-auto">
        <li class="nav-item"><a class="nav-link" href="index.php">Home</a></li>
        <li class="nav-item"><a class="nav-link" href="listings.php">All Trips</a></li>
        <?php if ($u && in_array($u['role'], ['company', 'admin'])): ?>
          <li class="nav-item dropdown">
            <a class="nav-link dropdown-toggle" data-bs-toggle="dropdown" href="#">Panels</a>
            <ul class="dropdown-menu">
              <?php if ($u['role'] === 'company'): ?>
                <li><a class="dropdown-item" href="company-admin.php">Company Admin Panel</a></li>
              <?php endif; ?>
              <?php if ($u['role'] === 'admin'): ?>
                <li><a class="dropdown-item" href="admin.php">Admin Panel</a></li>
              <?php endif; ?>
            </ul>
          </li>
        <?php endif; ?>
      </ul>
      <div class="d-flex gap-2">
        <?php if (!$u): ?>
          <a class="btn btn-outline-light" href="login.php">Login</a>
          <a class="btn btn-warning" href="register.php">Sign Up</a>
        <?php else: ?>
          <span class="badge text-bg-light text-primary align-self-center">
            Role: <?= htmlspecialchars(strtoupper($u['role'])) ?>
          </span>

          <?php if ($u['role'] !== 'admin'): ?>
            <a class="btn btn-outline-light" href="tickets.php">My Account</a>
          <?php endif; ?>

          <a class="btn btn-danger" href="logout.php">Logout</a>
        <?php endif; ?>
      </div>
    </div>
  </div>
</nav>