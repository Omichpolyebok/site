<?php
// public/verify.php
declare(strict_types=1);

require __DIR__ . '/inc/init.php';   // session + CSRF 
//require __DIR__ . '/src/db.php';     // $pdo
require __DIR__ . '/src/mail.php';   // sendVerificationCode()

// авто-очистка просроченных кодов (безопасно выполнять при каждом заходе)
try {
    $pdo->prepare("
        UPDATE users
        SET verify_code_hash = NULL,
            verify_expires = NULL,
            verify_attempts = 0
        WHERE verify_expires IS NOT NULL AND verify_expires < ?
    ")->execute([time()]);
} catch (Throwable $e) {
    // не ломаем страницу из-за этой операции — логировать в error_log
    error_log('verify cleanup error: ' . $e->getMessage());
}

// UI / messages
$message = '';
$errors = [];
$emailPrefill = filter_var($_GET['email'] ?? '', FILTER_VALIDATE_EMAIL) ?: '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF
    if (!hash_equals($_SESSION['csrf'] ?? '', (string)($_POST['csrf'] ?? ''))) {
        $errors[] = 'Некорректный запрос (CSRF).';
    } else {
        $email = filter_var($_POST['email'] ?? '', FILTER_VALIDATE_EMAIL);
        $resend = isset($_POST['resend']) && $_POST['resend'] === '1';
        $code = trim((string)($_POST['code'] ?? ''));

        if (!$email) {
            $errors[] = 'Некорректный email.';
        } else {
            // получаем пользователя
            $stmt = $pdo->prepare("SELECT id, verify_code_hash, verify_expires, IFNULL(verify_attempts,0) AS verify_attempts, is_verified FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$user) {
                $errors[] = 'Пользователь не найден.';
            } elseif ((int)$user['is_verified'] === 1) {
                $message = 'Аккаунт уже подтверждён. Можете войти.';
            } else {
                // обработка повторной отправки кода
                if ($resend) {
                    // генерируем новый код
                    $newCode = random_int(100000, 999999);
                    $newHash = password_hash((string)$newCode, PASSWORD_DEFAULT);
                    $expires = time() + 15 * 60;

                    try {
                        $upd = $pdo->prepare("UPDATE users SET verify_code_hash = ?, verify_expires = ?, verify_attempts = 0 WHERE id = ?");
                        $upd->execute([$newHash, $expires, $user['id']]);

                        // отправляем письмо; логика отправки в sendVerificationCode()
                        if (sendVerificationCode($email, (string)$newCode)) {
                            $message = "Новый код отправлен на $email. Проверьте папку «спам».";
                        } else {
                            $errors[] = 'Не удалось отправить новый код. Попробуйте позже.';
                        }
                    } catch (Throwable $e) {
                        error_log('verify resend error: ' . $e->getMessage());
                        $errors[] = 'Ошибка сервера. Попробуйте позже.';
                    }
                } else {
                    // проверка кода
                    $verifyExpires = (int)$user['verify_expires'];
                    $attempts = (int)$user['verify_attempts'];

                    if ($verifyExpires === 0 || $verifyExpires < time()) {
                        $errors[] = 'Код истёк. Запросите новый код.';
                    } elseif ($attempts >= 5) {
                        $errors[] = 'Слишком много неверных попыток. Попробуйте позже.';
                    } elseif ($code === '') {
                        $errors[] = 'Введите код.';
                    } else {
                        // сверяем код
                        if (password_verify($code, $user['verify_code_hash'])) {
                            try {
                                $pdo->prepare("UPDATE users SET is_verified = 1, verify_code_hash = NULL, verify_expires = NULL, verify_attempts = 0 WHERE id = ?")
                                    ->execute([$user['id']]);
                                // Успех — редирект на login.php с уведомлением.
                                // Отправим заголовок — и покажем страницу с 3-секундной авто-переадресацией.
                                header("Location: login.php?verified=1");
                                exit;
                            } catch (Throwable $e) {
                                error_log('verify commit error: ' . $e->getMessage());
                                $errors[] = 'Ошибка сервера при подтверждении. Попробуйте позже.';
                            }
                        } else {
                            // инкремент попыток
                            try {
                                $pdo->prepare("UPDATE users SET verify_attempts = verify_attempts + 1 WHERE id = ?")
                                    ->execute([$user['id']]);
                            } catch (Throwable $e) {
                                error_log('verify increment attempts error: ' . $e->getMessage());
                            }
                            $errors[] = 'Неверный код.';
                        }
                    }
                } // end resend/verify
            } // end if user found
        } // end if email valid
    } // end CSRF ok
} // end POST

// подтягиваем CSRF для формы (предполагаем, что init.php формирует или содержит $_SESSION['csrf'])
$csrf = $_SESSION['csrf'] ?? bin2hex(random_bytes(16));
?>
<!doctype html>
<html lang="ru">
<head>
  <meta charset="utf-8">
  <title>Подтверждение email</title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <style>
    body{font-family:Inter,system-ui,Arial;background:#f5f7fb;padding:40px}
    .card{max-width:420px;margin:0 auto;background:#fff;padding:20px;border-radius:10px;box-shadow:0 6px 24px rgba(15,23,42,0.06)}
    h1{font-size:18px;margin:0 0 10px}
    p{color:#333}
    input[type=email],input[type=text]{width:100%;padding:10px;margin:8px 0;border:1px solid #ddd;border-radius:6px}
    button{display:inline-block;padding:10px 14px;border-radius:6px;border:0;background:#0b74de;color:#fff;cursor:pointer}
    .muted{color:#666;font-size:14px}
    .errors{background:#fff4f4;border:1px solid #f2c2c2;color:#8a1f1f;padding:8px;border-radius:6px;margin-bottom:10px}
    .ok{background:#f0fff4;border:1px solid #b7e4c7;color:#175f2c;padding:8px;border-radius:6px;margin-bottom:10px}
    .small{font-size:13px;color:#555}
  </style>
</head>
<body>
  <div class="card">
    <h1>Подтверждение Email</h1>

    <?php if (!empty($errors)): ?>
      <div class="errors">
        <?php foreach ($errors as $err): ?>
          <div><?= htmlspecialchars($err) ?></div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <?php if ($message): ?>
      <div class="ok"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>

    <p class="muted">Введите код из письма, отправленного на <strong><?= htmlspecialchars($emailPrefill) ?></strong>.</p>

    <form method="POST" style="margin-bottom:10px">
      <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">
      <label class="small">Email</label>
      <input type="email" name="email" required value="<?= htmlspecialchars($emailPrefill) ?>">
      <label class="small">Код из письма</label>
      <input type="text" name="code" inputmode="numeric" autocomplete="one-time-code" minlength="6" maxlength="6" placeholder="123456">
      <div style="margin-top:12px">
        <button type="submit">Подтвердить</button>
      </div>
    </form>

    <form method="POST">
      <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">
      <input type="hidden" name="email" value="<?= htmlspecialchars($emailPrefill) ?>">
      <input type="hidden" name="resend" value="1">
      <button type="submit" style="background:#6b7280">Выслать новый код</button>
    </form>

    <p class="small" style="margin-top:12px">Если вы не получили письмо — проверьте папку «Спам» или нажмите «Выслать новый код».</p>
  </div>
</body>
</html>

