<?php

namespace App\Controllers\Api;

use App\Controllers\Controller;
use App\Services\ProductService;
use App\Repositories\ProductRepository;
use App\Repositories\StockMovementRepository;
use App\Repositories\OrderItemRepository;
use App\Repositories\OrderRepository;
use App\Repositories\ProductImageRepository;

class ProductApiController extends Controller
{
    public function list(string $slug): void
    {
        $storeId = $this->getStoreIdFromSlug($slug);
        if (!$storeId) {
            $this->json(['error' => 'Loja não encontrada'], 404);
        }
        $this->requireStorePanelAccess($storeId);
        $onlyStock = !empty($_GET['in_stock']);
        $service = new ProductService(new ProductRepository(), new StockMovementRepository(), new ProductImageRepository());
        $list = $service->listForStore($storeId, $onlyStock);
        $this->json(['products' => $list]);
    }

    public function get(string $slug, int $id): void
    {
        $storeId = $this->getStoreIdFromSlug($slug);
        if (!$storeId) {
            $this->json(['error' => 'Loja não encontrada'], 404);
        }
        $this->requireStorePanelAccess($storeId);
        $service = new ProductService(new ProductRepository(), new StockMovementRepository(), new ProductImageRepository());
        $product = $service->getByIdAndStore($id, $storeId);
        if (!$product) {
            $this->json(['error' => 'Produto não encontrado'], 404);
        }
        $this->json($product);
    }

    public function create(string $slug): void
    {
        $storeId = $this->getStoreIdFromSlug($slug);
        if (!$storeId) {
            $this->json(['error' => 'Loja não encontrada'], 404);
        }
        $this->requireStorePanelAccess($storeId);
        $input = $this->getProductInput();
        if (empty($input['name'])) {
            $this->json(['error' => 'Nome é obrigatório'], 400);
        }
        $service = new ProductService(new ProductRepository(), new StockMovementRepository(), new ProductImageRepository());
        try {
            $imagePaths = $input['image_paths'] ?? [];
            $imagesBase64 = !empty($input['images']) && is_array($input['images']) ? $input['images'] : [];
            unset($input['images']);
            $input['image_paths'] = $imagePaths;
            $product = $service->create($storeId, $input);
            $productId = (int) $product['id'];
            $imageRepo = new ProductImageRepository();
            foreach ($imagesBase64 as $i => $dataUrl) {
                if (!is_string($dataUrl)) continue;
                $path = save_product_image_from_base64($dataUrl);
                if ($path) {
                    $imageRepo->add($productId, $path, $i);
                }
            }
            $product = $service->getByIdAndStore($productId, $storeId);
            $this->json(['success' => true, 'product' => $product]);
        } catch (\Throwable $e) {
            $this->json(['error' => $e->getMessage()], 400);
        }
    }

    public function update(string $slug, int $id): void
    {
        $storeId = $this->getStoreIdFromSlug($slug);
        if (!$storeId) {
            $this->json(['error' => 'Loja não encontrada'], 404);
        }
        $this->requireStorePanelAccess($storeId);
        $input = $this->getJsonInput();
        $service = new ProductService(new ProductRepository(), new StockMovementRepository(), new ProductImageRepository());
        $product = $service->update($id, $storeId, $input);
        if (!$product) {
            $this->json(['error' => 'Produto não encontrado'], 404);
        }
        $this->json(['success' => true, 'product' => $product]);
    }

    public function adjustStock(string $slug, int $id): void
    {
        $storeId = $this->getStoreIdFromSlug($slug);
        if (!$storeId) {
            $this->json(['error' => 'Loja não encontrada'], 404);
        }
        $this->requireStorePanelAccess($storeId);
        $input = $this->getJsonInput();
        $type = $input['type'] ?? 'ajuste';
        $quantity = (int) ($input['quantity'] ?? 0);
        $reason = $input['reason'] ?? null;
        $userId = $_SESSION['user_id'] ?? null;
        if ($quantity <= 0) {
            $this->json(['error' => 'Quantidade inválida'], 400);
        }
        $service = new ProductService(new ProductRepository(), new StockMovementRepository(), new ProductImageRepository());
        $ok = $service->adjustStock($id, $storeId, $quantity, $type, $userId, $reason);
        if (!$ok) {
            $this->json(['error' => 'Não foi possível ajustar o estoque'], 400);
        }
        $product = (new ProductRepository())->find($id);
        $this->json(['success' => true, 'product' => $product]);
    }

    public function lowStock(string $slug): void
    {
        $storeId = $this->getStoreIdFromSlug($slug);
        if (!$storeId) {
            $this->json(['error' => 'Loja não encontrada'], 404);
        }
        $this->requireStorePanelAccess($storeId);
        $service = new ProductService(new ProductRepository(), new StockMovementRepository(), new ProductImageRepository());
        $list = $service->listLowStock($storeId);
        $this->json(['products' => $list]);
    }

    public function delete(string $slug, int $id): void
    {
        $storeId = $this->getStoreIdFromSlug($slug);
        if (!$storeId) {
            $this->json(['error' => 'Loja não encontrada'], 404);
            return;
        }
        $this->requireStorePanelAccess($storeId);
        $repo = new ProductRepository();
        $product = $repo->findByIdAndStore((int) $id, $storeId);
        if (!$product) {
            $this->json(['error' => 'Produto não encontrado'], 404);
            return;
        }
        try {
            $idInt = (int) $id;
            $this->deleteProductImageFiles($idInt);
            $orderItemRepo = new OrderItemRepository();
            $orderIds = $orderItemRepo->getOrderIdsByProductId($idInt);
            $orderItemRepo->deleteByProductId($idInt);
            $orderRepo = new OrderRepository();
            foreach ($orderIds as $orderId) {
                $orderRepo->recalcTotal($orderId);
            }
            $ok = $repo->delete($idInt);
            if (!$ok) {
                $this->json(['error' => 'Não foi possível excluir o produto.'], 400);
                return;
            }
            $this->json(['success' => true]);
        } catch (\Throwable $e) {
            $this->json(['error' => 'Não foi possível excluir: ' . $e->getMessage()], 400);
        }
    }

    /* Adiciona fotos: POST JSON com { "images": ["data:image/...;base64,..."] } OU multipart com images[]. */
    public function addImages(string $slug, int $id): void
    {
        $storeId = $this->getStoreIdFromSlug($slug);
        if (!$storeId) {
            $this->json(['error' => 'Loja não encontrada'], 404);
        }
        $this->requireStorePanelAccess($storeId);
        $repo = new ProductRepository();
        $product = $repo->findByIdAndStore($id, $storeId);
        if (!$product) {
            $this->json(['error' => 'Produto não encontrado'], 404);
        }
        $imageRepo = new ProductImageRepository();
        $sortStart = count($imageRepo->getByProductId($id));
        $saved = 0;

        $json = $this->getJsonInput();
        if (!empty($json['images']) && is_array($json['images'])) {
            foreach ($json['images'] as $i => $dataUrl) {
                if (!is_string($dataUrl)) continue;
                $path = save_product_image_from_base64($dataUrl);
                if ($path) {
                    $imageRepo->add($id, $path, $sortStart + $i);
                    $saved++;
                }
            }
        }

        if ($saved === 0) {
            $allFiles = [];
            foreach ($_FILES as $filesArr) {
                if (!is_array($filesArr) || empty($filesArr['tmp_name'])) continue;
                $normalized = $this->normalizeFiles($filesArr);
                foreach ($normalized as $f) {
                    $allFiles[] = $f;
                }
            }
            foreach ($allFiles as $i => $file) {
                $path = upload_product_image($file);
                if ($path) {
                    $imageRepo->add($id, $path, $sortStart + $i);
                    $saved++;
                }
            }
        }

        if ($saved === 0) {
            $received = !empty($json['images']) && is_array($json['images']) ? count($json['images']) : 0;
            $msg = 'Nenhuma imagem foi salva.';
            if ($received > 0) {
                $msg .= ' Verifique se a pasta frontend/public/uploads/products existe e tem permissão de escrita.';
            } else {
                $msg = 'Envie imagens em JSON: { "images": ["data:image/...;base64,..."] }';
            }
            $this->json(['error' => $msg, 'received' => $received], 400);
        }
        $service = new ProductService(new ProductRepository(), new StockMovementRepository(), new ProductImageRepository());
        $product = $service->getByIdAndStore($id, $storeId);
        $this->json(['success' => true, 'product' => $product, 'saved_images' => $saved]);
    }

    /** Remove uma foto do produto (POST JSON { "image_id": 1 }). */
    public function deleteImage(string $slug, int $productId): void
    {
        $storeId = $this->getStoreIdFromSlug($slug);
        if (!$storeId) {
            $this->json(['error' => 'Loja não encontrada'], 404);
        }
        $this->requireStorePanelAccess($storeId);
        $repo = new ProductRepository();
        $product = $repo->findByIdAndStore($productId, $storeId);
        if (!$product) {
            $this->json(['error' => 'Produto não encontrado'], 404);
        }
        $input = $this->getJsonInput();
        $imageId = (int) ($input['image_id'] ?? 0);
        if ($imageId <= 0) {
            $this->json(['error' => 'Informe image_id'], 400);
        }
        $imageRepo = new ProductImageRepository();
        $img = $imageRepo->find($imageId);
        $productIdInt = (int) $productId;
        if (!$img || (int) $img['product_id'] !== $productIdInt) {
            $this->json(['error' => 'Imagem não encontrada'], 404);
        }
        $baseDir = PLATAFORM_ROOT . DIRECTORY_SEPARATOR . 'frontend' . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . 'uploads';
        $path = $baseDir . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $img['file_path']);
        if (is_file($path)) {
            @unlink($path);
        }
        if (!$imageRepo->delete($imageId)) {
            $this->json(['error' => 'Nenhuma linha excluída no banco'], 404);
        }
        $this->json(['success' => true, 'deleted' => $imageId]);
    }

    /**
     * Remove foto só com slug na URL (evita conflito de rotas).
     * POST JSON: { "product_id": 1, "image_id": 2 }
     */
    public function deleteProductImageByBody(string $slug): void
    {
        $storeId = $this->getStoreIdFromSlug($slug);
        if (!$storeId) {
            $this->json(['error' => 'Loja não encontrada'], 404);
        }
        $this->requireStorePanelAccess($storeId);
        $input = $this->getJsonInput();
        $productId = (int) ($input['product_id'] ?? 0);
        $imageId = (int) ($input['image_id'] ?? 0);
        if ($productId <= 0 || $imageId <= 0) {
            $this->json(['error' => 'Envie product_id e image_id no JSON'], 400);
        }
        $repo = new ProductRepository();
        $product = $repo->findByIdAndStore($productId, $storeId);
        if (!$product) {
            $this->json(['error' => 'Produto não encontrado'], 404);
        }
        $imageRepo = new ProductImageRepository();
        $img = $imageRepo->find($imageId);
        if (!$img || (int) $img['product_id'] !== $productId) {
            $this->json(['error' => 'Imagem não encontrada neste produto'], 404);
        }
        $baseDir = PLATAFORM_ROOT . DIRECTORY_SEPARATOR . 'frontend' . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . 'uploads';
        $path = $baseDir . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $img['file_path']);
        if (is_file($path)) {
            @unlink($path);
        }
        $ok = $imageRepo->delete($imageId);
        if (!$ok) {
            $this->json(['error' => 'Nenhuma linha excluída. Verifique image_id e product_id.'], 404);
        }
        $this->json(['success' => true, 'deleted' => $imageId]);
    }

    /** Lê dados do produto: JSON ou multipart (name, description, cost_price, sale_price, stock_quantity, min_stock, images). */
    private function getProductInput(): array
    {
        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
        $hasMultipart = strpos($contentType, 'multipart/form-data') !== false;
        $hasPostName = !empty(trim($_POST['name'] ?? ''));
        $input = [
            'name' => trim($_POST['name'] ?? ''),
            'description' => trim($_POST['description'] ?? ''),
            'cost_price' => (float) ($_POST['cost_price'] ?? 0),
            'sale_price' => (float) ($_POST['sale_price'] ?? 0),
            'stock_quantity' => (int) ($_POST['stock_quantity'] ?? 0),
            'min_stock' => (int) ($_POST['min_stock'] ?? 0),
        ];
        $input['image_paths'] = [];
        if ($hasPostName && ($hasMultipart || !empty($_FILES))) {
            foreach ($_FILES as $key => $filesArr) {
                if (!is_array($filesArr) || empty($filesArr['tmp_name'])) {
                    continue;
                }
                $files = $this->normalizeFiles($filesArr);
                foreach ($files as $file) {
                    $path = upload_product_image($file);
                    if ($path) {
                        $input['image_paths'][] = $path;
                    }
                }
            }
            return $input;
        }
        $json = $this->getJsonInput();
        if (!empty($json)) {
            $input = array_merge($input, $json);
        }
        $input['image_paths'] = $input['image_paths'] ?? [];
        return $input;
    }

    /** Normaliza $_FILES['images'] (pode ser um arquivo ou array de arquivos) para lista de arrays. */
    private function normalizeFiles(array $files): array
    {
        if (empty($files['tmp_name'])) {
            return [];
        }
        $tmp = $files['tmp_name'];
        $isMulti = is_array($tmp);
        if (!$isMulti) {
            return (isset($files['error']) && $files['error'] === UPLOAD_ERR_OK && $tmp) ? [$files] : [];
        }
        $out = [];
        foreach ($tmp as $i => $t) {
            $err = isset($files['error'][$i]) ? $files['error'][$i] : UPLOAD_ERR_NO_FILE;
            if ($err === UPLOAD_ERR_OK && $t) {
                $out[] = [
                    'name' => $files['name'][$i] ?? '',
                    'type' => $files['type'][$i] ?? '',
                    'tmp_name' => $t,
                    'error' => $err,
                    'size' => $files['size'][$i] ?? 0,
                ];
            }
        }
        return $out;
    }

    /** Exclui produto recebendo product_id no body (POST /api/loja/{slug}/products/delete). */
    public function deleteByBody(string $slug): void
    {
        $storeId = $this->getStoreIdFromSlug($slug);
        if (!$storeId) {
            $this->json(['error' => 'Loja não encontrada'], 404);
            return;
        }
        $this->requireStorePanelAccess($storeId);
        $input = $this->getJsonInput();
        $id = (int) ($input['product_id'] ?? $input['id'] ?? 0);
        if ($id <= 0) {
            $this->json(['error' => 'Informe o ID do produto (product_id).'], 400);
            return;
        }
        $repo = new ProductRepository();
        $product = $repo->findByIdAndStore($id, $storeId);
        if (!$product) {
            $this->json(['error' => 'Produto não encontrado'], 404);
            return;
        }
        try {
            $this->deleteProductImageFiles($id);
            $orderItemRepo = new OrderItemRepository();
            $orderIds = $orderItemRepo->getOrderIdsByProductId($id);
            $orderItemRepo->deleteByProductId($id);
            $orderRepo = new OrderRepository();
            foreach ($orderIds as $orderId) {
                $orderRepo->recalcTotal($orderId);
            }
            $ok = $repo->delete($id);
            if (!$ok) {
                $this->json(['error' => 'Não foi possível excluir o produto.'], 400);
                return;
            }
            $this->json(['success' => true]);
        } catch (\Throwable $e) {
            $this->json(['error' => 'Não foi possível excluir: ' . $e->getMessage()], 400);
        }
    }

    private function deleteProductImageFiles(int $productId): void
    {
        $imageRepo = new ProductImageRepository();
        $images = $imageRepo->getByProductId($productId);
        $baseDir = PLATAFORM_ROOT . '/frontend/public/uploads';
        foreach ($images as $img) {
            $path = $baseDir . '/' . str_replace('/', DIRECTORY_SEPARATOR, $img['file_path']);
            if (is_file($path)) {
                @unlink($path);
            }
        }
        $imageRepo->deleteByProductId($productId);
    }
}
