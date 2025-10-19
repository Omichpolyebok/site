<?php
// src/mail.php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
require __DIR__ . '/../vendor/autoload.php';
function sendVerificationCode(string $to, string $code): bool {
    $log = '/var/www/mysite/logs/mail_debug.log';
    $cfg = require __DIR__ . '/config.php'; // ожидаем smtp_host, smtp_user, smtp_pass, smtp_port

    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host       = $cfg['smtp_host'] ?? 'smtp.yandex.ru';
        $mail->SMTPAuth   = true;
        $mail->Username   = $cfg['smtp_user'];
        $mail->Password   = $cfg['smtp_pass'];
        $mail->SMTPSecure = isset($cfg['smtp_secure']) ? $cfg['smtp_secure'] : 'ssl';
        $mail->Port       = $cfg['smtp_port'] ?? 465;

        // Важные параметры для стабильности
        $mail->Timeout = 10;                // сокет-таймаут (сек)
        $mail->SMTPKeepAlive = false;       // не держать соединение между вызовами
        $mail->SMTPAutoTLS = true;          // включать STARTTLS при 587

        $mail->setFrom($cfg['from_email'], $cfg['from_name'] ?? 'MySite');
        $mail->addAddress($to);
        $mail->Subject = 'Код подтверждения';
        $mail->Body    = "Ваш код: $code";

        // лог в файл (вместо вывода на экран)
        $mail->SMTPDebug = 2;
        $mail->Debugoutput = function($str, $level) use ($log) {
            @file_put_contents($log, date('[Y-m-d H:i:s] '). $str . PHP_EOL, FILE_APPEND);
        };

        $ok = $mail->send();
        @file_put_contents($log, date('[Y-m-d H:i:s] ') . "MAIL SEND OK: to=$to\n", FILE_APPEND);
        return (bool)$ok;
    } catch (Exception $e) {
        @file_put_contents($log, date('[Y-m-d H:i:s] ') . "MAIL EX: ".$e->getMessage()."\n", FILE_APPEND);
        return false;
    }
}

