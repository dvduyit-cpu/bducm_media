<?php

// Provision only the isolated, loopback MySQL instance created by start-local.ps1.
$port = $argv[1] ?? '3307';
$pdo = new PDO("mysql:host=127.0.0.1;port={$port};charset=utf8mb4", 'root', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$password = bin2hex(random_bytes(18));
$pdo->exec('CREATE DATABASE IF NOT EXISTS bdu_media CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
$quoted = $pdo->quote($password);
$pdo->exec("CREATE USER IF NOT EXISTS 'bdu_media'@'127.0.0.1' IDENTIFIED BY {$quoted}");
$pdo->exec("ALTER USER 'bdu_media'@'127.0.0.1' IDENTIFIED BY {$quoted}");
$pdo->exec("GRANT ALL PRIVILEGES ON bdu_media.* TO 'bdu_media'@'127.0.0.1'");
$envPath = dirname(__DIR__).'/.env';
$env = preg_replace('/^DB_PASSWORD=.*$/m', 'DB_PASSWORD='.$password, file_get_contents($envPath));
file_put_contents($envPath, $env);
echo "MySQL local đã được cấu hình.\n";
