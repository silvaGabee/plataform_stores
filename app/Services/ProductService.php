<?php

namespace App\Services;

use App\Repositories\ProductRepository;
use App\Repositories\ProductImageRepository;
use App\Repositories\StockMovementRepository;

class ProductService
{
    public function __construct(
        private ProductRepository $productRepo,
        private StockMovementRepository $stockMovementRepo,
        private ?ProductImageRepository $imageRepo = null
    ) {
        $this->imageRepo = $imageRepo ?? new ProductImageRepository();
    }

    public function listForStore(int $storeId, bool $onlyWithStock = false): array
    {
        $products = $this->productRepo->listByStore($storeId, $onlyWithStock);
        foreach ($products as &$p) {
            $p['images'] = $this->imageRepo->getByProductId((int) $p['id']);
            $this->attachImageUrls($p['images'], (int) $p['id']);
        }
        return $products;
    }

    public function getByIdAndStore(int $id, int $storeId): ?array
    {
        $product = $this->productRepo->findByIdAndStore($id, $storeId);
        if (!$product) return null;
        $product['images'] = $this->imageRepo->getByProductId((int) $product['id']);
        $this->attachImageUrls($product['images'], (int) $product['id']);
        return $product;
    }

    private function attachImageUrls(array &$images, int $productId = 0): void
    {
        $q = $productId ? '?p=' . $productId : '';
        foreach ($images as &$img) {
            $path = isset($img['file_path']) ? str_replace('\\', '/', (string) $img['file_path']) : '';
            $path = ltrim($path, '/');
            if ($path !== '') {
                $img['url'] = base_url('uploads/' . $path) . $q;
            }
        }
    }

    public function create(int $storeId, array $data): array
    {
        $data['store_id'] = $storeId;
        $data['stock_quantity'] = (int) ($data['stock_quantity'] ?? 0);
        $data['min_stock'] = (int) ($data['min_stock'] ?? 0);
        $id = $this->productRepo->create($data);
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
                'reason'     => 'Estoque inicial',
            ]);
        }
        return $this->productRepo->find($id);
    }

    public function update(int $id, int $storeId, array $data): ?array
    {
        $product = $this->productRepo->findByIdAndStore($id, $storeId);
        if (!$product) return null;
        $this->productRepo->update($id, $data);
        return $this->productRepo->find($id);
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
