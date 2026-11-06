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
    /** Meses completos usados para sugerir a parte da meta que cabe aos funcionários. */
    private const MESES_HISTORICO = 3;

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

        $funcionarios = (new StoreMemberRepository())->listMembers($storeId, Permissions::FUNCIONARIO);
        $idsFuncionarios = array_map(static fn (array $u): int => (int) $u['id'], $funcionarios);

        $linhas = [];
        foreach ($funcionarios as $u) {
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

            // De onde veio o vendido. A vitrine e as vendas de quem saiu da equipe
            // não têm funcionário, então são o que sobra: as três partes sempre
            // fecham com o total da loja.
            $vendidoEquipe = 0.0;
            $vendidoGerentes = 0.0;
            foreach ($vendas as $id => $p) {
                if (in_array($id, $idsFuncionarios, true)) {
                    $vendidoEquipe += (float) $p['total_sales'];
                } else {
                    $vendidoGerentes += (float) $p['total_sales'];
                }
            }
            $resposta['sales_by_source'] = [
                'employees' => round($vendidoEquipe, 2),
                'managers' => round($vendidoGerentes, 2),
                'others' => round($vendidoLoja - $vendidoEquipe - $vendidoGerentes, 2),
            ];
            $resposta['team_history'] = $this->participacaoDaEquipe($storeId, $from, $idsFuncionarios);
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

        // A meta da loja inclui a vitrine e as vendas do gerente, que não contam
        // para nenhum funcionário. Por isso o rateio é de um valor informado, e não
        // da meta inteira: senão a loja bateria 100% com a equipe abaixo da meta.
        $dividir = !empty($input['distribute_to_employees']) && $goalAmount > 0;
        $valorEquipe = round((float) ($input['distribute_amount'] ?? 0), 2);
        if ($dividir && ($valorEquipe <= 0 || $valorEquipe > $goalAmount)) {
            $this->json(['error' => 'Informe quanto dividir: mais que zero e até o valor da meta da loja.'], 400);
            return;
        }
        // O gerente define a meta, não a recebe.
        $funcionarios = $dividir
            ? (new StoreMemberRepository())->listMembers($storeId, Permissions::FUNCIONARIO)
            : [];

        Database::transaction(function () use ($storeId, $period, $goalAmount, $valorEquipe, $funcionarios): void {
            (new StoreGoalRepository())->set($storeId, $period, $goalAmount);
            if ($funcionarios === []) {
                return;
            }
            // Rateio em centavos: as partes somam exatamente o valor dividido.
            // Antes cada parte era round(meta / n, 2) e R$ 1.000 entre 3
            // fechava em R$ 999,99.
            $partes = Money::splitEvenly($valorEquipe, count($funcionarios));
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

    /**
     * Quanto os funcionários atuais venderam nos meses completos anteriores ao
     * período, em relação à loja. É a base da sugestão de quanto dividir; percent
     * nulo significa que a loja não vendeu nada nesses meses.
     *
     * @param list<int> $idsFuncionarios
     * @return array{months: int, percent: ?float}
     */
    private function participacaoDaEquipe(int $storeId, string $inicioDoPeriodo, array $idsFuncionarios): array
    {
        $reports = new ReportService();
        $base = (int) strtotime($inicioDoPeriodo);
        $de = date('Y-m-d', (int) strtotime('-' . self::MESES_HISTORICO . ' months', $base));
        $ate = date('Y-m-d', (int) strtotime('-1 day', $base));
        $equipe = 0.0;
        foreach ($reports->employeePerformance($storeId, $de, $ate) as $p) {
            if (in_array((int) $p['id'], $idsFuncionarios, true)) {
                $equipe += (float) $p['total_sales'];
            }
        }

        return [
            'months' => self::MESES_HISTORICO,
            // A mesma conta do percentual da meta: parte ÷ todo × 100.
            'percent' => AnalyzingBIService::goalPercent($equipe, $reports->storeRevenueByType($storeId, $de, $ate)['total']),
        ];
    }

    private function normalizePeriod(string $period): string
    {
        if (preg_match('/^(\d{4})-(\d{2})$/', $period, $m) && checkdate((int) $m[2], 1, (int) $m[1])) {
            return $period;
        }

        return date('Y-m');
    }
}
