<?php

namespace App\Services;

use App\Database\Database;
use PDO;

class ReportService
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getConnection();
    }

    public function salesByPeriod(int $storeId, string $dateFrom, string $dateTo): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT DATE(created_at) as date, COUNT(*) as total_orders, SUM(total) as revenue 
             FROM orders WHERE store_id = ? AND status = 'pago' AND created_at BETWEEN ? AND ? 
             GROUP BY DATE(created_at) ORDER BY date"
        );
        $stmt->execute([$storeId, $dateFrom . ' 00:00:00', $dateTo . ' 23:59:59']);
        return $stmt->fetchAll();
    }

    public function topProducts(int $storeId, string $dateFrom, string $dateTo, int $limit = 10): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT p.name, SUM(oi.quantity) as total_qty, SUM(oi.quantity * oi.price) as revenue 
             FROM order_items oi 
             JOIN orders o ON o.id = oi.order_id 
             JOIN products p ON p.id = oi.product_id 
             WHERE o.store_id = ? AND o.status = 'pago' AND o.created_at BETWEEN ? AND ? 
             GROUP BY oi.product_id ORDER BY total_qty DESC LIMIT ?"
        );
        $stmt->execute([$storeId, $dateFrom . ' 00:00:00', $dateTo . ' 23:59:59', $limit]);
        return $stmt->fetchAll();
    }

    public function lowStockProducts(int $storeId): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT * FROM products WHERE store_id = ? AND min_stock > 0 AND stock_quantity <= min_stock ORDER BY stock_quantity"
        );
        $stmt->execute([$storeId]);
        return $stmt->fetchAll();
    }

    public function employeePerformance(int $storeId, string $dateFrom, string $dateTo): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT u.id, u.name, COUNT(o.id) as orders_count, COALESCE(SUM(o.total), 0) as total_sales 
             FROM users u 
             LEFT JOIN orders o ON o.created_by = u.id AND o.store_id = u.store_id AND o.status = 'pago' 
               AND o.created_at BETWEEN ? AND ?
             WHERE u.store_id = ? AND u.user_type IN ('funcionario','gerente') 
             GROUP BY u.id, u.name ORDER BY total_sales DESC"
        );
        $stmt->execute([$dateFrom . ' 00:00:00', $dateTo . ' 23:59:59', $storeId]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function customersWithStats(int $storeId): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT u.id, u.name, u.email,
                    COUNT(DISTINCT o.id) AS orders_count,
                    COALESCE(SUM(oi.quantity), 0) AS products_count,
                    COALESCE(SUM(CASE WHEN o.status = 'pago' THEN o.total ELSE 0 END), 0) AS total_spent
             FROM users u
             LEFT JOIN orders o ON o.customer_id = u.id AND o.store_id = u.store_id
             LEFT JOIN order_items oi ON oi.order_id = o.id
             WHERE u.store_id = ? AND u.user_type = 'cliente'
             GROUP BY u.id, u.name, u.email
             ORDER BY total_spent DESC, orders_count DESC"
        );
        $stmt->execute([$storeId]);
        return $stmt->fetchAll();
    }

    public function storeRevenue(int $storeId, ?string $dateFrom = null, ?string $dateTo = null): float
    {
        $data = $this->storeRevenueByType($storeId, $dateFrom, $dateTo);
        return $data['total'];
    }

    /**
     * Retorna faturamento separado por tipo: físico (PDV) e online.
     * @return array{revenue_fisico: float, revenue_online: float, total: float}
     */
    public function storeRevenueByType(int $storeId, ?string $dateFrom = null, ?string $dateTo = null): array
    {
        $sql = "SELECT order_type, COALESCE(SUM(total), 0) as revenue 
                FROM orders 
                WHERE store_id = ? AND status = 'pago'";
        $params = [$storeId];
        if ($dateFrom) {
            $sql .= " AND created_at >= ?";
            $params[] = $dateFrom . ' 00:00:00';
        }
        if ($dateTo) {
            $sql .= " AND created_at <= ?";
            $params[] = $dateTo . ' 23:59:59';
        }
        $sql .= " GROUP BY order_type";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $revenueFisico = 0.0;
        $revenueOnline = 0.0;
        foreach ($rows as $row) {
            $type = strtolower((string) ($row['order_type'] ?? ''));
            $val = (float) ($row['revenue'] ?? 0);
            if ($type === 'pdv') {
                $revenueFisico = $val;
            } else {
                $revenueOnline += $val;
            }
        }
        return [
            'revenue_fisico' => $revenueFisico,
            'revenue_online' => $revenueOnline,
            'total' => $revenueFisico + $revenueOnline,
        ];
    }
}
