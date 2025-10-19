<?php
// public/register.php (debug-ready)

session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'domain' => 'omkayaprica.shop', 
    'secure' => true,
    'httponly' => true,        
    'samesite' => 'Strict'     
]);
 include '/var/www/mysite/inc/header.php'; 

ini_set('display_errors', 0);
error_reporting(E_ALL);

session_start();
//debug toggle
define('REG_DEBUG', false);

// логировать в файл и в error_log
$logDir = __DIR__ . '/../logs';
$logFile = $logDir . '/register_debug.log';
if (!is_dir($logDir) && REG_DEBUG) {
    @mkdir($logDir, 0755, true);
}
function reg_log($msg) {
    global $logFile;
    $time = date('Y-m-d H:i:s');
    $line = "[$time] $msg\n";
    if (defined('REG_DEBUG') && REG_DEBUG && is_writable(dirname($logFile))) {
        @file_put_contents($logFile, $line, FILE_APPEND | LOCK_EX);
    }
    error_log($line);
}

// начало логирования
reg_log("=== START register.php ===");
reg_log("REQUEST_METHOD: " . ($_SERVER['REQUEST_METHOD'] ?? 'n/a'));

$initPath = '/var/www/mysite/inc/init.php';
//$dbPath   = '/var/www/mysite/src/db.php';
$mailPath = '/var/www/mysite/src/mail.php';

reg_log("Checking files: init={$initPath} exists=" . (file_exists($initPath) ? 'yes' : 'NO'));
reg_log("Checking files: db={$dbPath} exists=" . (file_exists($dbPath) ? 'yes' : 'NO'));
reg_log("Checking files: mail={$mailPath} exists=" . (file_exists($mailPath) ? 'yes' : 'NO'));

// require init (session & csrf). Если не найден — лог и аккуратно выйти
if (file_exists($initPath)) {
    require $initPath;
    reg_log("Included init.php");
} else {
    reg_log("Missing init.php - cannot continue");
    http_response_code(500);
    echo "Ошибка сервера.";
    exit;
}

// Здесь мы попытаемся подключить, если файл есть.
if (file_exists($dbPath)) {
    require $dbPath; // ожидаем $pdo
    reg_log("Included db.php");
} else {
    reg_log("db.php not found — \$pdo will be undefined. If you expect DB usage, restore db.php");
}

// mail.php обязателен для отправки
if (file_exists($mailPath)) {
    require $mailPath;
    reg_log("Included mail.php");
} else {
    reg_log("Missing mail.php - sendVerificationCode unavailable");
}

// Быстрые проверки окружения
reg_log("Session csrf (stored): " . (isset($_SESSION['csrf']) ? '[set]' : '[NOT_SET]'));
reg_log("Function sendVerificationCode exists: " . (function_exists('sendVerificationCode') ? 'yes' : 'NO'));
reg_log("Variable \$pdo exists: " . (isset($pdo) ? 'yes' : 'NO'));

// Обработать POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    reg_log("POST received");

    // Безопасно считываем вход
    $csrf_post = $_POST['csrf'] ?? '';
    $emailRaw = trim($_POST['email'] ?? '');
    $email = strtolower($emailRaw);
    $password = $_POST['password'] ?? '';

    reg_log("Inputs: email_raw=" . substr($emailRaw,0,200) . " password_len=" . strlen($password));

    // CSRF
    if (!hash_equals($_SESSION['csrf'] ?? '', $csrf_post)) {
        reg_log("CSRF mismatch: session=" . ($_SESSION['csrf'] ?? 'null') . " post=" . ($csrf_post === '' ? '[empty]' : '[present]'));
        echo "Некорректный запрос."; exit;
    }
    reg_log("CSRF OK");

    // валидация email/password
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        reg_log("Invalid email: $email");
        echo "Некорректный email."; exit;
    }
    if (strlen($password) < 8) {
        reg_log("Password too short");
        echo "Пароль должен быть не менее 8 символов."; exit;
    }
    reg_log("Input validation OK");

    // Проверяем $pdo
    if (!isset($pdo) || !($pdo instanceof PDO)) {
        reg_log("\$pdo is not set or not PDO — aborting DB ops");
        // важно: если DB недоступна, не пытаться работать с ней
        echo "Ошибка сервера. Попробуйте позже.";
        exit;
    }

    try {
        // проверяем существование записи
        $stmt = $pdo->prepare("SELECT id, is_verified FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $exists = $stmt->fetch(PDO::FETCH_ASSOC);
        reg_log("DB lookup done. exists? " . ($exists ? 'yes id='.$exists['id'] : 'no'));

        // готовим значения
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $code = random_int(100000, 999999);
        $codeHash = password_hash((string)$code, PASSWORD_DEFAULT);
        $expires = time() + 15 * 60;

        reg_log("Prepared hashed values. code=" . $code . " (will not log hash). expires=" . $expires);

        $pdo->beginTransaction();
        reg_log("Transaction started");

        if ($exists) {
            if ((int)$exists['is_verified'] === 1) {
                $pdo->rollBack();
                reg_log("User exists and verified — rolled back and returned neutral message");
                echo "Если аккаунт с этим адресом существует, вы получите писмo с инструкцией.";
                exit;
            }
            $stmt = $pdo->prepare("UPDATE users SET password = ?, verify_code_hash = ?, verify_expires = ?, is_verified = 0, verify_attempts = 0 WHERE id = ?");
            $stmt->execute([$hash, $codeHash, $expires, $exists['id']]);
            $userId = $exists['id'];
            reg_log("Updated existing user id=$userId");
        } else {
            $stmt = $pdo->prepare("INSERT INTO users (email, password, is_verified, verify_code_hash, verify_expires, created_at) VALUES (?, ?, 0, ?, ?, ?)");
            $stmt->execute([$email, $hash, $codeHash, $expires, time()]);
            $userId = $pdo->lastInsertId();
            reg_log("Inserted new user id=$userId");
        }

        // Отправка письма: обёрнута в try/catch, логируем ошибки
        if (!function_exists('sendVerificationCode')) {
            reg_log("sendVerificationCode function missing - cannot send email");
            $pdo->rollBack();
            echo "Ошибка сервера. Попробуйте позже.";
            exit;
        }

        reg_log("Calling sendVerificationCode for $email");
        try {
            $sent = sendVerificationCode($email, (string)$code);
            reg_log("sendVerificationCode returned: " . ($sent ? 'true' : 'false'));
        } catch (Throwable $e) {
            reg_log("Exception in sendVerificationCode: " . $e->getMessage());
            // если внутри PHPMailer есть $mail->ErrorInfo, попытка получить его
            if (isset($GLOBALS['mail']) && is_object($GLOBALS['mail'])) {
                reg_log("Global mail ErrorInfo: " . ($GLOBALS['mail']->ErrorInfo ?? '[none]'));
            }
           $pdo->rollBack();
            echo "Ошибка при отправке письма. Попробуйте позже.";
            exit;
        }


        if (!$sent) {
            reg_log("sendVerificationCode returned false - rolling back");
            $pdo->rollBack();
            echo "Ошибка при отправке письма. Попробуйте позже.";
            exit;
        }

        $pdo->commit();
        reg_log("Transaction committed, userId=$userId, email sent");

        echo "Вашу почту было отправлено письмо. Проверьте папку «спам».";
header("Location: verify.php?email=" . urlencode($email));        
exit;
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        reg_log("Register DB error: " . $e->getMessage());
        echo "Ошибка сервера. Попробуйте позже.";
        exit;
    } catch (Throwable $e) {
        if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
        reg_log("Unexpected error: " . $e->getMessage());
        echo "Ошибка сервера. Попробуйте позже.";
        exit;
    }
}
reg_log("Rendering registration form");
?>
<!doctype html>
<html lang="ru">
<head>
  <meta charset="utf-8">
  <title>Регистрация</title>
  <link rel="stylesheet" href="style.css">
</head>
<body>
  <div class="container">
    <h1>Регистрация</h1>
    <form method="POST" autocomplete="off" class="form">
      <input type="hidden" name="csrf" value="<?=htmlspecialchars($_SESSION['csrf'] ?? '')?>">

      <label>
        <span>Email</span>
        <input type="email" name="email" required>
      </label>

      <label>
        <span>Пароль</span>
        <input type="password" name="password" required minlength="8">
      </label>

      <button type="submit">Зарегистрироваться</button>
    </form>
      </div>
</body>
</html>

