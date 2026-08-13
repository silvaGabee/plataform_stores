<?php

namespace App\Payment;

use DateTimeImmutable;
use InvalidArgumentException;

/**
 * Valida os dados de cartão informados no checkout.
 *
 * Isto NÃO autoriza pagamento: o projeto não tem adquirente (ver
 * docs/AUDITORIA-E-PLANO.md). A classe confere formato e devolve apenas o que
 * pode ser guardado — nome, bandeira e os quatro últimos dígitos. O número
 * completo e o CVV existem só dentro destes métodos e nunca são persistidos.
 */
final class CardValidator
{
    /** Comprimentos aceitos por bandeira; o resto usa 3 dígitos de CVV. */
    private const CVV_POR_BANDEIRA = ['amex' => 4];

    /**
     * @param array<string, mixed>|null $card
     * @return array{holder: string, last4: string, brand: string}|null
     * @throws InvalidArgumentException quando algum campo é inválido
     */
    public static function validate(?array $card): ?array
    {
        if (!$card) {
            return null;
        }
        $holder = trim((string) ($card['holder'] ?? ''));
        $number = self::digitsOnly((string) ($card['number'] ?? ''));
        $expiry = trim((string) ($card['expiry'] ?? ''));
        $cvv = self::digitsOnly((string) ($card['cvv'] ?? ''));

        if (mb_strlen($holder) < 3) {
            throw new InvalidArgumentException('Informe o nome impresso no cartão.');
        }
        if (!self::luhnValid($number)) {
            throw new InvalidArgumentException('Número do cartão inválido.');
        }
        self::assertNotExpired($expiry);

        $brand = self::detectBrand($number);
        $cvvLen = self::CVV_POR_BANDEIRA[$brand] ?? 3;
        if (strlen($cvv) !== $cvvLen) {
            throw new InvalidArgumentException('CVV inválido.');
        }

        return [
            'holder' => $holder,
            'last4' => substr($number, -4),
            'brand' => $brand,
        ];
    }

    public static function digitsOnly(string $value): string
    {
        return preg_replace('/\D+/', '', $value) ?? '';
    }

    /** Checksum de Luhn — pega dígito trocado e a maioria das transposições. */
    public static function luhnValid(string $digits): bool
    {
        $len = strlen($digits);
        if ($len < 13 || $len > 19) {
            return false;
        }
        $sum = 0;
        $parity = $len % 2;
        for ($i = 0; $i < $len; $i++) {
            $d = (int) $digits[$i];
            if ($i % 2 === $parity) {
                $d *= 2;
                if ($d > 9) {
                    $d -= 9;
                }
            }
            $sum += $d;
        }

        return $sum % 10 === 0;
    }

    public static function detectBrand(string $digits): string
    {
        if (preg_match('/^4/', $digits)) {
            return 'visa';
        }
        if (preg_match('/^(5[1-5]|2[2-7])/', $digits)) {
            return 'mastercard';
        }
        if (preg_match('/^3[47]/', $digits)) {
            return 'amex';
        }
        if (preg_match('/^(636368|438935|504175|451416|636297|5067|4576|4011)/', $digits)) {
            return 'elo';
        }
        if (preg_match('/^(606282|3841)/', $digits)) {
            return 'hipercard';
        }

        return 'card';
    }

    /**
     * O cartão vale até o ÚLTIMO dia do mês impresso.
     *
     * A comparação é feita em datas zeradas de propósito. A versão anterior
     * comparava `new DateTimeImmutable('first day of this month')` — que carrega
     * a hora e os microssegundos do instante atual — com um
     * `createFromFormat('Y-m-d', ...)`, que zera os microssegundos. O primeiro
     * era sempre maior, e por isso TODO cartão vencendo no mês corrente era
     * recusado como expirado, o mês inteiro.
     */
    private static function assertNotExpired(string $expiry): void
    {
        if (!preg_match('/^(0[1-9]|1[0-2])\/([0-9]{2})$/', $expiry, $m)) {
            throw new InvalidArgumentException('Validade inválida. Use MM/AA.');
        }
        $mes = (int) $m[1];
        $ano = 2000 + (int) $m[2];

        $inicioDoMesAtual = (new DateTimeImmutable('today'))->modify('first day of this month')->setTime(0, 0, 0);
        $inicioDoMesDoCartao = DateTimeImmutable::createFromFormat(
            'Y-m-d H:i:s',
            sprintf('%04d-%02d-01 00:00:00', $ano, $mes)
        );
        if ($inicioDoMesDoCartao === false || $inicioDoMesDoCartao < $inicioDoMesAtual) {
            throw new InvalidArgumentException('Cartão expirado.');
        }
    }
}
