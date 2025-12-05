<?php
// src/db.php

// 1. Определяем правильные пути
// __DIR__ это /var/www/mysite/src
// dirname(__DIR__) это /var/www/mysite
$baseDir = dirname(__DIR__); 

// Папка базы данных
$dbFolder = $baseDir . '/db';
$dbPath = $dbFolder . '/database.db';
$schemaPath = $dbFolder . '/schema.sql';

// 2. Создаем папку db, если её нет
if (!is_dir($dbFolder)) {
    mkdir($dbFolder, 0775, true);
    // Важно: даем права на запись папке, иначе SQLite не сможет создать журнал
    chmod($dbFolder, 0775);
}

// 3. Проверяем, нужно ли инициализировать базу
// Нужно, если файла нет ИЛИ если он пустой (0 байт)
$needInit = !file_exists($dbPath) || filesize($dbPath) === 0;

try {
    // Подключение
    $pdo = new PDO("sqlite:" . $dbPath);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->exec("PRAGMA foreign_keys = ON;");

    // 4. Если база новая или пустая — накатываем схему
    if ($needInit) {
        if (file_exists($schemaPath)) {
            $sql = file_get_contents($schemaPath);
            
            // SQLite не всегда любит выполнять кучу команд через exec одним махом.
            // Но попробуем. Если будут ошибки — разделим.
            $pdo->exec($sql);
            
            // Проверка: создалась ли таблица?
            // Можно удалить этот блок потом
            /*
            $test = $pdo->query("SELECT count(*) FROM sqlite_master WHERE type='table' AND name='users'")->fetchColumn();
            if ($test == 0) {
                die("ОШИБКА: schema.sql прочитан, но таблицы не создались. Проверьте синтаксис SQL.");
            }
            */
        } else {
            die("Критическая ошибка: Файл схемы не найден по пути: $schemaPath");
        }
    }

} catch (PDOException $e) {
    // Выводим полный путь, чтобы ты точно знал, где искать базу
    die("Ошибка БД: " . $e->getMessage() . " <br>Путь к базе: " . $dbPath);
}
