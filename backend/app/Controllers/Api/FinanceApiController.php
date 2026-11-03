<?php

namespace App\Controllers\Api;

use App\Controllers\Controller;
use App\Domain\Finance\Installments;

/**
 * Simulações de matemática financeira usadas pelo painel.
 *
 * Só calcula, não grava nada: o cálculo mora em App\Domain\Finance para ser
 * testado sem banco, e este controller apenas valida a entrada.
 */
class FinanceApiController extends Controller
{
    /**
     * GET /api/loja/{slug}/finance/installments?valor=&taxa=&parcelas=&max=
     *
     * Devolve o resumo de 1x a `max` parcelas e a tabela Price completa da
     * opção escolhida em `parcelas`.
     */
    public function installments(string $slug): void
    {
        $storeId = $this->getStoreIdFromSlug($slug);
        if (!$storeId) {
            $this->json(['error' => 'Loja não encontrada'], 404);
        }
        $this->requireStorePanelAccess($storeId);

        $valor = self::decimal($_GET['valor'] ?? '0');
        $taxa = self::decimal($_GET['taxa'] ?? '0');
        $parcelas = (int) ($_GET['parcelas'] ?? 1);
        $max = (int) ($_GET['max'] ?? 12);

        try {
            $this->json([
                'opcoes' => Installments::options($valor, $taxa, $max),
                'simulacao' => Installments::price($valor, $taxa, $parcelas),
                'metodo' => 'tabela_price',
            ]);
        } catch (\InvalidArgumentException $e) {
            $this->json(['error' => $e->getMessage()], 400);
        }
    }

    /** Aceita "2,5" e "2.5". */
    private static function decimal(string $v): float
    {
        $v = trim($v);
        if (str_contains($v, ',')) {
            $v = str_replace(['.', ','], ['', '.'], $v);
        }

        return is_numeric($v) ? (float) $v : 0.0;
    }
}
