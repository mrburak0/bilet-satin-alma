<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/session.php';


function set_flash($k,$v){ $_SESSION['flash'][$k]=$v; }
function get_flash($k){ if(!empty($_SESSION['flash'][$k])){ $m=$_SESSION['flash'][$k]; unset($_SESSION['flash'][$k]); return $m; } return null; }


function current_user(bool $refresh = false) {
  if (session_status() === PHP_SESSION_NONE) session_start();
  if (!isset($_SESSION['user'])) return null;

  if ($refresh) {

    global $db;
    if ($db) {
      $st = $db->prepare('SELECT * FROM "User" WHERE id = ? LIMIT 1');
      $st->execute([$_SESSION['user']['id']]);
      $row = $st->fetch(PDO::FETCH_ASSOC);
      if ($row) $_SESSION['user'] = $row;
    }
  }
  return $_SESSION['user'];
}
function is_logged_in(){ return !!current_user(); }
function user_role(){ return $_SESSION['user']['role'] ?? 'guest'; }

function require_login(){
  if (!is_logged_in()){ set_flash('error','Lütfen giriş yapın.'); header('Location: login.php'); exit; }
}
function require_role($roles){
  require_login();
  if (!in_array(user_role(), $roles, true)){
    set_flash('error','Bu sayfaya erişiminiz yok.'); header('Location: index.php'); exit;
  }
}
