<?php
session_set_cookie_params([
    'lifetime' => 0,           // cookie живёт до закрытия браузера
    'path' => '/',
    'domain' => 'omkayaprica.shop', // замени на свой домен
    'secure' => true,          // только по HTTPS
    'httponly' => true,        // нельзя читать из JS
    'samesite' => 'Strict'     // запрет кросс-сайтовых запросов
]);
 include 'header.php'; 
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);


// Путь к базе данных
$db_path = '/var/www/mysite/db/users.db';

// Проверяем, существует ли база
if (!file_exists($db_path)) {
    die("Database not found at $db_path");
}

// Подключение к SQLite
$db = new SQLite3($db_path);

// Массив для ошибок
$errors = [];

// Если отправлена форма
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($email === '' || $password === '') {
        $errors[] = "Email and password are required.";
    } else {
        // Подготовленный запрос
        $stmt = $db->prepare('SELECT id, password FROM users WHERE email = :email');
        $stmt->bindValue(':email', $email, SQLITE3_TEXT);
        $result = $stmt->execute();
        $row = $result->fetchArray(SQLITE3_ASSOC);

        if ($row && password_verify($password, $row['password'])) {
	session_regenerate_id(true); // 👈 создаём новую сессию
            $_SESSION['user_id'] = $row['id'];
            $_SESSION['email'] = $email;
            $success_message = "Login successful! Welcome, " . htmlspecialchars($email);
        } else {
            $errors[] = "Invalid email or password.";
        }
    }
}
?>

<!doctype html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Login</title>
</head>
<body>

<h1>Login</h1>

<?php
// Вывод ошибок
if (!empty($errors)) {
    echo "<ul style='color:red;'>";
    foreach ($errors as $er) {
        echo "<li>" . htmlspecialchars($er) . "</li>";
    }
    echo "</ul>";
}

// Сообщение об успешном входе
if (!empty($success_message)) {
    echo "<p style='color:green;'>$success_message</p>";
}

// Сообщение после регистрации
if (isset($_GET['registered'])) {
    echo '<p style="color:blue;">Registered — please login</p>';
}
?>

<form method="post">
  Email: <input type="email" name="email" required><br><br>
  Password: <input type="password" name="password" required><br><br>
  <button type="submit">Login</button>
</form>

</body>
</html>
