<?php

namespace App\Services;

use App\Domain\Finance\Installments;
use App\Domain\Finance\Money;

/**
 * Parcelamento no cartão da loja online.
 *
 * Regra configurada pelo gerente (Configurações > Pagamentos):
 *   installments_max        número máximo de parcelas (1 a 12)
 *   installments_free       até quantas parcelas sem juros
 *   installments_rate       taxa de juros ao mês acima disso (% a.m.)
 *   installments_min_value  valor mínimo de cada parcela
 *
 * Até `free` parcelas o valor é dividido igualmente (sem juros). Acima, o
 * valor da parcela vem da tabela Price — o mesmo cálculo do simulador do PDV
 * (App\Domain\Finance\Installments), com juros compostos sobre o saldo.
 */
final class InstallmentPlan
{
    public const MAX = 12;

    /** @return array{max:int,free:int,rate:float,min_value:float} */
    public static function rules(array $store): array
    {
        $max = max(1, min(self::MAX, (int) ($store['installments_max'] ?? 6)));
        $free = max(1, min($max, (int) ($store['installments_free'] ?? 3)));

        return [
            'max' => $max,
            'free' => $free,
            'rate' => max(0.0, min(15.0, (float) ($store['installments_rate'] ?? 1.99))),
            'min_value' => max(0.0, (float) ($store['installments_min_value'] ?? 20)),
        ];
    }

    /**
     * Opções de 1x até o máximo permitido para o valor.
     *
     * @return list<array{parcelas:int,parcela:float,total:float,juros:float,sem_juros:bool}>
     */
    public static function options(array $store, float $amount): array
    {
        $amount = round($amount, 2);
        if ($amount <= 0) {
            return [];
        }
        $r = self::rules($store);
        $out = [];
        for ($n = 1; $n <= $r['max']; $n++) {
            $q = self::quote($store, $amount, $n);
            if ($n > 1 && $q['parcela'] < $r['min_value']) {
                break;
            }
            $out[] = $q;
        }

        return $out;
    }

    /** @return array{parcelas:int,parcela:float,total:float,juros:float,sem_juros:bool} */
    public static function quote(array $store, float $amount, int $n): array
    {
        $r = self::rules($store);
        if ($n < 1 || $n > $r['max']) {
            throw new \InvalidArgumentException('Número de parcelas indisponível para esta loja.');
        }
        $semJuros = $n <= $r['free'] || $r['rate'] <= 0;
        if ($semJuros) {
            $partes = Money::splitEvenly($amount, $n);

            return [
                'parcelas' => $n,
                'parcela' => (float) $partes[0],
                'total' => round($amount, 2),
                'juros' => 0.0,
                'sem_juros' => true,
                'descricao' => self::descrever($partes),
            ];
        }
        $p = Installments::price($amount, $r['rate'], $n);

        return [
            'parcelas' => $n,
            'parcela' => (float) $p['parcela'],
            'total' => (float) $p['total'],
            'juros' => round((float) $p['total'] - $amount, 2),
            'sem_juros' => false,
            'descricao' => self::descrever(array_column($p['tabela'], 'parcela')),
        ];
    }

    /**
     * Parcelas por extenso, com os valores exatos: "3x de R$ 30,00" quando são
     * iguais; "2x de R$ 100,00 + 1x de R$ 99,99" quando a divisão em centavos
     * não fecha. Antes só a primeira parcela (a maior) era mostrada, e R$ 299,99
     * aparecia como "3x de R$ 100,00", que somam R$ 300,00.
     *
     * @param list<float|int|string> $valores valor de cada parcela, na ordem
     */
    public static function descrever(array $valores): string
    {
        $grupos = [];
        foreach ($valores as $v) {
            $c = Money::toCents((float) $v);
            $ultimo = count($grupos) - 1;
            if ($ultimo >= 0 && $grupos[$ultimo][1] === $c) {
                $grupos[$ultimo][0]++;
            } else {
                $grupos[] = [1, $c];
            }
        }

        return implode(' + ', array_map(
            static fn (array $g): string => $g[0] . 'x de R$ ' . number_format($g[1] / 100, 2, ',', '.'),
            $grupos
        ));
    }

    /** Texto curto para a vitrine: "ou 3x de R$ 40,00 sem juros" (ou "ou 2x de R$ 39,97 + 1x de R$ 39,96 sem juros"). */
    public static function headline(array $store, float $amount): ?string
    {
        $best = null;
        foreach (self::options($store, $amount) as $o) {
            if ($o['parcelas'] > 1 && $o['sem_juros']) {
                $best = $o;
            }
        }
        if ($best === null) {
            return null;
        }

        return 'ou ' . $best['descricao'] . ' sem juros';
    }
}
