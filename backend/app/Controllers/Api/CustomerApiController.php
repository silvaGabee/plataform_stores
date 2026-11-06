<?php

namespace App\Controllers\Api;

use App\Controllers\Controller;
use App\Database\Database;
use App\Services\CustomerReport;
use DateTimeImmutable;
use DateTimeZone;

class CustomerApiController extends Controller
{
    /** GET /api/loja/{slug}/clientes — quem mais compra e quem sumiu. */
    public function list(string $slug): void
    {
        $storeId = $this->getStoreIdFromSlug($slug);
        if (!$storeId) {
            $this->json(['error' => 'Loja não encontrada'], 404);
            return;
        }
        $pdo = Database::getConnection();
        $st = $pdo->prepare(
            "SELECT u.id, u.name, u.email, COUNT(*) AS pedidos, SUM(o.total - o.shipping_fee) AS total, MAX(o.created_at) AS ultima_compra
             FROM orders o INNER JOIN users u ON u.id = o.customer_id
             WHERE o.store_id = ? AND o.status = 'pago' AND o.order_type = 'online'
             GROUP BY u.id, u.name, u.email"
        );
        $st->execute([$storeId]);

        $balcao = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE store_id = ? AND status = 'pago' AND order_type = 'pdv'");
        $balcao->execute([$storeId]);

        $tz = new DateTimeZone((string) (config('app.timezone') ?: 'America/Sao_Paulo'));
        $this->json([
            'clientes' => CustomerReport::montar($st->fetchAll(\PDO::FETCH_ASSOC), new DateTimeImmutable('now', $tz)),
            'dias_sumido' => CustomerReport::DIAS_SUMIDO,
            'vendas_balcao_sem_cliente' => (int) $balcao->fetchColumn(),
        ]);
    }
}
