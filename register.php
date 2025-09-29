<?php
session_set_cookie_params([
    'lifetime' => 0,           // cookie живёт до закрытия браузера
    'path' => '/',
    'domain' => 'omkayaprica.shop', // замени на свой домен
    'secure' => true,          // только по HTTPS
    'httponly' => true,        // нельзя читать из JS
    'samesite' => 'Strict'     // запрет кросс-сайтовых запросов
]);
 include 'header.php';  session_start();
// Путь к базе данных
$db = new SQLite3('/var/www/mysite/db/users.db');
// Создаём таблицу, если её ещё нет
$db->exec('CREATE TABLE IF NOT EXISTS users ( id INTEGER PRIMARY KEY AUTOINCREMENT, email TEXT NOT NULL UNIQUE, password TEXT NOT NULL, created_at DATETIME DEFAULT 
    CURRENT_TIMESTAMP
)'); $errors = []; if ($_SERVER['REQUEST_METHOD'] === 'POST') { $email = trim($_POST['email']); $password = trim($_POST['password']);
    // Проверка данных
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) { $errors[] = "Invalid email address.";
    }
    if (strlen($password) < 6) { $errors[] = "Password must be at least 6 characters.";
    }
    if (empty($errors)) {
        // Хешируем пароль
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        // Вставка пользователя
        $stmt = $db->prepare('INSERT INTO users (email, password) VALUES (:email, :password)'); $stmt->bindValue(':email', $email, SQLITE3_TEXT); 
        $stmt->bindValue(':password', $hashedPassword, SQLITE3_TEXT); $result = $stmt->execute(); if ($result) {
            header('Location: login.php?registered=1'); exit;
        } else {
            $errors[] = "This email is already registered.";
        }
    }
}
?> <!doctype html> <html lang="en"> <head> <meta charset="UTF-8"> <title>Register</title> </head> <body> <h1>Register</h1> <?php if (!empty($errors)): ?> <ul> <?php 
foreach($errors as $er) echo '<li>'.htmlspecialchars($er).'</li>'; ?> </ul> <?php endif; ?> <form method="post">
    Email: <input type="email" name="email" required><br> Password: <input type="password" name="password" required><br> <button type="submit">Register</button> 
</form> </body> </html>
