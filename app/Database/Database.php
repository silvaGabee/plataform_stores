<?php

namespace App\Database;

use PDO;
use PDOException;

final class Database
{
    private static ?PDO $pdo = null;

    public static function getConnection(): PDO
    {
        if (self::$pdo !== null) {
            return self::$pdo;
        }
        $c = require dirname(__DIR__, 2) . '/config/database.php';
        $dsn = "mysql:host={$c['host']};dbname={$c['dbname']};charset={$c['charset']}";
        try {
            self::$pdo = new PDO($dsn, $c['username'], $c['password'], [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);
        } catch (PDOException $e) {
            if (($cfg = require dirname(__DIR__, 2) . '/config/app.php')['debug'] ?? false) {
                throw $e;
            }
            throw new \RuntimeException('Erro de conexão com o banco.');
        }
        return self::$pdo;
    }
}
