<?php
// inc/init.php — единый init: сессия, CSRF, PDO, простая защита от брута

// Настройки cookie для сессии — ставим до session_start
$secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'domain' => $_SERVER['HTTP_HOST'], // использует текущий хост
    'secure' => $secure,
    'httponly' => true,
    'samesite' => 'Strict'
]);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// CSRF token
if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
}

// Простая защита от грубой силы (session-based)
if (!isset($_SESSION['failed_login'])) {
    $_SESSION['failed_login'] = ['count' => 0, 'first_ts' => 0];
}
function too_many_attempts(): bool {
    $f = &$_SESSION['failed_login'];
    // порог: 5 попыток за 5 минут
    if ($f['count'] >= 5 && (time() - $f['first_ts']) < 300) return true;
    if ((time() - $f['first_ts']) >= 300) {
        // сбрасываем старые попытки
        $f = ['count' => 0, 'first_ts' => 0];
    }
    return false;
}
function record_failed_attempt(): void {
    $f = &$_SESSION['failed_login'];
    if ($f['first_ts'] === 0) $f['first_ts'] = time();
    $f['count'] = ($f['count'] ?? 0) + 1;
}
function reset_attempts(): void {
    $_SESSION['failed_login'] = ['count' => 0, 'first_ts' => 0];
}

// Подключение к базе через PDO (SQLite)
try {
    $pdo = new PDO('sqlite:/var/www/mysite/db/users.db');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (Exception $e) {
    http_response_code(500);
    exit('Internal Server Error (DB)');
}
