<?php

namespace App\Services;

use App\Repositories\ProductRepository;
use App\Repositories\ProductImageRepository;
use App\Repositories\ProductVariantRepository;
use App\Repositories\ProductVitrineCategoryRepository;
use App\Repositories\StockMovementRepository;

class ProductService
{
    // Declaradas explicitamente: $imageRepo era atribuída no construtor sem
    // existir como propriedade, funcionando por propriedade dinâmica — algo
    // que o PHP 8.2 já deprecia e o 9 remove.
    private ProductImageRepository $imageRepo;
    private ProductVariantRepository $variantRepo;
    private ProductVitrineCategoryRepository $productCategoryRepo;

    public function __construct(
        private ProductRepository $productRepo,
        private StockMovementRepository $stockMovementRepo,
        ?ProductImageRepository $imageRepo = null,
        ?ProductVariantRepository $variantRepo = null,
        ?ProductVitrineCategoryRepository $productCategoryRepo = null
    ) {
        $this->imageRepo = $imageRepo ?? new ProductImageRepository();
        $this->variantRepo = $variantRepo ?? new ProductVariantRepository();
        $this->productCategoryRepo = $productCategoryRepo ?? new ProductVitrineCategoryRepository();
    }

    private function attachVitrineCategories(array &$product): void
    {
        $productId = (int) ($product['id'] ?? 0);
        $ids = $productId > 0 ? $this->productCategoryRepo->listIdsByProduct($productId) : [];
        $this->applyVitrineCategories($product, $ids);
    }

    /**
     * Aplica ao produto as categorias já carregadas.
     * Separado de attachVitrineCategories para servir também ao carregamento
     * em lote, sem duplicar a regra do campo legado.
     *
     * @param list<int> $ids
     */
    private function applyVitrineCategories(array &$product, array $ids): void
    {
        if ((int) ($product['id'] ?? 0) <= 0) {
            $product['vitrine_category_ids'] = [];

            return;
        }
        // Produtos antigos guardavam uma única categoria na coluna do produto,
        // antes de existir a tabela de ligação.
        if ($ids === [] && !empty($product['vitrine_category_id'])) {
            $legacy = (int) $product['vitrine_category_id'];
            if ($legacy > 0) {
                $ids = [$legacy];
            }
        }
        $product['vitrine_category_ids'] = $ids;
        $product['vitrine_category_id'] = $ids[0] ?? null;
    }

    /** @param array<string, mixed> $data */
    private function resolveVitrineCategoryIdsFromData(array $data): array
    {
        if (array_key_exists('vitrine_category_ids', $data) && is_array($data['vitrine_category_ids'])) {
            $out = [];
            foreach ($data['vitrine_category_ids'] as $id) {
                $id = (int) $id;
                if ($id > 0) {
                    $out[] = $id;
                }
            }

            return array_values(array_unique($out));
        }
        if (array_key_exists('vitrine_category_id', $data)) {
            $single = $data['vitrine_category_id'];
            if ($single === null || $single === '' || $single === false) {
                return [];
            }
            $id = (int) $single;

            return $id > 0 ? [$id] : [];
        }

        return [];
    }

    /** @param array<string, mixed> $data */
    private function syncVitrineCategories(int $productId, array $data): void
    {
        if (!array_key_exists('vitrine_category_ids', $data) && !array_key_exists('vitrine_category_id', $data)) {
            return;
        }
        $ids = $this->resolveVitrineCategoryIdsFromData($data);
        $this->productCategoryRepo->replaceForProduct($productId, $ids);
        $this->productRepo->updateVitrineCategoryId($productId, $ids[0] ?? null);
    }

    private function attachVariants(array &$product): void
    {
        $productId = (int) ($product['id'] ?? 0);
        $rows = $productId > 0 ? $this->variantRepo->listByProduct($productId) : [];
        $this->applyVariants($product, $rows);
    }

    /**
     * Aplica ao produto as variações já carregadas.
     * Separado de attachVariants para servir também ao carregamento em lote.
     *
     * @param list<array<string, mixed>> $rows
     */
    private function applyVariants(array &$product, array $rows): void
    {
        if ((int) ($product['id'] ?? 0) <= 0) {
            $product['variants'] = [];
            $product['variants_matrix'] = null;
            $product['display_name'] = product_display_name($product);

            return;
        }
        $product['variants'] = array_map(static function (array $row): array {
            $type = (string) $row['variant_type'];
            $value = (string) $row['variant_value'];
            $label = product_variant_type_label($type);
            if ($type === 'combinacao' && str_contains($value, '|')) {
                [$cor, $tam] = explode('|', $value, 2);
                $label = trim($cor) . ' · ' . trim($tam);
            }

            return [
                'id' => (int) $row['id'],
                'variant_type' => $type,
                'variant_type_label' => $label,
                'variant_value' => $value,
                'stock_quantity' => (int) $row['stock_quantity'],
            ];
        }, $rows);
        $matrix = product_variants_rows_to_matrix($rows);
        $product['variants_matrix'] = $matrix;
        $product['display_name'] = product_display_name($product);
        $product['effective_price'] = product_effective_price($product);
        $product['on_sale'] = product_on_sale($product);
    }

    public function listForStore(int $storeId, bool $onlyWithStock = false): array
    {
        return $this->hydrateMany($this->productRepo->listByStore($storeId, $onlyWithStock));
    }

    public function listForStoreByCategory(int $storeId, int $categoryId, bool $onlyWithStock = false): array
    {
        return $this->hydrateMany($this->productRepo->listByStoreAndCategory($storeId, $categoryId, $onlyWithStock));
    }

    /**
     * Completa uma lista de produtos com imagens, variações e categorias.
     *
     * Três consultas no total, independentemente de quantos produtos houver.
     * Antes cada produto disparava as suas próprias três, dentro do laço: uma
     * vitrine com 50 produtos fazia 151 idas ao banco para montar a página.
     *
     * @param list<array<string, mixed>> $products
     * @return list<array<string, mixed>>
     */
    private function hydrateMany(array $products): array
    {
        if ($products === []) {
            return [];
        }
        $ids = array_map(static fn (array $p): int => (int) ($p['id'] ?? 0), $products);
        $imagens = $this->imageRepo->getByProductIds($ids);
        $variacoes = $this->variantRepo->listByProducts($ids);
        $categorias = $this->productCategoryRepo->listIdsByProducts($ids);

        foreach ($products as &$p) {
            $pid = (int) ($p['id'] ?? 0);
            $p['images'] = $imagens[$pid] ?? [];
            $this->attachImageUrls($p['images'], $pid);
            $this->applyVariants($p, $variacoes[$pid] ?? []);
            $this->applyVitrineCategories($p, $categorias[$pid] ?? []);
        }
        unset($p);

        return $products;
    }

    public function getByIdAndStore(int $id, int $storeId): ?array
    {
        $product = $this->productRepo->findByIdAndStore($id, $storeId);
        if (!$product) {
            return null;
        }
        $product['images'] = $this->imageRepo->getByProductId((int) $product['id']);
        $this->attachImageUrls($product['images'], (int) $product['id']);
        $this->attachVariants($product);
        $this->attachVitrineCategories($product);

        return $product;
    }

    private function attachImageUrls(array &$images, int $productId = 0): void
    {
        $q = $productId ? '?p=' . $productId : '';
        $minSort = null;
        foreach ($images as $img) {
            $so = (int) ($img['sort_order'] ?? 0);
            if ($minSort === null || $so < $minSort) {
                $minSort = $so;
            }
        }
        foreach ($images as &$img) {
            $path = isset($img['file_path']) ? str_replace('\\', '/', (string) $img['file_path']) : '';
            $path = ltrim($path, '/');
            if ($path !== '') {
                $img['url'] = base_url('uploads/' . $path) . $q;
            }
            $img['is_cover'] = $minSort !== null && (int) ($img['sort_order'] ?? 0) === $minSort;
        }
        unset($img);
    }

    public function setProductCoverImage(int $productId, int $storeId, int $imageId): ?array
    {
        $product = $this->productRepo->findByIdAndStore($productId, $storeId);
        if (!$product) {
            return null;
        }
        $img = $this->imageRepo->find($imageId);
        if (!$img || (int) $img['product_id'] !== $productId) {
            return null;
        }
        $this->imageRepo->setCover($productId, $imageId);

        return $this->getByIdAndStore($productId, $storeId);
    }

    public function create(int $storeId, array $data): array
    {
        $data['name'] = trim((string) ($data['name'] ?? ''));
        if ($data['name'] === '') {
            throw new \InvalidArgumentException('Informe o nome do produto.');
        }
        $rawVariants = $data['variants_matrix'] ?? $data['variants'] ?? [];
        unset($data['variants_matrix']);
        $variants = normalize_product_variants_input($rawVariants);
        unset($data['variants']);
        $data['store_id'] = $storeId;
        $data = self::validarPrecos($data, null);
        if ($variants !== []) {
            $data['stock_quantity'] = product_variants_total_stock($variants);
        } else {
            $data['stock_quantity'] = (int) ($data['stock_quantity'] ?? 0);
        }
        $categoryIds = $this->resolveVitrineCategoryIdsFromData($data);
        $data['vitrine_category_id'] = $categoryIds[0] ?? null;
        $id = $this->productRepo->create($data);
        $this->syncVitrineCategories($id, [
            'vitrine_category_ids' => $categoryIds,
            'vitrine_category_id' => $data['vitrine_category_id'],
        ]);
        if ($variants !== []) {
            $this->variantRepo->replaceForProduct($id, $variants);
        }
        if (!empty($data['image_paths']) && is_array($data['image_paths'])) {
            foreach ($data['image_paths'] as $i => $path) {
                $this->imageRepo->add($id, $path, $i);
            }
        }
        if ($data['stock_quantity'] > 0) {
            $this->stockMovementRepo->create([
                'store_id'   => $storeId,
                'product_id' => $id,
                'type'       => 'entrada',
                'quantity'   => $data['stock_quantity'],
                'reason'     => $variants !== [] ? 'Estoque inicial (variações)' : 'Estoque inicial',
            ]);
        }

        return $this->getByIdAndStore($id, $storeId) ?? $this->productRepo->find($id);
    }

    public function update(int $id, int $storeId, array $data): ?array
    {
        $product = $this->productRepo->findByIdAndStore($id, $storeId);
        if (!$product) {
            return null;
        }
        $hasVariantsKey = array_key_exists('variants', $data) || array_key_exists('variants_matrix', $data);
        $variants = null;
        if ($hasVariantsKey) {
            $rawVariants = $data['variants_matrix'] ?? $data['variants'] ?? [];
            unset($data['variants_matrix'], $data['variants']);
            $variants = normalize_product_variants_input($rawVariants);
        }
        $data = self::validarPrecos($data, $product);
        $estoqueAntes = $this->stockSnapshot($id, $product);
        if ($variants !== null) {
            $this->variantRepo->replaceForProduct($id, $variants);
            $data['stock_quantity'] = $variants !== []
                ? product_variants_total_stock($variants)
                : (int) ($data['stock_quantity'] ?? $product['stock_quantity']);
        }
        if (array_key_exists('name', $data)) {
            $data['name'] = trim((string) $data['name']);
            if ($data['name'] === '') {
                throw new \InvalidArgumentException('Informe o nome do produto.');
            }
        }

        // Atualização parcial (ex.: só variants_matrix no estoque) — não apagar nome nem outros campos.
        $payload = [
            'name'            => array_key_exists('name', $data) ? $data['name'] : (string) ($product['name'] ?? ''),
            'description'     => array_key_exists('description', $data) ? $data['description'] : $product['description'],
            'cost_price'      => array_key_exists('cost_price', $data) ? $data['cost_price'] : $product['cost_price'],
            'sale_price'      => array_key_exists('sale_price', $data) ? $data['sale_price'] : $product['sale_price'],
            'promo_price'     => array_key_exists('promo_price', $data) ? $data['promo_price'] : ($product['promo_price'] ?? null),
            'stock_quantity'  => array_key_exists('stock_quantity', $data) ? $data['stock_quantity'] : $product['stock_quantity'],
            'min_stock'       => array_key_exists('min_stock', $data) ? $data['min_stock'] : $product['min_stock'],
        ];
        if (array_key_exists('vitrine_category_id', $data)) {
            $payload['vitrine_category_id'] = $data['vitrine_category_id'];
        }
        $this->productRepo->update($id, $payload);
        $this->syncVitrineCategories($id, $data);
        $this->registrarDiferencasDeEstoque($storeId, $id, $estoqueAntes, $this->stockSnapshot($id, null));

        return $this->getByIdAndStore($id, $storeId);
    }

    /**
     * Estoque atual por variação (chave "Cor|Tamanho") ou, sem variações, o
     * total do produto sob a chave ''.
     *
     * @return array<string, int>
     */
    private function stockSnapshot(int $productId, ?array $product): array
    {
        $map = [];
        foreach ($this->variantRepo->listByProduct($productId) as $row) {
            if (($row['variant_type'] ?? '') === 'combinacao') {
                $map[(string) $row['variant_value']] = (int) $row['stock_quantity'];
            }
        }
        if ($map === []) {
            $product = $product ?? $this->productRepo->find($productId);
            $map[''] = (int) ($product['stock_quantity'] ?? 0);
        }

        return $map;
    }

    /**
     * Toda mudança de saldo feita pelo painel (matriz de estoque ou edição do
     * produto) vira movimentação. Antes a reposição pela matriz cor × tamanho
     * alterava o saldo sem deixar rastro, e o histórico não fechava com o
     * estoque real.
     *
     * @param array<string, int> $antes
     * @param array<string, int> $depois
     */
    private function registrarDiferencasDeEstoque(int $storeId, int $productId, array $antes, array $depois): void
    {
        foreach (array_unique(array_merge(array_keys($antes), array_keys($depois))) as $chave) {
            $diff = ($depois[$chave] ?? 0) - ($antes[$chave] ?? 0);
            if ($diff === 0) {
                continue;
            }
            $rotulo = '';
            if ($chave !== '' && str_contains($chave, '|')) {
                [$cor, $tam] = explode('|', $chave, 2);
                $rotulo = ' (' . trim($cor) . ' · ' . trim($tam) . ')';
            }
            $this->stockMovementRepo->create([
                'store_id'   => $storeId,
                'product_id' => $productId,
                'type'       => $diff > 0 ? 'entrada' : 'ajuste',
                'quantity'   => abs($diff),
                'reason'     => ($diff > 0 ? 'Reposição de estoque' : 'Ajuste manual (baixa)') . $rotulo,
            ]);
        }
    }

    /**
     * Preços e estoque mínimo não podem ser negativos, e a promoção precisa
     * ser menor que o preço normal. Antes um produto a R$ -50,00 era aceito,
     * publicado na vitrine e abatia o total de qualquer carrinho.
     *
     * @param array<string, mixed> $data
     * @param array<string, mixed>|null $atual
     * @return array<string, mixed>
     */
    public static function validarPrecos(array $data, ?array $atual): array
    {
        foreach (['cost_price' => 'O custo', 'sale_price' => 'O preço de venda'] as $campo => $nome) {
            if (array_key_exists($campo, $data)) {
                $v = round((float) $data[$campo], 2);
                if ($v < 0) {
                    throw new \InvalidArgumentException($nome . ' não pode ser negativo.');
                }
                $data[$campo] = $v;
            }
        }
        $venda = (float) ($data['sale_price'] ?? $atual['sale_price'] ?? 0);
        if (($atual === null || array_key_exists('sale_price', $data)) && $venda <= 0) {
            throw new \InvalidArgumentException('Informe um preço de venda maior que zero.');
        }
        if (array_key_exists('min_stock', $data) || $atual === null) {
            $data['min_stock'] = max(0, (int) ($data['min_stock'] ?? 0));
        }
        if (array_key_exists('promo_price', $data)) {
            $p = $data['promo_price'];
            if ($p === null || $p === '' || (float) $p <= 0) {
                $data['promo_price'] = null;
            } else {
                $p = round((float) $p, 2);
                if ($p >= $venda) {
                    throw new \InvalidArgumentException('O preço promocional precisa ser menor que o preço de venda.');
                }
                $data['promo_price'] = $p;
            }
        } elseif ($atual !== null && !empty($atual['promo_price']) && (float) $atual['promo_price'] >= $venda) {
            // O preço normal baixou até a promoção: ela deixa de fazer sentido.
            $data['promo_price'] = null;
        }

        return $data;
    }

    /**
     * Ajuste manual de estoque pelo painel, com o movimento correspondente.
     *
     * Em transação e com escrita atômica: eram duas escritas soltas em cima de
     * um valor lido antes (ler 10, gravar 8). Dois ajustes simultâneos se
     * sobrescreviam, e uma falha ao gravar o movimento deixava o estoque
     * alterado sem rastro de quem alterou.
     */
    public function adjustStock(int $productId, int $storeId, int $quantity, string $type, ?int $userId, ?string $reason = null): bool
    {
        $qty = abs($quantity);
        if ($qty < 1) {
            return false;
        }

        return \App\Database\Database::transaction(function () use ($productId, $storeId, $qty, $type, $userId, $reason): bool {
            $product = $this->productRepo->findByIdAndStore($productId, $storeId);
            if (!$product) {
                return false;
            }
            $isSaida = $type === 'saida' || $type === 'ajuste';
            $ok = $isSaida
                ? $this->productRepo->decrementStock($productId, $qty)
                : $this->productRepo->incrementStock($productId, $qty);
            if (!$ok) {
                // Saída maior que o saldo: o UPDATE não casa nenhuma linha.
                return false;
            }
            $this->stockMovementRepo->create([
                'store_id'   => $storeId,
                'product_id' => $productId,
                'user_id'    => $userId,
                'type'       => $type,
                'quantity'   => $qty,
                'reason'     => $reason,
            ]);

            return true;
        });
    }

    public function listLowStock(int $storeId): array
    {
        return $this->productRepo->listLowStock($storeId);
    }
}
