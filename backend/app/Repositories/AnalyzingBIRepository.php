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

    /**
     * Receita de mercadorias dos pedidos pagos, líquida de desconto.
     *
     * O frete fica de fora: orders.total o inclui, mas ele não é venda de
     * mercadoria, não tem CMV e não entra no lucro. Com ele, L = R − CMV não
     * fecharia em pedido com entrega, e faturamento e ticket médio subiriam.
     */
    public function sumPaidOrdersTotal(int $storeId, ?string $fromDatetime, ?string $toDatetime): float
    {
        $sql = 'SELECT COALESCE(SUM(total - shipping_fee), 0) FROM orders WHERE store_id = ? AND status = ?';
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

    /**
     * Desconto à vista no PIX concedido x juros recebidos no parcelamento,
     * em pedidos pagos do período.
     *
     * @return array{desconto_pix: float, pedidos_pix: int, juros_parcelamento: float, pedidos_parcelados: int}
     */
    public function pixVsInstallments(int $storeId, string $fromDatetime, string $toDatetime): array
    {
        $st = $this->pdo->prepare(
            "SELECT COALESCE(SUM(pix_discount), 0) AS desconto, COALESCE(SUM(pix_discount > 0), 0) AS pedidos
             FROM orders WHERE store_id = ? AND status = 'pago' AND created_at >= ? AND created_at <= ?"
        );
        $st->execute([$storeId, $fromDatetime, $toDatetime]);
        $pix = $st->fetch() ?: [];

        $st = $this->pdo->prepare(
            "SELECT COALESCE(SUM(p.interest_amount), 0) AS juros, COALESCE(SUM(p.installments > 1), 0) AS pedidos
             FROM payments p INNER JOIN orders o ON o.id = p.order_id
             WHERE p.store_id = ? AND p.status = 'confirmado' AND o.status = 'pago' AND o.created_at >= ? AND o.created_at <= ?"
        );
        $st->execute([$storeId, $fromDatetime, $toDatetime]);
        $cartao = $st->fetch() ?: [];

        return [
            'desconto_pix' => round((float) ($pix['desconto'] ?? 0), 2),
            'pedidos_pix' => (int) ($pix['pedidos'] ?? 0),
            'juros_parcelamento' => round((float) ($cartao['juros'] ?? 0), 2),
            'pedidos_parcelados' => (int) ($cartao['pedidos'] ?? 0),
        ];
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
     * Variações zeradas dos produtos com estoque mínimo definido.
     *
     * O total do produto esconde a falta de um tamanho: com P e G sobrando, o M
     * pode acabar sem o total chegar ao mínimo. Só entram produtos com mínimo
     * definido porque a matriz cor × tamanho grava todas as combinações, inclusive
     * as que a loja nunca teve; sem esse filtro, todo produto com grade incompleta
     * ficaria em alerta para sempre.
     *
     * @return list<array{id: int|string, name: string, stock_quantity: int|string, min_stock: int|string, variant_type: string, variant_value: string}>
     */
    public function fetchZeroedVariants(int $storeId): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT p.id, p.name, p.stock_quantity, p.min_stock, v.variant_type, v.variant_value
             FROM product_variants v
             INNER JOIN products p ON p.id = v.product_id
             WHERE p.store_id = ? AND p.min_stock > 0 AND v.variant_type <> '_meta' AND v.stock_quantity <= 0
             ORDER BY p.name ASC, v.sort_order ASC, v.id ASC"
        );
        $stmt->execute([$storeId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Produtos com estoque e sem venda paga desde $cutoff, incluindo os que nunca
     * venderam. Quem foi cadastrado depois de $cutoff ainda não teve tempo de
     * vender e fica de fora.
     *
     * Primeiro exclui quem vendeu desde $cutoff, olhando só os pedidos recentes.
     * Assim a última venda é buscada apenas para os candidatos, e o custo da
     * consulta não cresce com o histórico inteiro da loja.
     *
     * @return list<array{id: int|string, name: string, stock_quantity: int|string, cost_price: float|string|null, created_at: string, ultima_venda: ?string}>
     */
    public function fetchProductsWithoutSalesSince(int $storeId, string $cutoff): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT p.id, p.name, p.stock_quantity, p.cost_price, p.created_at,
                (SELECT MAX(o.created_at) FROM order_items oi
                 INNER JOIN orders o ON o.id = oi.order_id
                 WHERE oi.product_id = p.id AND o.store_id = p.store_id AND o.status = 'pago') AS ultima_venda
             FROM products p
             WHERE p.store_id = ? AND p.stock_quantity > 0
               AND p.id NOT IN (
                 SELECT oi.product_id FROM orders o
                 INNER JOIN order_items oi ON oi.order_id = o.id
                 WHERE o.store_id = ? AND o.status = 'pago' AND o.created_at >= ?
               )
             HAVING ultima_venda IS NOT NULL OR p.created_at < ?"
        );
        $stmt->execute([$storeId, $storeId, $cutoff, $cutoff]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Todos os produtos da loja com estoque e vendas pagas desde $inicioAno.
     * Receita líquida do desconto rateado e sem frete, como no resto do BI.
     *
     * @return list<array<string, mixed>>
     */
    public function fetchProductIndicators(int $storeId, string $inicioAno, string $inicioJanela): array
    {
        $sql = 'SELECT p.id, p.name, p.stock_quantity, p.min_stock, p.cost_price, p.created_at,
                COALESCE(s.quantidade, 0) AS quantidade,
                COALESCE(s.receita, 0) AS receita,
                COALESCE(s.cmv, 0) AS cmv,
                COALESCE(s.quantidade_janela, 0) AS quantidade_janela,
                s.primeira_venda
            FROM products p
            LEFT JOIN (
                SELECT oi.product_id,
                    SUM(oi.quantity) AS quantidade,
                    SUM(oi.quantity * oi.price - ' . self::DESCONTO_ITEM . ') AS receita,
                    SUM(oi.quantity * ' . self::CUSTO_ITEM . ') AS cmv,
                    SUM(CASE WHEN o.created_at >= ? THEN oi.quantity ELSE 0 END) AS quantidade_janela,
                    MIN(o.created_at) AS primeira_venda
                FROM orders o
                INNER JOIN order_items oi ON oi.order_id = o.id
                LEFT JOIN products p ON p.id = oi.product_id
                WHERE o.store_id = ? AND o.status = \'pago\' AND o.created_at >= ?
                GROUP BY oi.product_id
            ) s ON s.product_id = p.id
            WHERE p.store_id = ?';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$inicioJanela, $storeId, $inicioAno, $storeId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Pedidos online pagos desde $inicio, agrupados pelo cupom. A linha com
     * coupon_code NULL reúne os pedidos sem cupom. O desconto do cupom é o total
     * menos a parte do PIX. O lucro segue a fórmula de sumEstimatedProfit().
     *
     * @return list<array<string, mixed>>
     */
    public function fetchCouponImpact(int $storeId, string $inicio): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT coupon_code, COUNT(*) AS pedidos, SUM(discount - pix_discount) AS desconto, SUM(total - shipping_fee) AS receita
             FROM orders
             WHERE store_id = ? AND status = 'pago' AND order_type = 'online' AND created_at >= ?
             GROUP BY coupon_code"
        );
        $stmt->execute([$storeId, $inicio]);
        $linhas = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $stmt = $this->pdo->prepare(
            'SELECT o.coupon_code, SUM(oi.quantity * (oi.price - ' . self::CUSTO_ITEM . ') - ' . self::DESCONTO_ITEM . ') AS lucro
             FROM orders o
             INNER JOIN order_items oi ON oi.order_id = o.id
             LEFT JOIN products p ON p.id = oi.product_id
             WHERE o.store_id = ? AND o.status = \'pago\' AND o.order_type = \'online\' AND o.created_at >= ?
             GROUP BY o.coupon_code'
        );
        $stmt->execute([$storeId, $inicio]);
        $lucros = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $l) {
            $lucros[(string) $l['coupon_code']] = (float) $l['lucro'];
        }
        foreach ($linhas as &$linha) {
            $linha['lucro'] = $lucros[(string) $linha['coupon_code']] ?? 0.0;
        }
        unset($linha);

        return $linhas;
    }

    /** Meta de faturamento da loja no mês (YYYY-MM), ou null se não foi definida. */
    public function fetchStoreGoal(int $storeId, string $period): ?float
    {
        $stmt = $this->pdo->prepare('SELECT goal_amount FROM store_goals WHERE store_id = ? AND period = ?');
        $stmt->execute([$storeId, $period]);
        $valor = $stmt->fetchColumn();

        return $valor === false ? null : (float) $valor;
    }

    /**
     * Faturamento agregado por dia ou mês (só pedidos pagos / enviados da loja),
     * sem o frete, como em sumPaidOrdersTotal().
     *
     * @param 'day'|'month' $granularity
     *
     * @return list<array{data: string, valor: float}>
     */
    public function fetchRevenueByPeriod(int $storeId, string $fromDatetime, string $toDatetime, string $granularity): array
    {
        if ($granularity === 'month') {
            $sql = 'SELECT DATE_FORMAT(o.created_at, \'%Y-%m-01\') AS `data`,
                COALESCE(SUM(o.total - o.shipping_fee), 0) AS valor
                FROM orders o
                WHERE o.store_id = ? AND o.status IN (\'pago\', \'enviado\')
                AND o.created_at >= ? AND o.created_at <= ?
                GROUP BY DATE_FORMAT(o.created_at, \'%Y-%m\')
                ORDER BY `data` ASC';
        } else {
            $sql = 'SELECT DATE(o.created_at) AS `data`,
                COALESCE(SUM(o.total - o.shipping_fee), 0) AS valor
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
