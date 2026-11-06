<?php

namespace App\Services;

use DateTimeImmutable;

/**
 * Clientes identificados, de quem mais compra para quem menos compra.
 *
 * Só entram compras da vitrine, que têm conta. A venda de balcão guarda o próprio
 * operador em customer_id e, no máximo, um nome digitado, então não identifica
 * ninguém: contá-la daria ao funcionário o título de melhor cliente da loja.
 */
final class CustomerReport
{
    /** Sem comprar há esse número de dias ou mais, o cliente conta como sumido. */
    public const DIAS_SUMIDO = 60;

    /**
     * @param list<array<string, mixed>> $linhas id, name, email, pedidos, total, ultima_compra
     * @return list<array<string, mixed>>
     */
    public static function montar(array $linhas, DateTimeImmutable $agora): array
    {
        $saida = [];
        foreach ($linhas as $l) {
            $pedidos = (int) $l['pedidos'];
            $total = (float) $l['total'];
            $ultima = new DateTimeImmutable((string) $l['ultima_compra'], $agora->getTimezone());
            $dias = (int) floor(($agora->getTimestamp() - $ultima->getTimestamp()) / 86400);
            $saida[] = [
                'id' => (int) $l['id'],
                'nome' => (string) $l['name'],
                'email' => (string) $l['email'],
                'pedidos' => $pedidos,
                'total' => round($total, 2),
                'ticket_medio' => $pedidos > 0 ? round($total / $pedidos, 2) : 0.0,
                'ultima_compra' => (string) $l['ultima_compra'],
                'dias_sem_comprar' => $dias,
                'sumido' => $dias >= self::DIAS_SUMIDO,
            ];
        }
        usort($saida, static fn (array $a, array $b): int => ($b['total'] <=> $a['total']) ?: strcmp($a['nome'], $b['nome']));

        return $saida;
    }
}
