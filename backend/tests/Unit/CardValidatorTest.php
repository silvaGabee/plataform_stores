<?php

namespace Tests\Unit;

use Tests\TestCase;

/**
 * Validação dos dados de cartão no checkout.
 *
 * Este código NÃO autoriza pagamento — não existe adquirente no projeto (ver
 * docs/AUDITORIA-E-PLANO.md). Ele só valida o formato e extrai o que pode ser
 * guardado: nome, bandeira e os quatro últimos dígitos. O número completo e o
 * CVV nunca saem daqui, e é isso que estes testes protegem.
 */
final class CardValidatorTest extends TestCase
{
    /** @return array<string, mixed> */
    private function cartaoValido(array $sobrescreve = []): array
    {
        return $sobrescreve + [
            'holder' => 'FULANO DE TAL',
            'number' => '4111 1111 1111 1111',
            'expiry' => '12/34',
            'cvv' => '123',
        ];
    }

    public function testDevolveApenasNomeBandeiraEUltimosQuatro(): void
    {
        $meta = card_validate_checkout_input($this->cartaoValido());

        $this->assertSame(['holder', 'last4', 'brand'], array_keys($meta));
        $this->assertSame('1111', $meta['last4']);
        $this->assertSame('visa', $meta['brand']);
    }

    public function testNumeroCompletoECvvNuncaSaem(): void
    {
        $meta = card_validate_checkout_input($this->cartaoValido());
        $serializado = json_encode($meta);

        $this->assertFalse(str_contains($serializado, '4111111111111111'), 'número completo vazou');
        $this->assertFalse(str_contains($serializado, '123'), 'CVV vazou');
    }

    public function testLuhnRecusaNumeroInvalido(): void
    {
        // Um dígito trocado no fim quebra o checksum.
        $this->expectException(
            \InvalidArgumentException::class,
            fn () => card_validate_checkout_input($this->cartaoValido(['number' => '4111111111111112']))
        );
    }

    public function testBandeirasReconhecidas(): void
    {
        $casos = [
            '4111111111111111' => 'visa',
            '5555555555554444' => 'mastercard',
            '6062826244319182' => 'hipercard',
        ];
        foreach ($casos as $numero => $bandeira) {
            $meta = card_validate_checkout_input($this->cartaoValido(['number' => $numero]));
            $this->assertSame($bandeira, $meta['brand'], 'número ' . $numero);
        }
    }

    public function testAmexUsaCvvDeQuatroDigitos(): void
    {
        $meta = card_validate_checkout_input($this->cartaoValido([
            'number' => '378282246310005',
            'cvv' => '1234',
        ]));
        $this->assertSame('amex', $meta['brand']);

        // Três dígitos, que valem para as outras bandeiras, aqui não valem.
        $this->expectException(
            \InvalidArgumentException::class,
            fn () => card_validate_checkout_input($this->cartaoValido([
                'number' => '378282246310005',
                'cvv' => '123',
            ]))
        );
    }

    public function testCartaoExpirado(): void
    {
        $anoPassado = date('y', strtotime('-1 year'));
        $this->expectException(
            \InvalidArgumentException::class,
            fn () => card_validate_checkout_input($this->cartaoValido(['expiry' => '01/' . $anoPassado]))
        );
    }

    public function testValidadeNoMesCorrenteAindaVale(): void
    {
        // O cartão vence no FIM do mês impresso, então o mês atual é válido.
        $meta = card_validate_checkout_input($this->cartaoValido(['expiry' => date('m/y')]));
        $this->assertSame('visa', $meta['brand']);
    }

    public function testFormatoDeValidadeInvalido(): void
    {
        foreach (['13/30', '2030-12', '1/30', ''] as $expiry) {
            $this->expectException(
                \InvalidArgumentException::class,
                fn () => card_validate_checkout_input($this->cartaoValido(['expiry' => $expiry])),
                'validade ' . var_export($expiry, true)
            );
        }
    }

    public function testNomeMuitoCurto(): void
    {
        $this->expectException(
            \InvalidArgumentException::class,
            fn () => card_validate_checkout_input($this->cartaoValido(['holder' => 'AB']))
        );
    }

    public function testSemCartaoDevolveNull(): void
    {
        $this->assertNull(card_validate_checkout_input(null));
    }
}
