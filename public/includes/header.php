<?php require_once __DIR__ . '/functions.php'; ?>
<!doctype html>
<html lang="tr">
<head>
  <meta charset="utf-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1"/>
  <title><?= isset($page_title) ? htmlspecialchars($page_title) . ' • ' : '' ?>Bilet Platformu</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet"/>
  <link href="assets/styles.css" rel="stylesheet"/>
</head>
<body class="d-flex flex-column min-vh-100">
