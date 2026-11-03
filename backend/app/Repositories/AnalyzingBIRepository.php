<?php

namespace App\Repositories;

use App\Database\Database;
use PDO;

/**
 * Consultas agregadas para o BI da loja. Sempre filtrar por store_id.
 */
class AnalyzingBIRepository
{
    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? Database::getConnection();
    }

    /** Pedidos contados como venda: status pago (alinhado aos relatórios do sistema). */
    public function sumPaidOrdersTotal(int $storeId, ?string $fromDatetime, ?string $toDatetime): float
    {
        $sql = 'SELECT COALESCE(SUM(total), 0) FROM orders WHERE store_id = ? AND status = ?';
        $params = [$storeId, 'pago'];
        if ($fromDatetime !== null) {
            $sql .= ' AND created_at >= ?';
            $params[] = $fromDatetime;
        }
        if ($toDatetime !== null) {
            $sql .= ' AND created_at <= ?';
            $params[] = $toDatetime;
        }
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return (float) $stmt->fetchColumn();
    }

    public function countPaidOrders(int $storeId, ?string $fromDatetime, ?string $toDatetime): int
    {
        $sql = 'SELECT COUNT(*) FROM orders WHERE store_id = ? AND status = ?';
        $params = [$storeId, 'pago'];
        if ($fromDatetime !== null) {
            $sql .= ' AND created_at >= ?';
            $params[] = $fromDatetime;
        }
        if ($toDatetime !== null) {
            $sql .= ' AND created_at <= ?';
            $params[] = $toDatetime;
        }
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return (int) $stmt->fetchColumn();
    }

    /**
     * Custo do item: o congelado na venda (order_items.cost_price, desde a
     * 0019). Só cai para o custo atual do cadastro se o item for anterior à
     * coluna e o produto não existia no backfill — caso que hoje não ocorre,
     * mas a consulta não deve depender disso.
     */
    private const CUSTO_ITEM = 'COALESCE(oi.cost_price, p.cost_price, 0)';

    /**
     * Parte do desconto do pedido que cabe a este item, proporcional à sua
     * receita bruta no pedido:
     *
     *     desconto_item = desconto_pedido × (q × pv) / subtotal_pedido
     *
     * A soma das partes devolve o desconto inteiro. O CASE só executa a
     * subconsulta para pedidos com desconto — a imensa maioria não tem.
     */
    private const DESCONTO_ITEM = 'CASE WHEN o.discount > 0
            THEN o.discount * (oi.quantity * oi.price)
                 / NULLIF((SELECT SUM(x.quantity * x.price) FROM order_items x WHERE x.order_id = o.id), 0)
            ELSE 0 END';

    /**
     * Lucro estimado: Σ (pv − pc) × q − desconto rateado.
     *
     * pv é o preço gravado no item, pc o custo gravado no item. Antes o custo
     * vinha do cadastro atual e o desconto do PDV era ignorado — o primeiro
     * reescrevia o passado, o segundo inflava o lucro de toda venda com desconto.
     */
    public function sumEstimatedProfit(int $storeId, ?string $fromDatetime, ?string $toDatetime): float
    {
        $sql = 'SELECT COALESCE(SUM(oi.quantity * (oi.price - ' . self::CUSTO_ITEM . ') - ' . self::DESCONTO_ITEM . '), 0)
            FROM order_items oi
            INNER JOIN orders o ON o.id = oi.order_id AND o.store_id = ? AND o.status = ?
            LEFT JOIN products p ON p.id = oi.product_id
            WHERE 1=1';
        $params = [$storeId, 'pago'];
        if ($fromDatetime !== null) {
            $sql .= ' AND o.created_at >= ?';
            $params[] = $fromDatetime;
        }
        if ($toDatetime !== null) {
            $sql .= ' AND o.created_at <= ?';
            $params[] = $toDatetime;
        }
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return (float) $stmt->fetchColumn();
    }

    /**
     * Lucro por produto no período: Σ (pv − pc) × q − desconto rateado, com pc congelado no item.
     *
     * @return list<array{
     *     product_id: int,
     *     product_name: string,
     *     quantidade_vendida: float,
     *     custo_unitario: float,
     *     preco_venda_medio: float,
     *     lucro_total: float
     * }>
     */
    public function fetchProductProfitByPeriod(int $storeId, ?string $fromDatetime, ?string $toDatetime): array
    {
        // Custo e preço médios são PONDERADOS pela quantidade: com o custo
        // congelado, o mesmo produto pode ter sido vendido a custos diferentes
        // ao longo do período. O preço médio já é líquido do desconto rateado,
        // para que margem = preço médio − custo médio feche com o lucro total.
        $sql = 'SELECT oi.product_id,
            MAX(p.name) AS product_name,
            SUM(oi.quantity) AS quantidade_vendida,
            CASE WHEN SUM(oi.quantity) > 0
                THEN SUM(oi.quantity * ' . self::CUSTO_ITEM . ') / SUM(oi.quantity)
                ELSE 0 END AS custo_unitario,
            CASE WHEN SUM(oi.quantity) > 0
                THEN (SUM(oi.quantity * oi.price) - SUM(' . self::DESCONTO_ITEM . ')) / SUM(oi.quantity)
                ELSE 0 END AS preco_venda_medio,
            SUM(oi.quantity * (oi.price - ' . self::CUSTO_ITEM . ') - ' . self::DESCONTO_ITEM . ') AS lucro_total
            FROM order_items oi
            INNER JOIN orders o ON o.id = oi.order_id AND o.store_id = ? AND o.status = ?
            INNER JOIN products p ON p.id = oi.product_id AND p.store_id = o.store_id
            WHERE 1=1';
        $params = [$storeId, 'pago'];
        if ($fromDatetime !== null) {
            $sql .= ' AND o.created_at >= ?';
            $params[] = $fromDatetime;
        }
        if ($toDatetime !== null) {
            $sql .= ' AND o.created_at <= ?';
            $params[] = $toDatetime;
        }
        $sql .= ' GROUP BY oi.product_id
            HAVING SUM(oi.quantity) > 0
            ORDER BY lucro_total DESC, product_name ASC';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Vendas por produto no mês atual vs mês anterior (quantidades).
     *
     * @return list<array{product_id: int, product_name: string, qty_curr: float, qty_prev: float}>
     */
    public function fetchProductSalesCurrentVsPrevious(
        int $storeId,
        string $currStart,
        string $currEnd,
        string $prevStart,
        string $prevEnd
    ): array {
        $sql = 'SELECT oi.product_id,
            MAX(p.name) AS product_name,
            SUM(CASE WHEN o.created_at >= ? AND o.created_at <= ? THEN oi.quantity ELSE 0 END) AS qty_curr,
            SUM(CASE WHEN o.created_at >= ? AND o.created_at <= ? THEN oi.quantity ELSE 0 END) AS qty_prev
            FROM order_items oi
            INNER JOIN orders o ON o.id = oi.order_id AND o.store_id = ? AND o.status = ?
            INNER JOIN products p ON p.id = oi.product_id AND p.store_id = o.store_id
            WHERE o.created_at >= ? AND o.created_at <= ?
            GROUP BY oi.product_id';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            $currStart,
            $currEnd,
            $prevStart,
            $prevEnd,
            $storeId,
            'pago',
            $prevStart,
            $currEnd,
        ]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Quantidades vendidas por produto e mês (YYYY-MM) no intervalo.
     *
     * @return list<array{product_id: int, product_name: string, ym: string, qty: float}>
     */
    public function fetchProductMonthlyQuantities(int $storeId, string $periodStart, string $periodEnd): array
    {
        $sql = 'SELECT oi.product_id,
            MAX(p.name) AS product_name,
            DATE_FORMAT(o.created_at, \'%Y-%m\') AS ym,
            SUM(oi.quantity) AS qty
            FROM order_items oi
            INNER JOIN orders o ON o.id = oi.order_id AND o.store_id = ? AND o.status = ?
            INNER JOIN products p ON p.id = oi.product_id AND p.store_id = o.store_id
            WHERE o.created_at >= ? AND o.created_at <= ?
            GROUP BY oi.product_id, ym';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$storeId, 'pago', $periodStart, $periodEnd]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * @return list<array{id: int, name: string, stock_quantity: int|float, min_stock: int|float}>
     */
    public function fetchCriticalStock(int $storeId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, name, stock_quantity, min_stock FROM products
             WHERE store_id = ? AND min_stock > 0 AND stock_quantity <= min_stock
             ORDER BY stock_quantity ASC, name ASC'
        );
        $stmt->execute([$storeId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Estoque atual por produto (loja).
     *
     * @return array<int, float|int>
     */
    public function fetchStockQuantitiesByProduct(int $storeId): array
    {
        $stmt = $this->pdo->prepare('SELECT id, stock_quantity FROM products WHERE store_id = ?');
        $stmt->execute([$storeId]);
        $map = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $map[(int) $row['id']] = (float) $row['stock_quantity'];
        }

        return $map;
    }

    /**
     * Faturamento agregado por dia ou mês (só pedidos pagos / enviados da loja).
     *
     * @param 'day'|'month' $granularity
     *
     * @return list<array{data: string, valor: float}>
     */
    public function fetchRevenueByPeriod(int $storeId, string $fromDatetime, string $toDatetime, string $granularity): array
    {
        if ($granularity === 'month') {
            $sql = 'SELECT DATE_FORMAT(o.created_at, \'%Y-%m-01\') AS `data`,
                COALESCE(SUM(o.total), 0) AS valor
                FROM orders o
                WHERE o.store_id = ? AND o.status IN (\'pago\', \'enviado\')
                AND o.created_at >= ? AND o.created_at <= ?
                GROUP BY DATE_FORMAT(o.created_at, \'%Y-%m\')
                ORDER BY `data` ASC';
        } else {
            $sql = 'SELECT DATE(o.created_at) AS `data`,
                COALESCE(SUM(o.total), 0) AS valor
                FROM orders o
                WHERE o.store_id = ? AND o.status IN (\'pago\', \'enviado\')
                AND o.created_at >= ? AND o.created_at <= ?
                GROUP BY DATE(o.created_at)
                ORDER BY `data` ASC';
        }
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$storeId, $fromDatetime, $toDatetime]);

        $out = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $out[] = [
                'data' => (string) $row['data'],
                'valor' => (float) $row['valor'],
            ];
        }

        return $out;
    }
}
