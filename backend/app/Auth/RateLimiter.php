<?php

namespace App\Auth;

use App\Database\Database;
use PDO;

/**
 * Limita tentativas por janela de tempo.
 *
 * O login não tinha limite nenhum: dava para testar senhas indefinidamente,
 * e os endpoints de IA podiam ser chamados em laço queimando crédito da API
 * paga. O contador vive em tabela porque quem ataca não reaproveita cookie —
 * na sessão, cada tentativa começaria do zero.
 */
final class RateLimiter
{
    /**
     * Registra uma tentativa e diz se o limite foi ultrapassado.
     *
     * @param string $acao     ex.: 'login', 'criar-conta', 'ai'
     * @param string $alvo     e-mail, slug da loja — o que distingue a tentativa
     * @param int    $limite   tentativas permitidas na janela
     * @param int    $janelaSegundos
     * @return bool true se PODE prosseguir; false se estourou o limite
     */
    public static function tentar(string $acao, string $alvo, int $limite, int $janelaSegundos): bool
    {
        return self::restantes($acao, $alvo, $limite, $janelaSegundos) > 0;
    }

    /**
     * Incrementa e devolve quantas tentativas ainda restam (0 = bloqueado).
     */
    public static function restantes(string $acao, string $alvo, int $limite, int $janelaSegundos): int
    {
        $pdo = Database::getConnection();
        $bucket = self::bucket($acao, $alvo);

        // Uma instrução só: cria o registro ou incrementa, reiniciando quando a
        // janela anterior já venceu. Duas requisições simultâneas não
        // conseguem, assim, ler o mesmo contador e gravar por cima.
        $stmt = $pdo->prepare(
            'INSERT INTO rate_limits (bucket, hits, expires_at)
             VALUES (:bucket, 1, DATE_ADD(NOW(), INTERVAL :janela SECOND))
             ON DUPLICATE KEY UPDATE
                hits = IF(expires_at < NOW(), 1, hits + 1),
                expires_at = IF(expires_at < NOW(), DATE_ADD(NOW(), INTERVAL :janela2 SECOND), expires_at)'
        );
        $stmt->execute([
            ':bucket' => $bucket,
            ':janela' => $janelaSegundos,
            ':janela2' => $janelaSegundos,
        ]);

        $ler = $pdo->prepare('SELECT hits FROM rate_limits WHERE bucket = ?');
        $ler->execute([$bucket]);
        $hits = (int) $ler->fetchColumn();

        self::limparVencidos($pdo);

        return max(0, $limite - $hits);
    }

    /** Zera o contador — chamado após uma tentativa bem-sucedida. */
    public static function limpar(string $acao, string $alvo): void
    {
        $stmt = Database::getConnection()->prepare('DELETE FROM rate_limits WHERE bucket = ?');
        $stmt->execute([self::bucket($acao, $alvo)]);
    }

    /** Segundos até liberar, para informar quem foi bloqueado. */
    public static function esperaSegundos(string $acao, string $alvo): int
    {
        $stmt = Database::getConnection()->prepare(
            'SELECT GREATEST(0, TIMESTAMPDIFF(SECOND, NOW(), expires_at)) FROM rate_limits WHERE bucket = ?'
        );
        $stmt->execute([self::bucket($acao, $alvo)]);

        return (int) $stmt->fetchColumn();
    }

    public static function ip(): string
    {
        return (string) ($_SERVER['REMOTE_ADDR'] ?? 'cli');
    }

    /**
     * Chave curta e estável.
     *
     * O alvo entra como hash: e-mail é dado pessoal e não precisa ficar legível
     * numa tabela de contadores.
     */
    private static function bucket(string $acao, string $alvo): string
    {
        return $acao . ':' . self::ip() . ':' . substr(hash('sha256', mb_strtolower(trim($alvo))), 0, 32);
    }

    /** Faxina barata: roda junto das escritas, sem precisar de cron. */
    private static function limparVencidos(PDO $pdo): void
    {
        // Só de vez em quando — apagar a cada requisição seria desperdício.
        if (random_int(1, 50) !== 1) {
            return;
        }
        $pdo->exec('DELETE FROM rate_limits WHERE expires_at < NOW() - INTERVAL 1 HOUR');
    }
}
