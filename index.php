<?php
session_set_cookie_params([
    'lifetime' => 0,           
    'path' => '/',
    'domain' => 'omkayaprica.shop', 
    'secure' => true,          
    'httponly' => true,       
    'samesite' => 'Strict'    
]);
 include '/var/www/mysite/inc/header.php'; 
session_start();
$db = new SQLite3('/var/www/mysite/db/users.db');

// Обработка добавления отзыва
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SESSION['user_id'])) {
    $content = trim($_POST['content']);
    if ($content !== '') {
        $stmt = $db->prepare("INSERT INTO reviews (user_id, content) VALUES (:uid, :content)");
        $stmt->bindValue(':uid', $_SESSION['user_id'], SQLITE3_INTEGER);
        $stmt->bindValue(':content', $content, SQLITE3_TEXT);
        $stmt->execute();
        header("Location: index.php"); // чтобы не было повторной отправки формы
        exit;
    }
}

// Получение отзывов
$reviews = $db->query("SELECT r.content, r.created_at, u.email 
                       FROM reviews r 
                       JOIN users u ON r.user_id = u.id 
                       ORDER BY r.created_at DESC");
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
<link rel="stylesheet" href="style.css">
  <title>My Site</title>
</head>
<body>
  <h1>Добро пожаловать на сайт!</h1>

  <?php if (isset($_SESSION['user_id'])): ?>
    <p>Вы вошли как: <strong><?= htmlspecialchars($_SESSION['user_id']) ?></strong></p>
    <form method="post">
      <label>Оставьте отзыв:</label><br>
      <textarea name="content" required></textarea><br>
      <button type="submit">Отправить</button>
    </form>
  <?php else: ?>
    <p><a href="login.php">Войти</a> или <a href="register.php">зарегистрироваться</a>, чтобы оставить отзыв.</p>
  <?php endif; ?>

  <h2>Отзывы:</h2>
  <?php while ($row = $reviews->fetchArray(SQLITE3_ASSOC)): ?>
    <div class="review">
      <div class="author"><?= htmlspecialchars($row['email']) ?></div>
      <div class="date"><?= htmlspecialchars($row['created_at']) ?></div>
      <div class="content"><?= nl2br(htmlspecialchars($row['content'])) ?></div>
    </div>
  <?php endwhile; ?>
</body>
</html>
