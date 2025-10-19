<?php
require __DIR__ . '/inc/init.php'; // инициализация: сессия, $pdo, csrf, защита
$errors = [];
$success_message = '';

// сайт должен перенаправлять залогиненного
if (!empty($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF
    if (!hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'] ?? '')) {
        $errors[] = 'Invalid request (CSRF).';
    } elseif (too_many_attempts()) {
        $errors[] = 'Too many failed attempts. Try later.';
    } else {
$email = strtolower(trim($_POST['email'] ?? ''));
        $password = $_POST['password'] ?? '';

        if ($email === '' || $password === '') {
            $errors[] = 'Email and password are required.';
        } else {
            try {
                $stmt = $pdo->prepare('SELECT id, password FROM users WHERE email = :email');
                $stmt->execute([':email' => $email]);
                $row = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($row && password_verify($password, $row['password'])) {
                    // успех
                    session_regenerate_id(true);
                    $_SESSION['user_id'] = $row['id'];
                    $_SESSION['email'] = $email;
                    reset_attempts();
                    $success_message = 'Login successful! Welcome, ' . htmlspecialchars($email, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                } else {
                    $errors[] = 'Invalid email or password.';
                    record_failed_attempt();
                }
            } catch (Exception $e) {
                $errors[] = 'Server error. Try later.';
            }
        }
    }
}
?>
<!doctype html>
<html lang="ru">
<head>
  <meta charset="utf-8">
  <title>Login</title>
<link rel="stylesheet" href="/style.css">

</head>
<body>
<?php include __DIR__ . '/inc/header.php'; ?>

<div class="container">
  <h1>Вход</h1>

  <?php if (!empty($errors)): ?>
    <ul class="error-list">
      <?php foreach ($errors as $er): ?>
        <li><?= htmlspecialchars($er, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></li>
      <?php endforeach; ?>
    </ul>
  <?php endif; ?>

  <?php if (!empty($success_message)): ?>
    <p class="success-message"><?= $success_message ?></p>
  <?php endif; ?>

  <?php if (isset($_GET['registered'])): ?>
    <p class="info-message">Вы успешно зарегистрировались — пожалуйста, войдите</p>
  <?php endif; ?>

  <form method="post" autocomplete="off" class="form">
    <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['csrf']) ?>">

    <label>
      <span>Email</span>
      <input type="email" name="email" required>
    </label>

    <label>
      <span>Пароль</span>
      <input type="password" name="password" required>
    </label>

    <button type="submit">Войти</button>
  </form>
</div>
</body>
</html>
