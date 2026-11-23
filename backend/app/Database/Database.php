<?php

namespace App\Database;

use PDO;
use PDOException;
use Throwable;

final class Database
{
    private static ?PDO $pdo = null;

    /**
     * Profundidade de transações aninhadas.
     *
     * O PDO não tem transação aninhada: um segundo beginTransaction() lança.
     * Como os serviços se chamam entre si (confirmPayment aciona a baixa de
     * estoque, que escreve em três tabelas), quem abre a transação é o primeiro
     * do encadeamento e só ele decide commit ou rollback.
     */
    private static int $txDepth = 0;

    public static function getConnection(): PDO
    {
        if (self::$pdo !== null) {
            return self::$pdo;
        }
        $c = require PLATAFORM_BACKEND . '/config/database.php';
        $dsn = "mysql:host={$c['host']};dbname={$c['dbname']};charset={$c['charset']}";
        try {
            self::$pdo = new PDO($dsn, $c['username'], $c['password'], [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);
        } catch (PDOException $e) {
            if (($cfg = require PLATAFORM_BACKEND . '/config/app.php')['debug'] ?? false) {
                throw $e;
            }
            throw new \RuntimeException('Erro de conexão com o banco.');
        }
        // O MySQL passa a usar o mesmo fuso do PHP. Os filtros de data (vendas
        // de hoje, mês do BI, prazo do PIX) são montados no PHP e comparados
        // com created_at/NOW() do banco; num servidor em UTC ficavam 3 h
        // deslocados. Vai como deslocamento ("-03:00") porque o XAMPP não traz
        // as tabelas de fusos nomeados do MySQL.
        $cfgApp = require PLATAFORM_BACKEND . '/config/app.php';
        try {
            $offset = (new \DateTimeImmutable('now', new \DateTimeZone((string) ($cfgApp['timezone'] ?? 'America/Sao_Paulo'))))->format('P');
            self::$pdo->exec("SET time_zone = '" . $offset . "'");
        } catch (Throwable $e) {
            // Fuso inválido na configuração: segue com o do servidor.
        }
        return self::$pdo;
    }

    /**
     * Executa $fn dentro de uma transação. Qualquer exceção desfaz tudo.
     *
     * Só a chamada mais externa abre e fecha a transação de verdade; as de
     * dentro apenas participam. Se uma interna lançar, a exceção sobe até a
     * externa e o rollback desfaz o conjunto inteiro — nunca metade.
     *
     * Atenção: DDL (CREATE/ALTER/DROP) provoca commit implícito no MySQL e no
     * MariaDB. Este helper serve para escrita de dados, não para migrations.
     *
     * @template T
     * @param callable(PDO): T $fn
     * @return T
     */
    public static function transaction(callable $fn)
    {
        $pdo = self::getConnection();

        if (self::$txDepth > 0) {
            self::$txDepth++;
            try {
                return $fn($pdo);
            } finally {
                self::$txDepth--;
            }
        }

        $pdo->beginTransaction();
        self::$txDepth = 1;
        try {
            $result = $fn($pdo);
            $pdo->commit();

            return $result;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        } finally {
            self::$txDepth = 0;
        }
    }
}
