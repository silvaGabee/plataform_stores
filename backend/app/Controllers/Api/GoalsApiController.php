<?php

namespace App\Controllers\Api;

use App\Auth\Permissions;
use App\Controllers\Controller;
use App\Database\Database;
use App\Domain\Finance\Money;
use App\Repositories\EmployeeGoalRepository;
use App\Repositories\StoreGoalRepository;
use App\Repositories\StoreMemberRepository;
use App\Services\AnalyzingBIService;
use App\Services\ReportService;

class GoalsApiController extends Controller
{
    /**
     * Metas e vendas de um mês.
     *
     * O gerente recebe a loja e a equipe. O funcionário recebe só a própria
     * linha: a rota é aberta aos dois, e o faturamento da loja e as vendas dos
     * colegas são dados do gerente.
     */
    public function get(string $slug): void
    {
        $storeId = $this->getStoreIdFromSlug($slug);
        if (!$storeId) {
            $this->json(['error' => 'Loja não encontrada'], 404);
            return;
        }
        $period = $this->normalizePeriod((string) ($_GET['period'] ?? date('Y-m')));
        // As vendas são as do mês da meta. Comparar a meta de um mês com outro
        // intervalo daria um percentual sem sentido.
        $from = $period . '-01';
        $to = date('Y-m-t', (int) strtotime($from));

        $reports = new ReportService();
        $vendas = [];
        foreach ($reports->employeePerformance($storeId, $from, $to) as $p) {
            $vendas[(int) $p['id']] = $p;
        }
        $metas = (new EmployeeGoalRepository())->getByStoreAndPeriod($storeId, $period);
        $podeGerenciar = Permissions::can('store.goals.write', $storeId);
        $eu = (int) ($_SESSION['logged_user_id'] ?? 0);

        $linhas = [];
        foreach ((new StoreMemberRepository())->listMembers($storeId, Permissions::FUNCIONARIO) as $u) {
            $id = (int) $u['id'];
            if (!$podeGerenciar && $id !== $eu) {
                continue;
            }
            $meta = $metas[$id] ?? 0.0;
            $vendido = (float) ($vendas[$id]['total_sales'] ?? 0);
            $linhas[] = [
                'user_id' => $id,
                'name' => (string) ($u['name'] ?? ''),
                'goal_amount' => $meta,
                'orders_count' => (int) ($vendas[$id]['orders_count'] ?? 0),
                'total_sales' => $vendido,
                'percent' => AnalyzingBIService::goalPercent($vendido, $meta),
            ];
        }

        $resposta = [
            'period' => $period,
            'can_manage' => $podeGerenciar,
            'employees' => $linhas,
        ];
        if ($podeGerenciar) {
            $metaLoja = (new StoreGoalRepository())->getByStoreAndPeriod($storeId, $period);
            $valorMeta = $metaLoja ? (float) $metaLoja['goal_amount'] : 0.0;
            $vendidoLoja = $reports->storeRevenueByType($storeId, $from, $to)['total'];
            $resposta['store_goal'] = $valorMeta;
            $resposta['store_sales'] = $vendidoLoja;
            $resposta['store_percent'] = AnalyzingBIService::goalPercent($vendidoLoja, $valorMeta);
        }
        $this->json($resposta);
    }

    public function setStoreGoal(string $slug): void
    {
        $storeId = $this->getStoreIdFromSlug($slug);
        if (!$storeId) {
            $this->json(['error' => 'Loja não encontrada'], 404);
            return;
        }
        $this->requireGerenteOfStore($storeId);
        $input = $this->getJsonInput();
        $period = $this->normalizePeriod((string) ($input['period'] ?? date('Y-m')));
        $goalAmount = round((float) ($input['goal_amount'] ?? 0), 2);
        if ($goalAmount < 0) {
            $this->json(['error' => 'A meta não pode ser negativa.'], 400);
            return;
        }

        // A meta é de vendas e o rateio é só entre funcionários: o gerente
        // define a meta, não a recebe.
        $funcionarios = !empty($input['distribute_to_employees']) && $goalAmount > 0
            ? (new StoreMemberRepository())->listMembers($storeId, Permissions::FUNCIONARIO)
            : [];

        Database::transaction(function () use ($storeId, $period, $goalAmount, $funcionarios): void {
            (new StoreGoalRepository())->set($storeId, $period, $goalAmount);
            if ($funcionarios === []) {
                return;
            }
            // Rateio em centavos: as partes somam exatamente a meta da loja.
            // Antes cada parte era round(meta / n, 2) e R$ 1.000 entre 3
            // fechava em R$ 999,99.
            $partes = Money::splitEvenly($goalAmount, count($funcionarios));
            $metas = [];
            foreach (array_values($funcionarios) as $i => $u) {
                $metas[(int) $u['id']] = $partes[$i];
            }
            (new EmployeeGoalRepository())->setBulk($storeId, $period, $metas);
        });

        $this->json(['success' => true, 'distributed_to' => count($funcionarios)]);
    }

    public function setEmployeeGoal(string $slug): void
    {
        $storeId = $this->getStoreIdFromSlug($slug);
        if (!$storeId) {
            $this->json(['error' => 'Loja não encontrada'], 404);
            return;
        }
        $this->requireGerenteOfStore($storeId);
        $input = $this->getJsonInput();
        $userId = (int) ($input['user_id'] ?? 0);
        $period = $this->normalizePeriod((string) ($input['period'] ?? date('Y-m')));
        $goalAmount = round((float) ($input['goal_amount'] ?? 0), 2);

        // Sem esta checagem, qualquer id de usuário da plataforma era aceito.
        if ((new StoreMemberRepository())->role($userId, $storeId) !== Permissions::FUNCIONARIO) {
            $this->json(['error' => 'Escolha um funcionário desta loja.'], 400);
            return;
        }
        if ($goalAmount < 0) {
            $this->json(['error' => 'A meta não pode ser negativa.'], 400);
            return;
        }
        (new EmployeeGoalRepository())->set($storeId, $userId, $period, $goalAmount);
        $this->json(['success' => true]);
    }

    private function normalizePeriod(string $period): string
    {
        if (preg_match('/^(\d{4})-(\d{2})$/', $period, $m) && checkdate((int) $m[2], 1, (int) $m[1])) {
            return $period;
        }

        return date('Y-m');
    }
}
