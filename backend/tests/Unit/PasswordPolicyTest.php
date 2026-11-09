<?php

namespace Tests\Unit;

use App\Auth\PasswordPolicy;
use Tests\TestCase;

/**
 * Regra de senha nova (cadastro, funcionário, troca no painel).
 */
final class PasswordPolicyTest extends TestCase
{
    public function testRecusaSenhaCurta(): void
    {
        $this->assertTrue(PasswordPolicy::problema('1') !== null);
        $this->assertTrue(PasswordPolicy::problema('1234567') !== null);
    }

    public function testAceitaOitoCaracteres(): void
    {
        $this->assertNull(PasswordPolicy::problema('12345678'));
        $this->assertNull(PasswordPolicy::problema('senha boa com espaço'));
    }

    public function testContaCaracteresENaoBytes(): void
    {
        // "çãéíõâêó" tem 8 caracteres e 16 bytes: precisa passar.
        $this->assertNull(PasswordPolicy::problema('çãéíõâêó'));
        // 7 acentuados são 14 bytes, mas continuam 7 caracteres.
        $this->assertTrue(PasswordPolicy::problema('çãéíõâê') !== null);
    }

    public function testRecusaSoEspacos(): void
    {
        $this->assertTrue(PasswordPolicy::problema('          ') !== null);
    }

    public function testRecusaAcimaDoQueOBcryptLe(): void
    {
        $this->assertNull(PasswordPolicy::problema(str_repeat('a', 72)));
        $this->assertTrue(PasswordPolicy::problema(str_repeat('a', 73)) !== null);
    }
}
