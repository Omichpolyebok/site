Веб-приложение для автоматизации работы ТСЖ

Сервис упрощает коммуникацию между жильцами и председателем.
Жильцы подают заявки на ремонт, председатель управляет ими через панель.

Технологии

Backend: PHP 8.1 (нативный, без фреймворков)

Database: SQLite 3

Frontend: HTML5, CSS3, Vanilla JS

Infrastructure: Docker, Nginx

Возможности

Регистрация и подтверждение через Email

Роли пользователей (Жилец / Председатель)

Создание, просмотр и обновление заявок

Защита от CSRF и XSS

Запуск через Docker (в разработке)

Docker заменяет ручную настройку сервера.

Клонировать репозиторий:

git clone https://github.com/Omichpolyebok/site/.git
cd /site/


Создать файл конфигурации:

cp src/config.example.php src/config.php


Указать настройки SMTP.

Запустить проект:

docker compose up -d --build


Открыть в браузере:

http://localhost:8080



Добавление администратора
Создать файл /var/www/mysite/add_admin.php:

    
<?php
// add_admin.php
require_once '/var/www/mysite/src/db.php';

$email = 'admin@tsj.local'; // Можно поменять
$password = 'admin';        // Пароль
$fullName = 'Председатель ТСЖ';
$role = 'admin';

// Генерируем правильный хеш
$hash = password_hash($password, PASSWORD_DEFAULT);

try {
    $pdo->exec("DELETE FROM users WHERE email = '$email'");
    $stmt = $pdo->prepare("INSERT INTO users (email, password, full_name, role, is_verified, apartment) VALUES (?, ?, ?, ?, 1, 'Офис')");
    $stmt->execute([$email, $hash, $fullName, $role]);

    echo "Администратор успешно создан!<br>";
    echo "Email: $email<br>";
    echo "Пароль: $password<br>";
    echo "<a href='public/login.php'>Войти</a>";

} catch (PDOException $e) {
    echo "Ошибка: " . $e->getMessage();
}

В консоли php /var/www/mysite/add_admin.php
Важно: После проверки удалить файл add_admin.php, чтобы никто случайно не сбросил админа.

rm /var/www/mysite/add_admin.php

    
