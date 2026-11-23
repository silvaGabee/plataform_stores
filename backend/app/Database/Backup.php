<?php

namespace App\Database;

use RuntimeException;

/**
 * Dump do banco para storage/backups/.
 *
 * Existe porque o schema é alterado por scripts, e script que altera schema sem
 * rede de segurança é como este projeto perdeu uma base de desenvolvimento
 * inteira: um DROP DATABASE de teste rodado contra o banco errado, sem binlog
 * (log_bin=OFF no XAMPP) e sem dump anterior, é perda definitiva.
 */
final class Backup
{
    /**
     * Gera o dump e devolve o caminho do arquivo.
     *
     * @throws RuntimeException se o mysqldump não for encontrado ou falhar
     */
    public static function dump(?string $rotulo = null): string
    {
        $cfg = require PLATAFORM_BACKEND . '/config/database.php';
        $dir = PLATAFORM_ROOT . '/storage/backups';
        if (!is_dir($dir) && !@mkdir($dir, 0775, true)) {
            throw new RuntimeException('Não foi possível criar ' . $dir);
        }

        $binario = self::localizarMysqldump();
        if ($binario === null) {
            throw new RuntimeException(
                'mysqldump não encontrado. Informe o caminho em MYSQLDUMP_PATH no .env.'
            );
        }

        $nome = $cfg['dbname'] . '-' . date('Y-m-d-His') . ($rotulo !== null ? '-' . $rotulo : '') . '.sql';
        $destino = $dir . '/' . $nome;

        // Lista de argumentos, sem shell: no Windows o cmd.exe comia %, ! e "
        // de uma senha passada por escapeshellarg, e o backup falhava. A senha
        // vai pela variável MYSQL_PWD — fora da linha de comando, sem o aviso
        // "Using a password on the command line" que o MySQL imprime. Os erros
        // saem num canal separado: antes iam para dentro do .sql (2>&1),
        // estragando o dump, e a mensagem de falha ficava vazia.
        $comando = [
            $binario,
            '--host=' . (string) $cfg['host'],
            '--user=' . (string) $cfg['username'],
            '--single-transaction',
            '--skip-comments',
            '--routines',
            '--events',
            (string) $cfg['dbname'],
        ];
        $ambiente = getenv();
        if ((string) $cfg['password'] !== '') {
            $ambiente['MYSQL_PWD'] = (string) $cfg['password'];
        }
        $proc = proc_open($comando, [1 => ['file', $destino, 'w'], 2 => ['pipe', 'w']], $pipes, null, $ambiente);
        if (!is_resource($proc)) {
            @unlink($destino);
            throw new RuntimeException('Não foi possível executar o mysqldump.');
        }
        $erros = trim((string) stream_get_contents($pipes[2]));
        fclose($pipes[2]);
        $codigo = proc_close($proc);

        if ($codigo !== 0) {
            @unlink($destino);
            throw new RuntimeException('mysqldump falhou (código ' . $codigo . '): ' . ($erros !== '' ? $erros : 'sem mensagem de erro.'));
        }
        if (!is_file($destino) || filesize($destino) < 100) {
            @unlink($destino);
            throw new RuntimeException('O dump saiu vazio; backup descartado.');
        }

        return $destino;
    }

    /** @return string|null caminho do executável, ou null se não achou */
    private static function localizarMysqldump(): ?string
    {
        $candidatos = array_filter([
            getenv('MYSQLDUMP_PATH') ?: null,
            'C:/xampp/mysql/bin/mysqldump.exe',
            '/usr/bin/mysqldump',
            '/usr/local/bin/mysqldump',
        ]);
        foreach ($candidatos as $c) {
            if (is_file($c)) {
                return $c;
            }
        }
        // Última tentativa: confiar no PATH.
        $probe = stripos(PHP_OS, 'WIN') === 0 ? 'where mysqldump' : 'command -v mysqldump';
        exec($probe . ' 2>&1', $saida, $codigo);
        if ($codigo === 0 && !empty($saida[0]) && is_file(trim($saida[0]))) {
            return trim($saida[0]);
        }

        return null;
    }
}
