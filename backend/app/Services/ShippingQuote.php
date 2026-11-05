<?php

namespace App\Services;

/**
 * Frete por faixa de CEP.
 *
 * O gerente cadastra faixas (ex.: 30000000–31999999 "Belo Horizonte", R$ 9,90,
 * 2 dias). O CEP do cliente cai na faixa mais estreita que o contém — assim uma
 * faixa de bairro vence a da cidade inteira. Empate: a mais barata.
 *
 * Loja sem nenhuma faixa entrega sem custo (comportamento de antes do frete).
 * Loja com faixas e CEP fora de todas: não entrega nesse CEP.
 */
final class ShippingQuote
{
    /** CEP só com dígitos (8), ou null se não tiver 8 dígitos. */
    public static function normalizeCep(?string $cep): ?string
    {
        $d = preg_replace('/\D/', '', (string) $cep) ?? '';

        return strlen($d) === 8 ? $d : null;
    }

    public static function formatCep(string $cep): string
    {
        $d = self::normalizeCep($cep);

        return $d === null ? $cep : substr($d, 0, 5) . '-' . substr($d, 5);
    }

    /**
     * @param list<array<string, mixed>> $rules  linhas de store_shipping_rules
     * @return array{disponivel: bool, valor: float, prazo: ?int, gratis: bool, regra: ?string, sem_regras: bool, cep: ?string}
     */
    public static function quote(array $rules, ?float $freeMin, ?string $cep, float $subtotal): array
    {
        $cep = self::normalizeCep($cep);
        $base = ['disponivel' => false, 'valor' => 0.0, 'prazo' => null, 'gratis' => false, 'regra' => null, 'sem_regras' => false, 'cep' => $cep];
        if ($rules === []) {
            return ['disponivel' => true, 'sem_regras' => true] + $base;
        }
        if ($cep === null) {
            return $base;
        }
        $melhor = null;
        foreach ($rules as $r) {
            $ini = (string) ($r['cep_start'] ?? '');
            $fim = (string) ($r['cep_end'] ?? '');
            // CHAR(8) com zeros à esquerda: comparar como texto preserva a ordem.
            if (strcmp($cep, $ini) < 0 || strcmp($cep, $fim) > 0) {
                continue;
            }
            $largura = (int) $fim - (int) $ini;
            $preco = (float) ($r['price'] ?? 0);
            if ($melhor === null || $largura < $melhor['largura'] || ($largura === $melhor['largura'] && $preco < $melhor['preco'])) {
                $melhor = ['largura' => $largura, 'preco' => $preco, 'regra' => $r];
            }
        }
        if ($melhor === null) {
            return $base;
        }
        $gratis = $freeMin !== null && $freeMin > 0 && round($subtotal, 2) >= round($freeMin, 2);
        $dias = $melhor['regra']['days'] ?? null;

        return [
            'disponivel' => true,
            'valor' => $gratis ? 0.0 : round($melhor['preco'], 2),
            'prazo' => $dias !== null && $dias !== '' ? (int) $dias : null,
            'gratis' => $gratis || $melhor['preco'] <= 0,
            'regra' => (string) ($melhor['regra']['name'] ?? ''),
            'sem_regras' => false,
            'cep' => $cep,
        ];
    }
}
