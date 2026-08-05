<?php
/**
 * Popula um banco vazio com uma loja de exemplo utilizável.
 *
 *   php backend/scripts/seed.php            popula (recusa se já houver lojas)
 *   php backend/scripts/seed.php --force    apaga as lojas existentes e repopula
 *
 * Escrito em PHP, e não como .sql, de propósito: assim usa os próprios helpers
 * do domínio (product_variants_matrix_to_rows, slugify, password_hash) e os
 * dados saem no formato exato que o painel e a vitrine esperam — em especial a
 * matriz de variações, cujo layout de linhas _meta/combinacao é fácil de errar
 * escrevendo INSERT à mão.
 *
 * As fotos de produto são as que já existem em frontend/public/uploads/products;
 * o seed apenas as associa, não gera imagem nenhuma.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require dirname(__DIR__) . '/bootstrap.php';
require PLATAFORM_BACKEND . '/app/Helpers/functions.php';

use App\Database\Database;

$force = in_array('--force', $argv, true);
$pdo = Database::getConnection();

$lojas = (int) $pdo->query('SELECT COUNT(*) FROM stores')->fetchColumn();
if ($lojas > 0 && !$force) {
    fwrite(STDERR, "O banco já tem {$lojas} loja(s). Use --force para apagar e repopular.\n");
    exit(1);
}

// Fotos disponíveis em disco, na ordem em que foram enviadas.
$dirFotos = PLATAFORM_ROOT . '/frontend/public/uploads/products';
$fotos = [];
foreach (glob($dirFotos . '/*.{jpg,jpeg,png,gif,webp}', GLOB_BRACE) ?: [] as $f) {
    $fotos[] = 'products/' . basename($f);
}
sort($fotos);
$proximaFoto = 0;
$pegarFotos = static function (int $quantas) use (&$fotos, &$proximaFoto): array {
    $out = [];
    for ($i = 0; $i < $quantas && $proximaFoto < count($fotos); $i++) {
        $out[] = $fotos[$proximaFoto++];
    }

    return $out;
};

$banner = glob(PLATAFORM_ROOT . '/frontend/public/uploads/store-banners/*/*') ?: [];
$icone = glob(PLATAFORM_ROOT . '/frontend/public/uploads/store-icons/*/*') ?: [];
$relativo = static function (string $abs): ?string {
    $base = str_replace('\\', '/', PLATAFORM_ROOT . '/frontend/public/uploads/');
    $abs = str_replace('\\', '/', $abs);

    return strpos($abs, $base) === 0 ? substr($abs, strlen($base)) : null;
};

$SENHA = 'gerente123';

$resultado = Database::transaction(function (PDO $pdo) use ($force, $pegarFotos, $banner, $icone, $relativo, $SENHA): array {
    if ($force) {
        // ON DELETE CASCADE cuida de produtos, usuários, pedidos e afins.
        $pdo->exec('DELETE FROM stores');
        $pdo->exec("DELETE FROM users WHERE store_id IS NULL");
    }

    // ------------------------------------------------------------------ loja
    $pdo->prepare(
        'INSERT INTO stores (name, slogan, slug, category, city, phone, banner_path, store_icon_path, background_color)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
    )->execute([
        'Loja Exemplo',
        'Tudo para treinar melhor',
        'teste',
        'Moda esportiva',
        'São Paulo',
        '(11) 90000-0000',
        $banner !== [] ? $relativo($banner[0]) : null,
        $icone !== [] ? $relativo($icone[0]) : null,
        '#0F172A',
    ]);
    $storeId = (int) $pdo->lastInsertId();

    $pdo->prepare('INSERT INTO store_pix_configs (store_id, pix_key, pix_key_type, merchant_name, merchant_city) VALUES (?, ?, ?, ?, ?)')
        ->execute([$storeId, 'exemplo@loja.test', 'email', 'Loja Exemplo', 'Sao Paulo']);

    // -------------------------------------------------------------- usuários
    $criarUsuario = static function (PDO $pdo, ?int $storeId, string $nome, string $email, string $tipo) use ($SENHA): int {
        $pdo->prepare('INSERT INTO users (store_id, name, email, password, user_type) VALUES (?, ?, ?, ?, ?)')
            ->execute([$storeId, $nome, $email, password_hash($SENHA, PASSWORD_DEFAULT), $tipo]);

        return (int) $pdo->lastInsertId();
    };
    $criarUsuario($pdo, $storeId, 'Gerente Exemplo', 'gerente@loja.test', 'gerente');
    $criarUsuario($pdo, $storeId, 'Funcionário Exemplo', 'funcionario@loja.test', 'funcionario');
    $criarUsuario($pdo, null, 'Cliente Exemplo', 'cliente@loja.test', 'cliente');

    // --------------------------------------------------- categorias da vitrine
    $categorias = [];
    $sqlCat = $pdo->prepare('INSERT INTO store_vitrine_categories (store_id, name, icon_key, sort_order) VALUES (?, ?, ?, ?)');
    foreach ([['Masculino', 'masculino'], ['Feminino', 'feminino'], ['Treino', 'treino'], ['Corrida', 'corrida']] as $i => [$nome, $icone_]) {
        $sqlCat->execute([$storeId, $nome, $icone_, $i]);
        $categorias[$nome] = (int) $pdo->lastInsertId();
    }

    // -------------------------------------------------------------- produtos
    $sqlProd = $pdo->prepare(
        'INSERT INTO products (store_id, vitrine_category_id, name, description, cost_price, sale_price, stock_quantity, min_stock)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
    );
    $sqlPivot = $pdo->prepare('INSERT INTO product_vitrine_categories (product_id, vitrine_category_id) VALUES (?, ?)');
    $sqlImg = $pdo->prepare('INSERT INTO product_images (product_id, file_path, sort_order) VALUES (?, ?, ?)');
    $sqlVar = $pdo->prepare('INSERT INTO product_variants (product_id, variant_type, variant_value, stock_quantity, sort_order) VALUES (?, ?, ?, ?, ?)');

    $criarProduto = static function (array $p) use ($pdo, $storeId, $sqlProd, $sqlPivot, $sqlImg, $sqlVar, $pegarFotos, $categorias): int {
        $catId = $categorias[$p['categoria']] ?? null;
        $sqlProd->execute([
            $storeId,
            $catId,
            $p['nome'],
            $p['descricao'],
            $p['custo'],
            $p['preco'],
            $p['estoque'] ?? 0,
            $p['min_estoque'] ?? 2,
        ]);
        $id = (int) $pdo->lastInsertId();
        if ($catId !== null) {
            $sqlPivot->execute([$id, $catId]);
        }
        foreach ($pegarFotos($p['fotos'] ?? 1) as $i => $caminho) {
            $sqlImg->execute([$id, $caminho, $i]);
        }
        if (isset($p['matriz'])) {
            $linhas = product_variants_matrix_to_rows($p['matriz']);
            $ordem = 0;
            $total = 0;
            foreach ($linhas as $linha) {
                $sqlVar->execute([$id, $linha['variant_type'], $linha['variant_value'], $linha['stock_quantity'], $ordem++]);
                $total += (int) $linha['stock_quantity'];
            }
            // products.stock_quantity é o total derivado das variações.
            $pdo->prepare('UPDATE products SET stock_quantity = ? WHERE id = ?')->execute([$total, $id]);
        }

        return $id;
    };

    $criarProduto([
        'nome' => 'Tênis de Corrida Masculino',
        'descricao' => 'Amortecimento leve para treinos diários em asfalto.',
        'categoria' => 'Corrida',
        'custo' => 149.90,
        'preco' => 299.99,
        'min_estoque' => 3,
        'fotos' => 3,
        'matriz' => [
            'axis' => 'numeracao',
            'colors' => ['Preto', 'Branco', 'Vermelho'],
            'sizes' => ['39', '40', '41', '42'],
            'color_meta' => ['Preto' => '#171717', 'Branco' => '#F8FAFC', 'Vermelho' => '#DC2626'],
            'stock' => [
                '39' => ['Preto' => 4, 'Branco' => 2, 'Vermelho' => 1],
                '40' => ['Preto' => 6, 'Branco' => 3, 'Vermelho' => 2],
                '41' => ['Preto' => 6, 'Branco' => 3, 'Vermelho' => 0],
                '42' => ['Preto' => 2, 'Branco' => 1, 'Vermelho' => 0],
            ],
        ],
    ]);

    $criarProduto([
        'nome' => 'Camiseta Dry Fit Masculina',
        'descricao' => 'Tecido leve com secagem rápida.',
        'categoria' => 'Masculino',
        'custo' => 29.90,
        'preco' => 79.90,
        'min_estoque' => 5,
        'fotos' => 3,
        'matriz' => [
            'axis' => 'tamanho',
            'colors' => ['Preto', 'Azul'],
            'sizes' => ['P', 'M', 'G', 'GG'],
            'color_meta' => ['Preto' => '#171717', 'Azul' => '#2563EB'],
            'stock' => [
                'P' => ['Preto' => 5, 'Azul' => 3],
                'M' => ['Preto' => 8, 'Azul' => 6],
                'G' => ['Preto' => 7, 'Azul' => 4],
                'GG' => ['Preto' => 3, 'Azul' => 1],
            ],
        ],
    ]);

    $criarProduto([
        'nome' => 'Legging Feminina Alta Compressão',
        'descricao' => 'Cintura alta, sem transparência.',
        'categoria' => 'Feminino',
        'custo' => 45.00,
        'preco' => 129.90,
        'min_estoque' => 4,
        'fotos' => 3,
        'matriz' => [
            'axis' => 'tamanho',
            'colors' => ['Preto', 'Verde'],
            'sizes' => ['P', 'M', 'G'],
            'color_meta' => ['Preto' => '#171717', 'Verde' => '#16A34A'],
            'stock' => [
                'P' => ['Preto' => 6, 'Verde' => 2],
                'M' => ['Preto' => 9, 'Verde' => 4],
                'G' => ['Preto' => 5, 'Verde' => 1],
            ],
        ],
    ]);

    // Sem variação: caminho simples de estoque, o que exercita decrementStock().
    $criarProduto([
        'nome' => 'Garrafa Térmica 1L',
        'descricao' => 'Mantém a temperatura por até 12 horas.',
        'categoria' => 'Treino',
        'custo' => 22.00,
        'preco' => 69.90,
        'estoque' => 25,
        'min_estoque' => 5,
        'fotos' => 3,
    ]);
    $criarProduto([
        'nome' => 'Corda de Pular Profissional',
        'descricao' => 'Rolamento duplo, cabo de aço revestido.',
        'categoria' => 'Treino',
        'custo' => 18.00,
        'preco' => 49.90,
        'estoque' => 12,
        'min_estoque' => 4,
        'fotos' => 3,
    ]);
    $criarProduto([
        'nome' => 'Mochila Esportiva 30L',
        'descricao' => 'Compartimento separado para calçados.',
        'categoria' => 'Masculino',
        'custo' => 60.00,
        'preco' => 159.90,
        'estoque' => 3,
        'min_estoque' => 5, // abaixo do mínimo de propósito: alimenta o alerta de estoque baixo
        'fotos' => 3,
    ]);
    $criarProduto([
        'nome' => 'Top Fitness Feminino',
        'descricao' => 'Alça larga com bojo removível.',
        'categoria' => 'Feminino',
        'custo' => 25.00,
        'preco' => 89.90,
        'estoque' => 14,
        'min_estoque' => 4,
        'fotos' => 2,
    ]);

    // ------------------------------------------------------------------ metas
    $periodo = date('Y-m');
    $pdo->prepare('INSERT INTO store_goals (store_id, period, goal_amount) VALUES (?, ?, ?)')
        ->execute([$storeId, $periodo, 15000.00]);

    $produtos = (int) $pdo->query('SELECT COUNT(*) FROM products')->fetchColumn();

    return ['store_id' => $storeId, 'slug' => 'teste', 'produtos' => $produtos];
});

$url = rtrim((string) (getenv('APP_URL') ?: 'http://localhost/plataform_stores/public'), '/');

echo PHP_EOL;
echo "Loja de exemplo criada." . PHP_EOL . PHP_EOL;
echo '  Vitrine   ' . $url . '/loja/' . $resultado['slug'] . PHP_EOL;
echo '  Painel    ' . $url . '/painel/' . $resultado['slug'] . PHP_EOL;
echo '  Produtos  ' . $resultado['produtos'] . ' (3 com matriz de cor/tamanho, 1 abaixo do estoque mínimo)' . PHP_EOL;
echo PHP_EOL;
echo '  Acessos (senha «' . $SENHA . '» para todos):' . PHP_EOL;
echo '    gerente@loja.test       gerente da loja' . PHP_EOL;
echo '    funcionario@loja.test   funcionário' . PHP_EOL;
echo '    cliente@loja.test       cliente da plataforma' . PHP_EOL;
echo PHP_EOL;
echo '  Troque essas senhas antes de expor a instalação a qualquer rede.' . PHP_EOL . PHP_EOL;
