<?php
/**
 * Verifica a identidade única: uma pessoa, uma senha, N lojas.
 *
 *   php backend/tools/identity_check.php [url-base]
 *
 * Cada asserção corresponde a um comportamento que estava errado enquanto a
 * mesma pessoa tinha uma linha em `users` por loja. Usa HTTP de verdade para o
 * que é de sessão, e o banco para o que é de estrutura.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require dirname(__DIR__) . '/bootstrap.php';
require PLATAFORM_BACKEND . '/app/Helpers/functions.php';

use App\Database\Database;
use App\Repositories\StoreMemberRepository;
use App\Repositories\UserRepository;

$base = rtrim($argv[1] ?? (getenv('APP_URL') ?: 'http://localhost/plataform_stores/public'), '/');
$pdo = Database::getConnection();
$falhas = 0;
$total = 0;

$ok = static function (string $nome, bool $passou) use (&$falhas, &$total): void {
    $total++;
    if (!$passou) {
        $falhas++;
    }
    echo ($passou ? '  PASSA  ' : '  FALHA  ') . $nome . PHP_EOL;
};

function req(string $metodo, string $url, ?string $jar, array $opts = []): array
{
    $ch = curl_init($url);
    $headers = ['Accept: application/json'];
    if (!empty($opts['csrf'])) {
        $headers[] = 'X-CSRF-Token: ' . $opts['csrf'];
    }
    if (isset($opts['json'])) {
        $headers[] = 'Content-Type: application/json';
    }
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => $metodo,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_HTTPHEADER => $headers,
    ]);
    if ($jar !== null) {
        curl_setopt($ch, CURLOPT_COOKIEJAR, $jar);
        curl_setopt($ch, CURLOPT_COOKIEFILE, $jar);
    }
    if (isset($opts['json'])) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($opts['json']));
    } elseif (isset($opts['form'])) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($opts['form']));
    }
    $corpo = curl_exec($ch);
    $codigo = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return ['status' => $codigo, 'body' => (string) $corpo];
}

function token(string $base, string $url, string $jar): string
{
    $p = req('GET', $base . $url, $jar);

    return preg_match('/name="csrf-token" content="([a-f0-9]+)"/', $p['body'], $m) ? $m[1] : '';
}

function entrar(string $base, string $email, string $senha, string $jar): bool
{
    @unlink($jar);
    $t = token($base, '/', $jar);
    $r = req('POST', $base . '/login', $jar, [
        'form' => ['auth_intent' => 'login', 'email' => $email, 'password' => $senha, '_csrf' => $t],
    ]);

    return $r['status'] === 302;
}

// ------------------------------------------------------------- estrutura
echo PHP_EOL . 'Estrutura' . PHP_EOL;

$cols = $pdo->query("SHOW COLUMNS FROM users")->fetchAll(PDO::FETCH_COLUMN);
$ok('users não tem mais store_id nem user_type',
    !in_array('store_id', $cols, true) && !in_array('user_type', $cols, true));

$idx = $pdo->query("SHOW INDEX FROM users WHERE Column_name = 'email' AND Non_unique = 0")->fetchAll();
$ok('users.email é UNIQUE global', $idx !== []);

// A unicidade precisa valer no banco, não só no PHP: com (email, store_id),
// NULL nunca colidia com NULL e duas contas de plataforma passavam.
$duplicouEmail = false;
try {
    $pdo->prepare('INSERT INTO users (name, email, password) VALUES (?, ?, ?)')
        ->execute(['Dup', 'gerente@loja.test', 'x']);
    $duplicouEmail = true;
    $pdo->exec("DELETE FROM users WHERE name = 'Dup'");
} catch (PDOException $e) {
    // Esperado.
}
$ok('banco recusa segundo registro com o mesmo e-mail', !$duplicouEmail);

// --------------------------------------------------- o bug que motivou a fase
echo PHP_EOL . 'O gerente mantém o painel depois de sair e entrar' . PHP_EOL;

$jar = sys_get_temp_dir() . '/ident.txt';
$entrou = entrar($base, 'gerente@loja.test', 'gerente123', $jar);
$ok('login do gerente', $entrou);
$antes = req('GET', $base . '/painel/teste', $jar)['status'];
$ok('painel acessível na 1a sessão (200)', $antes === 200);

req('GET', $base . '/sair', $jar);
entrar($base, 'gerente@loja.test', 'gerente123', $jar);
$depois = req('GET', $base . '/painel/teste', $jar)['status'];
// Antes, o login escolhia a linha "de plataforma", que não tinha cargo em loja
// nenhuma — e o gerente perdia o próprio painel até acertar por acidente.
$ok('painel continua acessível depois de sair e entrar (200)', $depois === 200);

// ------------------------------------------ mesma pessoa em duas lojas
echo PHP_EOL . 'Uma pessoa, várias lojas, uma senha' . PHP_EOL;

$userRepo = new UserRepository();
$memberRepo = new StoreMemberRepository();
$pdo->exec("DELETE FROM stores WHERE slug LIKE '__ident__%'");

$pdo->prepare('INSERT INTO stores (name, slug) VALUES (?, ?)')->execute(['Ident A', '__ident__a']);
$lojaA = (int) $pdo->lastInsertId();
$pdo->prepare('INSERT INTO stores (name, slug) VALUES (?, ?)')->execute(['Ident B', '__ident__b']);
$lojaB = (int) $pdo->lastInsertId();

$gerente = $userRepo->findByEmail('gerente@loja.test');
$memberRepo->upsert((int) $gerente['id'], $lojaA, 'gerente');
$memberRepo->upsert((int) $gerente['id'], $lojaB, 'funcionario');

$contas = (int) $pdo->query("SELECT COUNT(*) FROM users WHERE email = 'gerente@loja.test'")->fetchColumn();
$ok('continua sendo UMA conta com 3 vínculos', $contas === 1
    && count($memberRepo->storeIdsForUser((int) $gerente['id'])) === 3);

$ok('cargos independentes por loja',
    $memberRepo->role((int) $gerente['id'], $lojaA) === 'gerente'
    && $memberRepo->role((int) $gerente['id'], $lojaB) === 'funcionario');

entrar($base, 'gerente@loja.test', 'gerente123', $jar);
$a = req('GET', $base . '/painel/__ident__a/configuracoes', $jar)['status'];
$b = req('GET', $base . '/painel/__ident__b/configuracoes', $jar)['status'];
// Mesma sessão, mesma senha, permissões diferentes conforme a loja.
$ok('gerente entra em Configurações da loja A (200)', $a === 200);
$ok('funcionário NÃO entra em Configurações da loja B (302)', $b === 302);

// ------------------------------------------ remover da equipe ≠ apagar pessoa
echo PHP_EOL . 'Remover da equipe preserva a pessoa' . PHP_EOL;

$func = $userRepo->findByEmail('funcionario@loja.test');
$funcId = (int) $func['id'];
$memberRepo->upsert($funcId, $lojaA, 'funcionario');
$memberRepo->remove($funcId, $lojaA);

$ok('vínculo removido', $memberRepo->role($funcId, $lojaA) === null);
$ok('a pessoa continua existindo', $userRepo->find($funcId) !== null);
$ok('e mantém o vínculo com a outra loja', $memberRepo->storeIdsForUser($funcId) !== []);

// ------------------------------------------------------------------ limpeza
$pdo->exec("DELETE FROM stores WHERE slug LIKE '__ident__%'");
@unlink($jar);

echo PHP_EOL . $total . ' verificações — ' . ($falhas === 0 ? 'TUDO PASSOU' : $falhas . ' FALHARAM') . PHP_EOL;
exit($falhas === 0 ? 0 : 1);
