<?php
/** @var array $store */
/** @var array<string|int, mixed> $cart */

use App\Repositories\ProductImageRepository;
use App\Repositories\ProductRepository;
use App\Repositories\StockMovementRepository;
use App\Services\ProductService;

$productService = new ProductService(
    new ProductRepository(),
    new StockMovementRepository(),
    new ProductImageRepository()
);

$cartItems = [];
$cartTotal = 0.0;
foreach (store_cart_normalize_lines($cart) as $line) {
    $p = $productService->getByIdAndStore((int) $line['product_id'], (int) $store['id']);
    if (!$p) {
        continue;
    }
    $qty = (int) $line['quantity'];
    $variantKey = $line['variant_key'];
    $cartItems[] = [
        'product' => $p,
        'quantity' => $qty,
        'variant_key' => $variantKey,
        'cart_key' => store_cart_line_storage_key((int) $p['id'], $variantKey),
        'line_stock' => product_sale_available_stock($p, $variantKey),
        'variant_label' => product_variant_key_label($p, $variantKey),
    ];
    $cartTotal += (float) $p['sale_price'] * $qty;
}
