<?php
declare(strict_types=1);

namespace App\Core;

use PDO;
use PDOException;
use RuntimeException;

final class Database
{
    private static ?PDO $connection = null;

    public static function connection(): PDO
    {
        if (self::$connection instanceof PDO) {
            return self::$connection;
        }

        $host = $_ENV['DB_HOST'] ?? '127.0.0.1';
        $port = $_ENV['DB_PORT'] ?? '3306';
        $name = $_ENV['DB_DATABASE'] ?? '';
        $user = $_ENV['DB_USERNAME'] ?? '';
        $pass = $_ENV['DB_PASSWORD'] ?? '';
        $charset = $_ENV['DB_CHARSET'] ?? 'utf8mb4';

        if ($name === '' || $user === '') {
            throw new RuntimeException('Thiếu cấu hình DB_DATABASE hoặc DB_USERNAME trong file .env.');
        }

        $dsn = "mysql:host={$host};port={$port};dbname={$name};charset={$charset}";

        try {
            self::$connection = new PDO($dsn, $user, $pass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET time_zone = '+00:00'",
            ]);
        } catch (PDOException $exception) {
            error_log('[database] ' . $exception->getMessage());
            throw new RuntimeException('Không thể kết nối cơ sở dữ liệu. Vui lòng kiểm tra cấu hình hệ thống.');
        }

        return self::$connection;
    }
}
