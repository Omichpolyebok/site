<header>
  <nav class="navbar">
    <a href="index.php">Главная</a>
    <?php if (!empty($_SESSION['user_id'])): ?>
      <a href="logout.php">Выйти</a>
    <?php else: ?>
      <a href="login.php">Войти</a>
      <a href="register.php">Регистрация</a>
    <?php endif; ?>
  </nav>
  <hr>
</header>

