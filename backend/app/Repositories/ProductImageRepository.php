<?php

namespace App\Repositories;

use App\Database\Database;
use PDO;

class ProductImageRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getConnection();
    }

    public function find(int $id): ?array
    {
        $stmt = $this->pdo->prepare("SELECT id, product_id, file_path, sort_order FROM product_images WHERE id = ?");
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function getByProductId(int $productId): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT id, product_id, file_path, sort_order FROM product_images WHERE product_id = ? ORDER BY sort_order ASC, id ASC"
        );
        $stmt->execute([$productId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Imagens de vários produtos numa consulta, agrupadas por product_id.
     *
     * A vitrine chamava getByProductId() dentro do laço de produtos: uma
     * consulta por produto, mais outra por variações e outra por categorias.
     * Com 50 produtos eram 151 idas ao banco para montar uma página.
     *
     * @param int[] $productIds
     * @return array<int, list<array<string, mixed>>>
     */
    public function getByProductIds(array $productIds): array
    {
        $ids = self::idsValidos($productIds);
        if ($ids === []) {
            return [];
        }
        $marcadores = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $this->pdo->prepare(
            "SELECT id, product_id, file_path, sort_order FROM product_images
              WHERE product_id IN ({$marcadores}) ORDER BY product_id ASC, sort_order ASC, id ASC"
        );
        $stmt->execute($ids);

        $porProduto = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $linha) {
            $porProduto[(int) $linha['product_id']][] = $linha;
        }

        return $porProduto;
    }

    /**
     * @param int[] $ids
     * @return list<int>
     */
    public static function idsValidos(array $ids): array
    {
        return array_values(array_unique(array_filter(
            array_map('intval', $ids),
            static fn (int $i): bool => $i > 0
        )));
    }

    public function add(int $productId, string $filePath, int $sortOrder = 0): int
    {
        $stmt = $this->pdo->prepare(
            "INSERT INTO product_images (product_id, file_path, sort_order) VALUES (?, ?, ?)"
        );
        $stmt->execute([$productId, $filePath, $sortOrder]);
        return (int) $this->pdo->lastInsertId();
    }

    public function delete(int $id): bool
    {
        $stmt = $this->pdo->prepare("DELETE FROM product_images WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->rowCount() > 0;
    }

    public function deleteByProductId(int $productId): void
    {
        $stmt = $this->pdo->prepare("DELETE FROM product_images WHERE product_id = ?");
        $stmt->execute([$productId]);
    }

    /** Define a foto de capa (sort_order 0); demais imagens seguem na ordem atual. */
    public function setCover(int $productId, int $imageId): bool
    {
        $images = $this->getByProductId($productId);
        $cover = null;
        $others = [];
        foreach ($images as $img) {
            if ((int) $img['id'] === $imageId) {
                $cover = $img;
            } else {
                $others[] = $img;
            }
        }
        if ($cover === null) {
            return false;
        }
        usort($others, static function (array $a, array $b): int {
            return ((int) $a['sort_order']) <=> ((int) $b['sort_order'])
                ?: ((int) $a['id']) <=> ((int) $b['id']);
        });
        $stmt = $this->pdo->prepare('UPDATE product_images SET sort_order = ? WHERE id = ? AND product_id = ?');
        $stmt->execute([0, $imageId, $productId]);
        $order = 1;
        foreach ($others as $img) {
            $stmt->execute([$order++, (int) $img['id'], $productId]);
        }

        return true;
    }
}
