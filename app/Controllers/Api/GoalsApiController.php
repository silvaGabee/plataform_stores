<?php

namespace App\Controllers\Api;

use App\Controllers\Controller;
use App\Repositories\StoreGoalRepository;
use App\Repositories\EmployeeGoalRepository;
use App\Repositories\UserRepository;
use App\Services\ReportService;

class GoalsApiController extends Controller
{
    public function get(string $slug): void
    {
        $storeId = $this->getStoreIdFromSlug($slug);
        if (!$storeId) {
            $this->json(['error' => 'Loja não encontrada'], 404);
        }
        $this->requireStorePanelAccess($storeId);
        $period = $this->normalizePeriod($_GET['period'] ?? date('Y-m'));
        $storeGoalRepo = new StoreGoalRepository();
        $empGoalRepo = new EmployeeGoalRepository();
        $userRepo = new UserRepository();
        $reportService = new ReportService();

        $storeGoal = $storeGoalRepo->getByStoreAndPeriod($storeId, $period);
        $storeGoalAmount = $storeGoal ? (float) $storeGoal['goal_amount'] : 0.0;

        $employeeGoals = $empGoalRepo->getByStoreAndPeriod($storeId, $period);
        $dateFrom = $period . '-01';
        $dateTo = date('Y-m-t', strtotime($dateFrom));
        $performance = $reportService->employeePerformance($storeId, $dateFrom, $dateTo);
        $employees = $userRepo->listEmployeesByStore($storeId);
        $byId = [];
        foreach ($employees as $u) {
            $byId[(int) $u['id']] = [
                'user_id' => (int) $u['id'],
                'name' => $u['name'] ?? '',
                'goal_amount' => $employeeGoals[(int) $u['id']] ?? 0,
                'orders_count' => 0,
                'total_sales' => 0,
            ];
        }
        foreach ($performance as $p) {
            $id = (int) ($p['id'] ?? 0);
            if (isset($byId[$id])) {
                $byId[$id]['orders_count'] = (int) ($p['orders_count'] ?? 0);
                $byId[$id]['total_sales'] = (float) ($p['total_sales'] ?? 0);
            }
        }

        $this->json([
            'period' => $period,
            'store_goal' => $storeGoalAmount,
            'employees' => array_values($byId),
        ]);
    }

    public function setStoreGoal(string $slug): void
    {
        $storeId = $this->getStoreIdFromSlug($slug);
        if (!$storeId) {
            $this->json(['error' => 'Loja não encontrada'], 404);
        }
        $this->requireGerenteOfStore($storeId);
        $input = $this->getJsonInput();
        $period = $this->normalizePeriod($input['period'] ?? date('Y-m'));
        $goalAmount = isset($input['goal_amount']) ? (float) $input['goal_amount'] : 0;
        $distribute = !empty($input['distribute_to_employees']);

        $storeGoalRepo = new StoreGoalRepository();
        $storeGoalRepo->set($storeId, $period, $goalAmount);

        if ($distribute && $goalAmount > 0) {
            $userRepo = new UserRepository();
            $employees = $userRepo->listEmployeesByStore($storeId);
            $count = count($employees);
            if ($count > 0) {
                $perEmployee = round($goalAmount / $count, 2);
                $empGoalRepo = new EmployeeGoalRepository();
                $userGoals = [];
                foreach ($employees as $u) {
                    $userGoals[(int) $u['id']] = $perEmployee;
                }
                $empGoalRepo->setBulk($storeId, $period, $userGoals);
            }
        }

        $this->json(['success' => true]);
    }

    public function setEmployeeGoal(string $slug): void
    {
        $storeId = $this->getStoreIdFromSlug($slug);
        if (!$storeId) {
            $this->json(['error' => 'Loja não encontrada'], 404);
        }
        $this->requireGerenteOfStore($storeId);
        $input = $this->getJsonInput();
        $userId = isset($input['user_id']) ? (int) $input['user_id'] : 0;
        $period = $this->normalizePeriod($input['period'] ?? date('Y-m'));
        $goalAmount = isset($input['goal_amount']) ? (float) $input['goal_amount'] : 0;

        if ($userId < 1) {
            $this->json(['error' => 'user_id inválido'], 400);
        }
        $empGoalRepo = new EmployeeGoalRepository();
        $empGoalRepo->set($storeId, $userId, $period, $goalAmount);
        $this->json(['success' => true]);
    }

    private function normalizePeriod(string $period): string
    {
        if (preg_match('/^\d{4}-\d{2}$/', $period)) {
            return $period;
        }
        return date('Y-m');
    }
}
