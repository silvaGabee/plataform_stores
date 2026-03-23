<?php

namespace App\Controllers\Api;

use App\Controllers\Controller;
use App\Services\ReportService;

class ReportApiController extends Controller
{
    public function salesByPeriod(string $slug): void
    {
        $storeId = $this->getStoreIdFromSlug($slug);
        if (!$storeId) {
            $this->json(['error' => 'Loja não encontrada'], 404);
        }
        $this->requireStorePanelAccess($storeId);
        [$from, $to] = $this->parseReportDateRange($_GET['from'] ?? null, $_GET['to'] ?? null);
        $service = new ReportService();
        $data = $service->salesByPeriod($storeId, $from, $to);
        $this->json(['data' => $data]);
    }

    public function topProducts(string $slug): void
    {
        $storeId = $this->getStoreIdFromSlug($slug);
        if (!$storeId) {
            $this->json(['error' => 'Loja não encontrada'], 404);
        }
        $this->requireStorePanelAccess($storeId);
        [$from, $to] = $this->parseReportDateRange($_GET['from'] ?? null, $_GET['to'] ?? null);
        $limit = (int) ($_GET['limit'] ?? 10);
        $service = new ReportService();
        $data = $service->topProducts($storeId, $from, $to, $limit);
        $this->json(['data' => $data]);
    }

    public function lowStock(string $slug): void
    {
        $storeId = $this->getStoreIdFromSlug($slug);
        if (!$storeId) {
            $this->json(['error' => 'Loja não encontrada'], 404);
        }
        $this->requireStorePanelAccess($storeId);
        $service = new ReportService();
        $data = $service->lowStockProducts($storeId);
        $this->json(['data' => $data]);
    }

    public function employeePerformance(string $slug): void
    {
        $storeId = $this->getStoreIdFromSlug($slug);
        if (!$storeId) {
            $this->json(['error' => 'Loja não encontrada'], 404);
        }
        $this->requireStorePanelAccess($storeId);
        [$from, $to] = $this->parseReportDateRange($_GET['from'] ?? null, $_GET['to'] ?? null);
        $service = new ReportService();
        $data = $service->employeePerformance($storeId, $from, $to);
        $this->json(['data' => $data]);
    }

    public function revenue(string $slug): void
    {
        $storeId = $this->getStoreIdFromSlug($slug);
        if (!$storeId) {
            $this->json(['error' => 'Loja não encontrada'], 404);
        }
        $this->requireStorePanelAccess($storeId);
        [$from, $to] = $this->parseReportDateRange($_GET['from'] ?? null, $_GET['to'] ?? null);
        $service = new ReportService();
        $data = $service->storeRevenueByType($storeId, $from, $to);
        $this->json([
            'revenue' => $data['total'],
            'revenue_fisico' => $data['revenue_fisico'],
            'revenue_online' => $data['revenue_online'],
        ]);
    }

    public function customers(string $slug): void
    {
        $storeId = $this->getStoreIdFromSlug($slug);
        if (!$storeId) {
            $this->json(['error' => 'Loja não encontrada'], 404);
        }
        $this->requireStorePanelAccess($storeId);
        $service = new ReportService();
        $data = $service->customersWithStats($storeId);
        $this->json(['customers' => $data]);
    }
}
