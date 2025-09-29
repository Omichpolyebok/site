<?php
session_start();
?>
<!doctype html>
<html lang="ru">
<head>
  <meta charset="utf-8">
  <title>Мой сайт</title>
</head>
<body>
<header>
  <nav>
    <a href="index.php">Главная</a> |
    <?php if (isset($_SESSION['user_id'])): ?>
      <a href="logout.php">Выйти</a>
    <?php else: ?>
      <a href="login.php">Войти</a> |
      <a href="register.php">Регистрация</a>
    <?php endif; ?>
  </nav>
</header>
<hr>
