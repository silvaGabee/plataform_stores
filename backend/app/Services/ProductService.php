<?php

namespace App\Services;

use App\Repositories\ProductRepository;
use App\Repositories\ProductImageRepository;
use App\Repositories\ProductVariantRepository;
use App\Repositories\StockMovementRepository;

class ProductService
{
    private ProductVariantRepository $variantRepo;

    public function __construct(
        private ProductRepository $productRepo,
        private StockMovementRepository $stockMovementRepo,
        ?ProductImageRepository $imageRepo = null,
        ?ProductVariantRepository $variantRepo = null
    ) {
        $this->imageRepo = $imageRepo ?? new ProductImageRepository();
        $this->variantRepo = $variantRepo ?? new ProductVariantRepository();
    }

    private function attachVariants(array &$product): void
    {
        $productId = (int) ($product['id'] ?? 0);
        if ($productId <= 0) {
            $product['variants'] = [];

            return;
        }
        $rows = $this->variantRepo->listByProduct($productId);
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
    }

    public function listForStore(int $storeId, bool $onlyWithStock = false): array
    {
        $products = $this->productRepo->listByStore($storeId, $onlyWithStock);
        foreach ($products as &$p) {
            $p['images'] = $this->imageRepo->getByProductId((int) $p['id']);
            $this->attachImageUrls($p['images'], (int) $p['id']);
            $this->attachVariants($p);
        }

        return $products;
    }

    public function listForStoreByCategory(int $storeId, int $categoryId, bool $onlyWithStock = false): array
    {
        $products = $this->productRepo->listByStoreAndCategory($storeId, $categoryId, $onlyWithStock);
        foreach ($products as &$p) {
            $p['images'] = $this->imageRepo->getByProductId((int) $p['id']);
            $this->attachImageUrls($p['images'], (int) $p['id']);
            $this->attachVariants($p);
        }

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
        $rawVariants = $data['variants_matrix'] ?? $data['variants'] ?? [];
        unset($data['variants_matrix']);
        $variants = normalize_product_variants_input($rawVariants);
        unset($data['variants']);
        $data['store_id'] = $storeId;
        $data['min_stock'] = (int) ($data['min_stock'] ?? 0);
        if ($variants !== []) {
            $data['stock_quantity'] = product_variants_total_stock($variants);
        } else {
            $data['stock_quantity'] = (int) ($data['stock_quantity'] ?? 0);
        }
        $id = $this->productRepo->create($data);
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
        if ($variants !== null) {
            $this->variantRepo->replaceForProduct($id, $variants);
            $data['stock_quantity'] = $variants !== []
                ? product_variants_total_stock($variants)
                : (int) ($data['stock_quantity'] ?? $product['stock_quantity']);
        }
        $this->productRepo->update($id, $data);

        return $this->getByIdAndStore($id, $storeId);
    }

    public function adjustStock(int $productId, int $storeId, int $quantity, string $type, ?int $userId, ?string $reason = null): bool
    {
        $product = $this->productRepo->findByIdAndStore($productId, $storeId);
        if (!$product) return false;
        $current = (int) $product['stock_quantity'];
        if ($type === 'saida' || $type === 'ajuste') {
            $newQty = $current - abs($quantity);
            if ($newQty < 0) return false;
        } else {
            $newQty = $current + abs($quantity);
        }
        $this->productRepo->updateStock($productId, $newQty);
        $this->stockMovementRepo->create([
            'store_id'   => $storeId,
            'product_id' => $productId,
            'user_id'    => $userId,
            'type'       => $type,
            'quantity'   => abs($quantity),
            'reason'     => $reason,
        ]);
        return true;
    }

    public function listLowStock(int $storeId): array
    {
        return $this->productRepo->listLowStock($storeId);
    }
}
